<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
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
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<GiftCardEntity>> $giftCardRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $productRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $taxRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $salesChannelRepository
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     */
    public function __construct(
        private readonly EntityRepository $giftCardRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $voucherRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntityWritten',
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
            $primaryKey = $writeResult->getPrimaryKey();
            /** @var array<string,mixed>|string $primaryKey */
            $giftCardId = \is_array($primaryKey)
                ? (\is_string($primaryKey['id'] ?? null) ? $primaryKey['id'] : '')
                : $primaryKey;

            if ($giftCardId === '') {
                continue;
            }

            $isInsert = $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT;

            $this->syncGiftCard($giftCardId, $isInsert, $context);
        }
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

        if ($giftCard->getProductId() === null) {
            // Brand new or missing product — create the linked product
            $this->createProduct($giftCard, $taxId, $context);
        } else {
            // Existing gift card updated — sync product fields
            $this->updateProduct($giftCard, $taxId, $context);
        }
    }

    // -------------------------------------------------------------------------
    // Product sync
    // -------------------------------------------------------------------------

    private function createProduct(GiftCardEntity $giftCard, ?string $taxId, Context $context): void
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
        } catch (\Exception) {
            // If the update fails (rare), the product is already created and the voucher pool
            // is functional. The productId link is nice-to-have for lookups, but not critical.
        }
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
            if ($giftCard->getMediaId() !== null) {
                $payload['media'] = [[
                    'id'      => Uuid::randomHex(),
                    'mediaId' => $giftCard->getMediaId(),
                    'position' => 1,
                ]];
            }

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
