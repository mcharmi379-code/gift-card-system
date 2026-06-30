<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Storefront\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
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
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class GiftCardPageController extends StorefrontController
{
    private const CONFIG_DOMAIN = 'ICTECHGiftCard.config.';

    /**
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection> $giftCardRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly EntityRepository $giftCardRepository,
        private readonly EntityRepository $templateRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly Environment $twig,
    ) {
    }

    #[Route(path: '/gift-card', name: 'frontend.ictech.gift_card.page', methods: ['GET'])]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $navigationRootId = $context->getSalesChannel()->getNavigationCategoryId();
        if ($navigationRootId) {
            $categoryId = \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('ictech-gift-card-navigation-' . $navigationRootId);
            $request->attributes->set('navigationId', $categoryId);
            $routeParams = $request->attributes->get('_route_params');
            if (! \is_array($routeParams)) {
                $routeParams = [];
            }
            $routeParams['navigationId'] = $categoryId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $page = $this->genericPageLoader->load($request, $context);
        $salesChannelId = $context->getSalesChannelId();

        $active = $this->systemConfigService->getBool('ICTECHGiftCard.config.active', $salesChannelId);

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
            'active' => $active,
        ]);
    }

    #[Route(path: '/gift-card/preview', name: 'frontend.ictech.gift_card.preview', methods: ['GET', 'POST'])]
    public function preview(Request $request, SalesChannelContext $context): Response
    {
        $params = $this->getPreviewParams($request);

        // On GET refresh with no data, show friendly empty state
        if ($request->isMethod('GET')
            && $params['templateId'] === ''
            && $params['senderName'] === ''
        ) {
            return new Response(
                '<html><body style="font-family:Arial;text-align:center;padding:40px"><p>Please use the Preview button on the gift card page.</p></body></html>'
            );
        }

        return $this->generatePreviewPdf($params, $context);
    }

    /**
     * @return array{templateId: string, senderName: string, recipientName: string, message: string, amount: string, sendDate: string}
     */
    private function getPreviewParams(Request $request): array
    {
        $get = static function (string $key) use ($request): string {
            $value = $request->get($key);
            return \is_scalar($value) ? (string) $value : '';
        };

        return [
            'templateId' => $get('giftCardTemplateId'),
            'senderName' => $get('giftCardSenderName'),
            'recipientName' => $get('giftCardRecipientName'),
            'message' => $get('giftCardMessage'),
            'amount' => $get('giftCardAmount'),
            'sendDate' => $get('giftCardSendDate'),
        ];
    }

    /**
     * @param array{templateId: string, senderName: string, recipientName: string, message: string, amount: string, sendDate: string} $params
     */
    private function generatePreviewPdf(
        array $params,
        SalesChannelContext $context,
    ): Response {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        if ($params['templateId'] !== '') {
            $criteria->addFilter(new EqualsFilter('id', $params['templateId']));
        }
        $criteria->setLimit(1);
        $template = $this->templateRepository->search(
            $criteria,
            $context->getContext()
        )->first();

        $shopName = $this->systemConfigService->getString(
            'core.basicInformation.shopName',
            $context->getSalesChannelId()
        );
        if ($shopName === '') {
            $shopName = 'Our Shop';
        }

        $cardImage = $this->buildCardImageHtml($template, $context->getSalesChannelId());
        $priceStr = $this->formatPriceString((float) $params['amount'], $context);
        $formattedSendDate = $this->formatSendDate($params['sendDate']);

        try {
            $html = $this->twig->render('@ICTECHGiftCard/documents/gift_card_pdf.html.twig', [
                'card_lastname' => $params['recipientName'],
                'card_price' => $priceStr,
                'card_from' => $params['senderName'],
                'card_code' => 'PREVIEW-1234-5678',
                'card_message' => $params['message'],
                'card_image' => $cardImage,
                'shop_name' => $shopName,
                'validity_date' => $formattedSendDate,
            ]);
        } catch (\Throwable $e) {
            $html = '<html><body>Gift Card Code: PREVIEW-1234-5678</body></html>';
        }

        return $this->renderDompdfResponse($html);
    }

    private function buildCardImageHtml(?object $template, string $salesChannelId): string
    {
        $imgUrl = $this->getTemplateImageUrl($template);
        if ($imgUrl === '') {
            return '';
        }

        $w = $this->getConfigDimension('pdfCardWidth', $salesChannelId, 300);
        $h = $this->getConfigDimension('pdfCardHeight', $salesChannelId, 192);

        return '<img src="' . htmlspecialchars($imgUrl)
            . '" width="' . $w . '" height="' . $h
            . '" alt="Gift Card" style="max-width:100%">';
    }

    private function getTemplateImageUrl(?object $template): string
    {
        if ($template === null || ! method_exists($template, 'getMedia')) {
            return '';
        }
        $media = $template->getMedia();
        return $media ? $media->getUrl() : '';
    }

    private function getConfigDimension(string $configKey, string $salesChannelId, int $default): int
    {
        $val = $this->systemConfigService->getInt(self::CONFIG_DOMAIN . $configKey, $salesChannelId);
        return $val !== 0 ? $val : $default;
    }

    private function formatPriceString(float $amount, SalesChannelContext $context): string
    {
        $currencySymbol = $context->getCurrency()->getSymbol();
        return number_format($amount, 2) . ' ' . $currencySymbol;
    }

    private function formatSendDate(string $sendDate): string
    {
        if ($sendDate === '') {
            return '';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $sendDate);
        return $date ? $date->format('d.m.Y') : $sendDate;
    }

    private function renderDompdfResponse(string $html): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-preview.pdf"',
        ]);
    }

    /**
     * @return iterable<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity>
     */
    private function loadTemplates(SalesChannelContext $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('tag', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->templateRepository->search(
            $criteria,
            $context->getContext()
        )->getEntities();
    }

    /**
     * @return iterable<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity>
     */
    private function loadGiftCards(SalesChannelContext $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('template.media');
        $criteria->addAssociation('template.translations');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('productId', null),
        ]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', null),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
        ]));
        $criteria->addSorting(new FieldSorting('amount', FieldSorting::ASCENDING));

        return $this->giftCardRepository->search(
            $criteria,
            $context->getContext()
        )->getEntities();
    }

    /**
     * @param iterable<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity> $giftCards
     *
     * @return list<array{giftCardId: string, productId: string, amount: float, templateId: string|null}>
     */
    private function buildAmountOptions(iterable $giftCards): array
    {
        $options = [];
        $seenAmounts = [];

        foreach ($giftCards as $giftCard) {
            $productId = $giftCard->getProductId();
            $amount = $giftCard->getAmount();
            $templateId = $giftCard->getTemplateId();
            $amountKey = \number_format($amount, 2, '.', '') . '_' . ($templateId ?? '');

            if ($productId === null
                || $productId === ''
                || isset($seenAmounts[$amountKey])
            ) {
                continue;
            }

            $seenAmounts[$amountKey] = true;
            $options[] = [
                'giftCardId' => $giftCard->getUniqueIdentifier(),
                'productId' => $productId,
                'amount' => $amount,
                'templateId' => $templateId,
            ];
        }

        return $options;
    }

    /**
     * @param iterable<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity> $templates
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function buildTagFilters(iterable $templates): array
    {
        $filters = $this->initializeTagFilters();

        foreach ($templates as $template) {
            $filters = $this->processTemplateTag($template, $filters);
        }

        $filtersValues = \array_values($filters);
        $this->sortTagFilters($filtersValues);

        return $filtersValues;
    }

    /**
     * @return array<string, array{key: string, label: string, count: int}>
     */
    private function initializeTagFilters(): array
    {
        return [
            'all' => [
                'key' => 'all',
                'label' => 'All',
                'count' => 0,
            ],
        ];
    }

    /**
     * @param array<string, array{key: string, label: string, count: int}> $filters
     *
     * @return array<string, array{key: string, label: string, count: int}>
     */
    private function processTemplateTag(object $template, array $filters): array
    {
        $key = $this->getTemplateTagKey($template);

        if (! isset($filters['all'])) {
            $filters['all'] = [
                'key' => 'all',
                'label' => 'All',
                'count' => 0,
            ];
        }
        $filters['all']['count']++;

        $count = isset($filters[$key]) ? $filters[$key]['count'] : 0;
        $filters[$key] = [
            'key' => $key,
            'label' => $key,
            'count' => $count + 1,
        ];

        return $filters;
    }

    private function getTemplateTagKey(object $template): string
    {
        if (! method_exists($template, 'getTag')) {
            return 'Various';
        }
        $tag = \trim((string) $template->getTag());
        return $tag !== '' ? $tag : 'Various';
    }

    /**
     * @param list<array{key: string, label: string, count: int}> $filtersValues
     */
    private function sortTagFilters(array &$filtersValues): void
    {
        \usort($filtersValues, static function (array $a, array $b): int {
            if ($a['key'] === 'all') {
                return -1;
            }
            if ($b['key'] === 'all') {
                return 1;
            }
            return $a['label'] <=> $b['label'];
        });
    }

    /**
     * @return array{
     *     storefrontCardWidth: int,
     *     storefrontCardHeight: int
     * }
     */
    private function loadConfig(string $salesChannelId): array
    {
        $getInt = fn (string $k): int => $this->systemConfigService->getInt(self::CONFIG_DOMAIN . $k, $salesChannelId);

        return [
            'storefrontCardWidth' => $getInt('storefrontCardWidth'),
            'storefrontCardHeight' => $getInt('storefrontCardHeight'),
        ];
    }
}
