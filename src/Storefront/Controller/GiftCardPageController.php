<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class GiftCardPageController extends StorefrontController
{
    private const CONFIG_DOMAIN = 'ICTECHGiftCard.config.';

    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity>> $giftCardRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity>> $templateRepository
     */
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly EntityRepository $giftCardRepository,
        private readonly EntityRepository $templateRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    #[Route(path: '/gift-card/preview', name: 'frontend.ictech.gift_card.preview', methods: ['GET', 'POST'])]
    public function preview(Request $request, SalesChannelContext $context): Response
    {
        // Support both GET (query params) and POST
        $get = fn(string $key) => $request->get($key, '');

        $templateId    = (string) $get('giftCardTemplateId');
        $senderName    = (string) $get('giftCardSenderName');
        $recipientName = (string) $get('giftCardRecipientName');
        $message       = (string) $get('giftCardMessage');
        $amount        = (string) $get('giftCardAmount');
        $sendDate      = (string) $get('giftCardSendDate');
        $delivery      = (string) ($get('giftCardDeliveryMethod') ?: 'email');

        // On GET refresh with no data, show friendly empty state
        if ($request->isMethod('GET') && $templateId === '' && $senderName === '') {
            return new Response('<html><body style="font-family:Arial;text-align:center;padding:40px"><p>Please use the Preview button on the gift card page.</p></body></html>');
        }

        $criteria = new Criteria();
        $criteria->addAssociation('media');
        if ($templateId !== '') {
            $criteria->addFilter(new EqualsFilter('id', $templateId));
        }
        $criteria->setLimit(1);
        $template = $this->templateRepository->search($criteria, $context->getContext())->first();

        $pdfContent = (string) ($this->systemConfigService->get(
            self::CONFIG_DOMAIN . 'pdfContent',
            $context->getSalesChannelId()
        ) ?? '');

        $shopName  = (string) ($this->systemConfigService->get('core.basicInformation.shopName', $context->getSalesChannelId()) ?? '');
        $cardImage = '';
        if ($template !== null && $template->get('media') !== null) {
            $imgUrl = (string) ($template->get('media')->get('url') ?? '');
            $w = (int) ($this->systemConfigService->get(self::CONFIG_DOMAIN . 'pdfCardWidth', $context->getSalesChannelId()) ?? 300);
            $h = (int) ($this->systemConfigService->get(self::CONFIG_DOMAIN . 'pdfCardHeight', $context->getSalesChannelId()) ?? 192);
            $cardImage = '<img src="' . htmlspecialchars($imgUrl) . '" width="' . $w . '" height="' . $h . '" alt="Gift Card" style="max-width:100%">';
        }

        $pdfContent = str_replace(
            ['{{card_lastname}}', '{{card_price}}', '{{card_from}}', '{{card_code}}', '{{card_message}}', '{{card_image}}', '{{shop_name}}', '{{validity_date}}'],
            [htmlspecialchars($recipientName), htmlspecialchars($amount), htmlspecialchars($senderName), '****-****-****', nl2br(htmlspecialchars($message)), $cardImage, htmlspecialchars($shopName), htmlspecialchars($sendDate)],
            $pdfContent
        );

        return $this->renderStorefront('@ICTECHGiftCard/storefront/page/gift-card/preview.html.twig', [
            'template'      => $template,
            'pdfContent'    => $pdfContent,
            'senderName'    => $senderName,
            'recipientName' => $recipientName,
            'delivery'      => $delivery,
            'giftCardConfig' => $this->loadConfig($context->getSalesChannelId()),
        ]);
    }

    #[Route(path: '/gift-card', name: 'frontend.ictech.gift_card.page', methods: ['GET'])]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->genericPageLoader->load($request, $context);
        $salesChannelId = $context->getSalesChannelId();

        $templates = $this->loadTemplates($context);
        $giftCards = $this->loadGiftCards($context);
        $amountOptions = $this->buildAmountOptions($giftCards);
        $tagFilters = $this->buildTagFilters($templates);

        return $this->renderStorefront('@ICTECHGiftCard/storefront/page/gift-card/index.html.twig', [
            'page' => $page,
            'giftCardConfig' => $this->loadConfig($salesChannelId),
            'giftCardTemplates' => $templates,
            'giftCardTagFilters' => $tagFilters,
            'giftCardAmountOptions' => $amountOptions,
            'giftCardDefaultAmount' => $amountOptions[0] ?? null,
        ]);
    }

    private function loadTemplates(SalesChannelContext $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('tag', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->templateRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function loadGiftCards(SalesChannelContext $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('template.media');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('productId', null),
        ]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', null),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
        ]));
        $criteria->addSorting(new FieldSorting('amount', FieldSorting::ASCENDING));

        return $this->giftCardRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * @return list<array{giftCardId: string, productId: string, amount: float}>
     */
    private function buildAmountOptions(iterable $giftCards): array
    {
        $options = [];
        $seenAmounts = [];

        foreach ($giftCards as $giftCard) {
            $productId = $giftCard->get('productId');
            $amount = (float) $giftCard->get('amount');
            $amountKey = \number_format($amount, 2, '.', '');

            if (!\is_string($productId) || $productId === '' || isset($seenAmounts[$amountKey])) {
                continue;
            }

            $seenAmounts[$amountKey] = true;
            $options[] = [
                'giftCardId' => $giftCard->getUniqueIdentifier(),
                'productId' => $productId,
                'amount' => $amount,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function buildTagFilters(iterable $templates): array
    {
        $filters = [
            'all' => [
                'key' => 'all',
                'label' => 'All',
                'count' => 0,
            ],
        ];

        foreach ($templates as $template) {
            $customize = $template->getCustomFields()['giftCardTemplateCustomize'] ?? [];

            if (\is_array($customize) && ($customize['pdfOnly'] ?? false)) {
                continue;
            }

            $tag = \trim((string) $template->get('tag'));
            $key = $tag !== '' ? $tag : 'Various';

            $filters['all']['count']++;
            $filters[$key] ??= [
                'key' => $key,
                'label' => $key,
                'count' => 0,
            ];
            $filters[$key]['count']++;
        }

        return \array_values($filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $salesChannelId): array
    {
        $keys = [
            'storefrontText',
            'storefrontCardWidth',
            'storefrontCardHeight',
            'storefrontCardLargeWidth',
            'storefrontCardLargeHeight',
        ];

        $config = [];
        foreach ($keys as $key) {
            $config[$key] = $this->systemConfigService->get(self::CONFIG_DOMAIN . $key, $salesChannelId);
        }

        return $config;
    }
}
