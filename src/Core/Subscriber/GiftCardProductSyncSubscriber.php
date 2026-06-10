<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GiftCardProductSyncSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<GiftCardEntity>> $giftCardRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $productRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $taxRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $deliveryTimeRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $giftCardRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $deliveryTimeRepository,
        private readonly EntityRepository $salesChannelRepository,
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
        $giftCardEvent = $event->getEventByEntityName(GiftCardDefinition::ENTITY_NAME);

        if ($giftCardEvent === null) {
            return;
        }

        $context = $event->getContext();

        foreach ($giftCardEvent->getWriteResults() as $writeResult) {
            $primaryKey = $writeResult->getPrimaryKey();
            /** @var array<string,mixed>|string $primaryKey */
            $giftCardId = \is_array($primaryKey)
                ? (\is_string($primaryKey['id'] ?? null) ? $primaryKey['id'] : '')
                : $primaryKey;

            if ($giftCardId === '') {
                continue;
            }

            $this->syncProduct($giftCardId, $context);
        }
    }

    private function syncProduct(string $giftCardId, Context $context): void
    {
        $criteria = new Criteria([$giftCardId]);
        $criteria->addAssociation('media');

        /** @var GiftCardEntity|null $giftCard */
        $giftCard = $this->giftCardRepository->search($criteria, $context)->first();

        if ($giftCard === null) {
            return;
        }

        $taxId       = $this->getDefaultTaxId($context);
        $deliveryId  = $this->getDefaultDeliveryTimeId($context);

        if ($giftCard->getProductId() !== null) {
            $this->updateProduct($giftCard, $taxId, $deliveryId, $context);
        } else {
            $this->createProduct($giftCard, $taxId, $deliveryId, $context);
        }
    }

    private function createProduct(GiftCardEntity $giftCard, ?string $taxId, ?string $deliveryId, Context $context): void
    {
        $productId = Uuid::randomHex();

        $payload = $this->buildProductPayload($productId, $giftCard, $taxId, $deliveryId, $context);

        $this->productRepository->create([$payload], $context);

        $this->giftCardRepository->update([[
            'id'               => $giftCard->getId(),
            'productId'        => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
        ]], $context);
    }

    private function updateProduct(GiftCardEntity $giftCard, ?string $taxId, ?string $deliveryId, Context $context): void
    {
        $productId = $giftCard->getProductId();

        if ($productId === null) {
            return;
        }

        $payload = $this->buildProductPayload($productId, $giftCard, $taxId, $deliveryId, $context, false);

        $this->productRepository->update([$payload], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductPayload(
        string $productId,
        GiftCardEntity $giftCard,
        ?string $taxId,
        ?string $deliveryId,
        Context $context,
        bool $isCreate = true,
    ): array {
        $payload = [
            'id'             => $productId,
            'productNumber'  => 'GIFTCARD-' . \strtoupper(\substr($giftCard->getId(), 0, 8)),
            'name'           => $giftCard->getName(),
            'stock'          => $giftCard->getQuantity() ?? 999999,
            'price'          => [[
                'currencyId' => Defaults::CURRENCY,
                'gross'      => $giftCard->getAmount(),
                'net'        => $giftCard->getAmount(),
                'linked'     => true,
            ]],
            'shippingFree'   => true,
            'active'         => $giftCard->isActive(),
            'taxId'          => $taxId,
            'deliveryTimeId' => $deliveryId,
            'customFields'   => [
                'ictech_gift_card_id'           => $giftCard->getId(),
                'ictech_gift_card_validity_days' => $giftCard->getValidityDays(),
                'ictech_gift_card_code_prefix'   => $giftCard->getCodePrefix(),
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => $giftCard->getName(),
                ],
            ],
        ];

        // Only set visibilities and media on initial creation to avoid duplicate-key errors on update
        if ($isCreate) {
            if ($giftCard->getMediaId() !== null) {
                $payload['media'] = [[
                    'id'       => Uuid::randomHex(),
                    'mediaId'  => $giftCard->getMediaId(),
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
        $criteria->addFilter(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter(
                'typeId',
                \Shopware\Core\Defaults::SALES_CHANNEL_TYPE_STOREFRONT
            )
        );
        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();

        $visibilities = [];
        foreach ($salesChannelIds as $salesChannelId) {
            $visibilities[] = [
                'id'             => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
            ];
        }

        return $visibilities;
    }

    private function getDefaultTaxId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $result = $this->taxRepository->searchIds($criteria, $context);

        return $result->firstId();
    }

    private function getDefaultDeliveryTimeId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $result = $this->deliveryTimeRepository->searchIds($criteria, $context);

        return $result->firstId();
    }
}
