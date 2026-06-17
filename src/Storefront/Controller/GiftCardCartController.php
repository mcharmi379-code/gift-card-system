<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Storefront\Controller;

use ICTECHGiftCard\Core\Cart\GiftCardCartProcessor;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class GiftCardCartController extends StorefrontController
{
    /**
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityRepository $voucherRepository,
    ) {
    }

    #[Route(path: '/checkout/gift-card/add', name: 'frontend.checkout.gift-card.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addGiftCard(Cart $cart, Request $request, SalesChannelContext $context): Response
    {
        try {
            $code = (string) $request->request->get('code');
            $code = \trim($code);

            if ($code === '') {
                throw RoutingException::missingRequestParameter('code');
            }

            $voucher = $this->findValidVoucher(\strtoupper($code), $context);

            if ($voucher === null) {
                $this->addFlash(self::DANGER, $this->trans('ictech-gift-card.checkout.invalidVoucherCode'));
                return $this->createActionResponse($request);
            }

            // Check if already in cart
            $lineItemId = md5(GiftCardCartProcessor::LINE_ITEM_TYPE . '_' . $voucher->getCode());
            if ($cart->has($lineItemId)) {
                $this->addFlash(self::WARNING, $this->trans('ictech-gift-card.checkout.voucherAlreadyInCart'));
                return $this->createActionResponse($request);
            }

            $lineItem = new LineItem(
                $lineItemId,
                GiftCardCartProcessor::LINE_ITEM_TYPE,
                $voucher->getCode(),
                1
            );
            $lineItem->setLabel(\sprintf('Gift Card: %s', $voucher->getCode()));
            $lineItem->setGood(false);
            $lineItem->setStackable(false);
            $lineItem->setRemovable(true);

            $this->cartService->add($cart, $lineItem, $context);

            $this->addFlash(self::SUCCESS, $this->trans('ictech-gift-card.checkout.voucherAddedSuccess'));
        } catch (\Exception $e) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    private function findValidVoucher(string $code, SalesChannelContext $context): ?GiftCardVoucherEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('code', $code),
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('status', VoucherStatus::Used->value),
                new EqualsFilter('status', VoucherStatus::Canceled->value),
                new EqualsFilter('status', VoucherStatus::WaitingValidOrder->value),
            ]),
        ]));
        $criteria->setLimit(1);

        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context->getContext())->first();

        if ($voucher === null) {
            return null;
        }

        $expiresAt = $voucher->getExpiresAt();
        if ($expiresAt !== null && $expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        if ($voucher->getRemainingBalance() <= 0.0) {
            return null;
        }

        return $voucher;
    }
}
