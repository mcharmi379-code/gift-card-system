<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Service;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class GiftCardNavigationInstaller
{
    public const CATEGORY_NAME = 'Gift Cards';
    public const CATEGORY_URL = '/gift-card';

    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $categoryRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    private function getSalesChannelBaseUrl(SalesChannelEntity $salesChannel, Context $context): string
    {
        $result = $this->salesChannelRepository->search(
            (new Criteria([$salesChannel->getId()]))->addAssociation('domains'),
            $context
        )->first();

        $domainCollection = $result?->get('domains');
        if ($domainCollection === null || $domainCollection->count() === 0) {
            return self::CATEGORY_URL;
        }

        // Prefer the default language domain (no sub-path like /de)
        foreach ($domainCollection as $domain) {
            $url = \rtrim((string) $domain->get('url'), '/');
            if ($domain->get('languageId') === Defaults::LANGUAGE_SYSTEM) {
                return $url . self::CATEGORY_URL;
            }
        }

        $url = \rtrim((string) $domainCollection->first()->get('url'), '/');

        return $url . self::CATEGORY_URL;
    }

    /**
     * Build per-language translations so each language's nav link points to its own domain URL.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildTranslations(SalesChannelEntity $salesChannel, Context $context): array
    {
        $result = $this->salesChannelRepository->search(
            (new Criteria([$salesChannel->getId()]))->addAssociation('domains'),
            $context
        )->first();

        $domainCollection = $result?->get('domains');

        // Always add system language fallback
        $defaultUrl = self::CATEGORY_URL;
        $translations = [
            Defaults::LANGUAGE_SYSTEM => [
                'name' => self::CATEGORY_NAME,
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'externalLink' => $defaultUrl,
                'linkNewTab' => false,
            ],
        ];

        if ($domainCollection === null) {
            return $translations;
        }

        foreach ($domainCollection as $domain) {
            $languageId = $domain->get('languageId');
            $url = \rtrim((string) $domain->get('url'), '/') . self::CATEGORY_URL;

            if ($languageId === Defaults::LANGUAGE_SYSTEM) {
                $defaultUrl = $url;
            }

            $translations[$languageId] = [
                'name' => self::CATEGORY_NAME,
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'externalLink' => $url,
                'linkNewTab' => false,
            ];
        }

        // Update system language entry with actual default URL
        $translations[Defaults::LANGUAGE_SYSTEM]['externalLink'] = $defaultUrl;

        return $translations;
    }

    public function install(Context $context): void
    {
        $salesChannels = $this->getStorefrontSalesChannels($context);

        foreach ($salesChannels as $salesChannel) {
            $navigationCategoryId = $salesChannel->get('navigationCategoryId');
            if (!\is_string($navigationCategoryId) || !Uuid::isValid($navigationCategoryId)) {
                continue;
            }

            $categoryId = $this->getCategoryId($navigationCategoryId);
            $lastCategoryId = $this->getLastChildCategoryId($navigationCategoryId, $categoryId, $context);
            $externalLink = $this->getSalesChannelBaseUrl($salesChannel, $context);

            // Build per-language translations using each domain URL
            $translations = $this->buildTranslations($salesChannel, $context);

            $payload = [
                'id' => $categoryId,
                'parentId' => $navigationCategoryId,
                'afterCategoryId' => $lastCategoryId,
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'externalLink' => $externalLink,
                'linkNewTab' => false,
                'active' => true,
                'visible' => true,
                'displayNestedProducts' => false,
                'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
                'name' => self::CATEGORY_NAME,
                'translations' => $translations,
                'customFields' => [
                    'ictech_gift_card_navigation' => true,
                ],
            ];

            if ($lastCategoryId === null) {
                unset($payload['afterCategoryId']);
            }

            $this->categoryRepository->upsert([$payload], $context);
        }
    }

    /**
     * @return iterable<SalesChannelEntity>
     */
    private function getStorefrontSalesChannels(Context $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    private function getLastChildCategoryId(string $parentId, string $ownCategoryId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::DESCENDING));
        $criteria->setLimit(5);

        $children = $this->categoryRepository->search($criteria, $context);

        foreach ($children->getEntities() as $child) {
            if ($child->getUniqueIdentifier() !== $ownCategoryId) {
                return $child->getUniqueIdentifier();
            }
        }

        return null;
    }

    private function getCategoryId(string $navigationRootId): string
    {
        return Uuid::fromStringToHex('ictech-gift-card-navigation-' . $navigationRootId);
    }
}
