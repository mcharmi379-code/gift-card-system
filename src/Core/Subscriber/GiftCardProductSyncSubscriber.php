<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GiftCardProductSyncSubscriber implements EventSubscriberInterface
{
    private const SYNC_FLAG = 'ictech_gift_card_product_sync_running';

    /**
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection> $giftCardRepository
     * @param EntityRepository<\Shopware\Core\Content\Product\ProductCollection> $productRepository
     * @param EntityRepository<\Shopware\Core\System\Tax\TaxCollection> $taxRepository
     * @param EntityRepository<\Shopware\Core\System\SalesChannel\SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $giftCardRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly \Doctrine\DBAL\Connection $connection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntityWritten',
            \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent::class => 'beforeDeleteTemplate',
        ];
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        // Prevent re-entry when we write productId back to the gift card
        if ($event->getContext()->hasExtension(self::SYNC_FLAG)) {
            return;
        }

        $giftCardEvent = $event->getEventByEntityName(GiftCardDefinition::ENTITY_NAME);

        if ($giftCardEvent === null) {
            return;
        }

        $context = $event->getContext();
        $context->addExtension(self::SYNC_FLAG, new ArrayStruct());

        foreach ($giftCardEvent->getWriteResults() as $writeResult) {
            $this->processWriteResult($writeResult, $context);
        }
    }

    public function beforeDeleteTemplate(\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent $event): void
    {
        $templateIds = array_filter(
            $event->getIds(\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateDefinition::ENTITY_NAME),
            'is_string'
        );
        if ($templateIds === []) {
            return;
        }

        $context = $event->getContext();
        $criteria = new Criteria();
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter('templateId', $templateIds));

        $giftCardIds = $this->giftCardRepository->searchIds($criteria, $context)->getIds();
        if ($giftCardIds === []) {
            return;
        }

        $updatePayload = [];
        foreach ($giftCardIds as $id) {
            $updatePayload[] = [
                'id' => $id,
                'active' => false,
            ];
        }

        $this->giftCardRepository->update($updatePayload, $context);
    }

    private function processWriteResult(EntityWriteResult $writeResult, Context $context): void
    {
        $primaryKey = $writeResult->getPrimaryKey();
        /** @var array<string, string>|string $primaryKey */
        $giftCardId = \is_array($primaryKey)
            ? (\is_string($primaryKey['id'] ?? null) ? $primaryKey['id'] : '')
            : $primaryKey;

        if ($giftCardId === '') {
            return;
        }

        $isInsert = $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT;

        $this->syncGiftCard($giftCardId, $isInsert, $context);
    }

    private function syncGiftCard(string $giftCardId, bool $isInsert, Context $context): void
    {
        $criteria = new Criteria([$giftCardId]);
        $criteria->addAssociation('media');

        /** @var GiftCardEntity|null $giftCard */
        $giftCard = $this->giftCardRepository->search($criteria, $context)->first();

        if ($giftCard === null) {
            return;
        }

        $taxId = $this->getDefaultTaxId($context);

        $productId = $giftCard->getProductId();

        if ($productId === null) {
            // Brand new or missing product — create the linked product
            $productId = $this->createProduct($giftCard, $taxId, $context);
        } else {
            // Existing gift card updated — sync product fields
            $this->updateProduct($giftCard, $taxId, $context);
        }

        $this->syncProductMediaAndCover($productId, $giftCard);
    }

    // -------------------------------------------------------------------------
    // Product sync
    // -------------------------------------------------------------------------

    private function createProduct(GiftCardEntity $giftCard, ?string $taxId, Context $context): string
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create(
            [$this->buildProductPayload($productId, $giftCard, $taxId, $context, true)],
            $context
        );

        // Update via a completely isolated context to avoid DAL batching with the insert.
        // This creates a separate write transaction, bypassing the conflict.
        try {
            $isolatedContext = Context::createDefaultContext();

            $this->giftCardRepository->update([[
                'id'               => $giftCard->getId(),
                'productId'        => $productId,
                'productVersionId' => Defaults::LIVE_VERSION,
            ]], $isolatedContext);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $productId;
    }

    private function updateProduct(GiftCardEntity $giftCard, ?string $taxId, Context $context): void
    {
        $productId = $giftCard->getProductId();

        if ($productId === null) {
            return;
        }

        $this->productRepository->update(
            [$this->buildProductPayload($productId, $giftCard, $taxId, $context, false)],
            $context
        );
    }

    private function syncProductMediaAndCover(string $productId, GiftCardEntity $giftCard): void
    {
        $mediaId = $giftCard->getMediaId();
        if ($mediaId === null || $mediaId === '') {
            return;
        }

        try {
            $productIdBin = \hex2bin($productId);
            $mediaIdBin = \hex2bin($mediaId);

            // Check if this media is already associated with the product
            $existingProductMediaId = $this->connection->fetchOne(
                'SELECT id FROM product_media WHERE product_id = :productId AND media_id = :mediaId LIMIT 1',
                ['productId' => $productIdBin, 'mediaId' => $mediaIdBin]
            );

            if ($existingProductMediaId === false) {
                // Delete old media associations to avoid duplicates when image is changed
                $this->connection->executeStatement(
                    'DELETE FROM product_media WHERE product_id = :productId',
                    ['productId' => $productIdBin]
                );

                // Insert the new media association
                $productMediaIdBin = Uuid::randomBytes();
                $productMediaId = Uuid::fromBytesToHex($productMediaIdBin);
                $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.000');

                $this->connection->insert('product_media', [
                    'id' => $productMediaIdBin,
                    'product_id' => $productIdBin,
                    'media_id' => $mediaIdBin,
                    'position' => 1,
                    'created_at' => $now,
                ]);
            } else {
                $productMediaId = Uuid::fromBytesToHex($existingProductMediaId);
            }

            // Set as product cover
            $this->connection->executeStatement(
                'UPDATE product SET cover_id = :coverId WHERE id = :productId',
                [
                    'coverId' => \hex2bin($productMediaId),
                    'productId' => $productIdBin,
                ]
            );
        } catch (\Throwable $e) {
            error_log('Failed to sync product media and cover: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductPayload(
        string $productId,
        GiftCardEntity $giftCard,
        ?string $taxId,
        Context $context,
        bool $isCreate,
    ): array {
        $payload = [
            'id'             => $productId,
            'productNumber'  => 'GIFTCARD-' . \strtoupper(\substr($giftCard->getId(), -8)),
            'name'           => $giftCard->getName(),
            'stock'          => 999999,
            'price'          => [[
                'currencyId' => Defaults::CURRENCY,
                'gross'      => $giftCard->getAmount(),
                'net'        => $giftCard->getAmount(),
                'linked'     => true,
            ]],
            'shippingFree'   => true,
            'deliveryTimeId' => null,
            'active'         => $giftCard->isActive(),
            'taxId'          => $taxId,
            'customFields'   => [
                'ictech_gift_card_id'            => $giftCard->getId(),
                'ictech_gift_card_validity_days'  => $giftCard->getValidityDays(),
                'ictech_gift_card_code_prefix'    => $giftCard->getCodePrefix(),
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => $giftCard->getName()],
            ],
        ];

        if ($isCreate) {
            $payload['visibilities'] = $this->buildVisibilities($giftCard, $context);
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildVisibilities(GiftCardEntity $giftCard, Context $context): array
    {
        if ($giftCard->getSalesChannelId() !== null) {
            return [[
                'id'             => Uuid::randomHex(),
                'salesChannelId' => $giftCard->getSalesChannelId(),
                'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();

        $visibilities = [];
        foreach ($salesChannelIds as $id) {
            $visibilities[] = [
                'id'             => Uuid::randomHex(),
                'salesChannelId' => $id,
                'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
            ];
        }

        return $visibilities;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getDefaultTaxId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        return $this->taxRepository->searchIds($criteria, $context)->firstId();
    }
}
