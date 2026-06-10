<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Storefront\Controller;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardVoucherDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class GiftCardAccountController extends StorefrontController
{
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly EntityRepository $giftCardVoucherRepository,
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
        $page = $this->genericPageLoader->load($request, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $vouchers = $this->giftCardVoucherRepository->search($criteria, $context->getContext());

        return $this->renderStorefront('@ICTECHGiftCard/storefront/page/account/gift-cards/index.html.twig', [
            'page' => $page,
            'vouchers' => $vouchers->getElements(),
        ]);
    }
}
