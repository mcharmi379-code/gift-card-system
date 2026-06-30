<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Storefront\Controller;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class GiftCardAccountController extends StorefrontController
{
    /**
     * @param EntityRepository<GiftCardVoucherCollection> $giftCardVoucherRepository
     */
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly EntityRepository $giftCardVoucherRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    #[Route(
        path: '/account/gift-cards',
        name: 'frontend.ictech.gift_card.account.page',
        defaults: ['_loginRequired' => true, '_noStore' => true],
        methods: ['GET']
    )]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $active = $this->systemConfigService->getBool('ICTECHGiftCard.config.active', $context->getSalesChannelId());
        if (!$active) {
            throw $this->createNotFoundException();
        }

        $page = $this->genericPageLoader->load($request, $context);
        $vouchers = $this->getVouchersForCustomer($request, $customer, $context);

        return $this->renderStorefront('@ICTECHGiftCard/storefront/page/account/gift-cards/index.html.twig', [
            'page' => $page,
            'vouchers' => $vouchers,
        ]);
    }

    /**
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection>
     */
    private function getVouchersForCustomer(
        Request $request,
        CustomerEntity $customer,
        SalesChannelContext $context,
    ): \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult {
        $p = \max(1, $request->query->getInt('p', 1));
        $limit = 10;

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsFilter('customerId', $customer->getId()),
                    new EqualsFilter('recipientEmail', $customer->getEmail()),
                ]
            )
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset(($p - 1) * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->giftCardVoucherRepository->search($criteria, $context->getContext());
    }
}
