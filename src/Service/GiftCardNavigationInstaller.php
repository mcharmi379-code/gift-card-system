<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class GiftCardNavigationInstaller
{
    public const CATEGORY_NAME = 'Gift Cards';
    public const CATEGORY_URL = '/gift-card';

    /**
     * @param EntityRepository<\Shopware\Core\Content\Category\CategoryCollection> $categoryRepository
     * @param EntityRepository<\Shopware\Core\System\SalesChannel\SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly Connection $connection,
    ) {
    }

    public function install(Context $context): void
    {
        $salesChannels = $this->getStorefrontSalesChannels($context);

        foreach ($salesChannels as $salesChannel) {
            $this->upsertCategoryForSalesChannel($salesChannel, $context);
        }
    }

    private function getSalesChannelBaseUrl(SalesChannelEntity $salesChannel, Context $context): string
    {
        $salesChannelWithDomains = $this->getSalesChannelWithDomains($salesChannel->getId(), $context);
        if (! $salesChannelWithDomains instanceof SalesChannelEntity) {
            return self::CATEGORY_URL;
        }

        $domainCollection = $salesChannelWithDomains->getDomains();
        if ($domainCollection === null) {
            return self::CATEGORY_URL;
        }

        $domainUrl = $this->extractDomainUrl($domainCollection);
        if ($domainUrl === '') {
            return self::CATEGORY_URL;
        }

        return $domainUrl . self::CATEGORY_URL;
    }

    private function extractDomainUrl(\Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection $domainCollection): string
    {
        if ($domainCollection->count() === 0) {
            return '';
        }

        $url = $this->findDefaultLanguageDomainUrl($domainCollection);
        if ($url !== null) {
            return $url;
        }

        $firstDomain = $domainCollection->first();
        if ($firstDomain === null) {
            return '';
        }

        return \rtrim((string) $firstDomain->getUrl(), '/');
    }

    private function getSalesChannelWithDomains(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        return $this->salesChannelRepository->search(
            (new Criteria([$salesChannelId]))->addAssociation('domains'),
            $context
        )->first();
    }

    private function findDefaultLanguageDomainUrl(\Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection $domainCollection): ?string
    {
        foreach ($domainCollection as $domain) {
            if ($domain->getLanguageId() === Defaults::LANGUAGE_SYSTEM) {
                return \rtrim((string) $domain->getUrl(), '/');
            }
        }
        return null;
    }

    /**
     * Build per-language translations so each language's nav link points to its own domain URL.
     *
     * @return array<string, array<string, string|bool>>
     */
    private function buildTranslations(SalesChannelEntity $salesChannel, Context $context): array
    {
        $salesChannelWithDomains = $this->getSalesChannelWithDomains($salesChannel->getId(), $context);

        // Query all languages and their locale codes to support German translation
        $languages = [];
        try {
            $languages = $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(l.id)) as id, lo.code FROM language l INNER JOIN locale lo ON l.locale_id = lo.id'
            );
        } catch (\Exception) {
            // Fallback if DB query fails during tests
        }

        $defaultUrl = $this->getSalesChannelBaseUrl($salesChannel, $context);
        $translations = [];

        foreach ($languages as $langHexId => $localeCode) {
            $domainUrl = null;
            if ($salesChannelWithDomains instanceof SalesChannelEntity && $salesChannelWithDomains->getDomains() !== null) {
                foreach ($salesChannelWithDomains->getDomains() as $domain) {
                    if ($domain->getLanguageId() === $langHexId) {
                        $domainUrl = \rtrim((string) $domain->getUrl(), '/') . self::CATEGORY_URL;
                        break;
                    }
                }
            }

            $url = $domainUrl ?? $defaultUrl;
            $name = \str_starts_with((string)$localeCode, 'de') ? 'Geschenkkarten' : self::CATEGORY_NAME;

            $translations[$langHexId] = [
                'name' => $name,
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'externalLink' => $url,
                'linkNewTab' => false,
            ];
        }

        // Always ensure system language fallback is present
        if (!isset($translations[Defaults::LANGUAGE_SYSTEM])) {
            $systemLocale = $languages[Defaults::LANGUAGE_SYSTEM] ?? 'en-GB';
            $systemName = \str_starts_with($systemLocale, 'de') ? 'Geschenkkarten' : self::CATEGORY_NAME;
            $translations[Defaults::LANGUAGE_SYSTEM] = [
                'name' => $systemName,
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'externalLink' => $defaultUrl,
                'linkNewTab' => false,
            ];
        }

        return $translations;
    }

    private function upsertCategoryForSalesChannel(SalesChannelEntity $salesChannel, Context $context): void
    {
        $navigationCategoryId = $salesChannel->getNavigationCategoryId();
        if (! Uuid::isValid($navigationCategoryId)) {
            return;
        }

        $categoryId = $this->getCategoryId($navigationCategoryId);
        $lastCategoryId = $this->getLastChildCategoryId($navigationCategoryId, $categoryId, $context);
        $externalLink = $this->getSalesChannelBaseUrl($salesChannel, $context);

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

    private function getStorefrontSalesChannels(Context $context): SalesChannelCollection
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
