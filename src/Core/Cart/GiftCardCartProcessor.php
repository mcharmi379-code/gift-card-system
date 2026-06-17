<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Cart;

use ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class GiftCardCartProcessor implements CartProcessorInterface
{
    public const LINE_ITEM_TYPE = 'ictech_gift_card_voucher';

    /**
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     * @param EntityRepository<GiftCardTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $voucherRepository,
        private readonly EntityRepository $transactionRepository,
        private readonly AbsolutePriceCalculator $priceCalculator,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        foreach ($original->getLineItems()->filterType(self::LINE_ITEM_TYPE) as $lineItem) {
            $code = $lineItem->getReferencedId();
            if ($code === null || $code === '') {
                $original->getLineItems()->remove($lineItem->getId());
                continue;
            }

            $voucher = $this->findValidVoucher(\strtoupper($code), $context->getContext());

            if ($voucher === null) {
                $original->getLineItems()->remove($lineItem->getId());
                continue;
            }

            $cartTotal    = $toCalculate->getPrice()->getTotalPrice();
            $deductAmount = \min($voucher->getRemainingBalance(), $cartTotal);

            if ($deductAmount <= 0.0) {
                continue;
            }

            $lineItem->setLabel(\sprintf('Gift Card: %s', $voucher->getCode()));
            $lineItem->setPriceDefinition(new AbsolutePriceDefinition(-$deductAmount));
            $lineItem->setPrice(
                $this->priceCalculator->calculate(
                    -$deductAmount,
                    $toCalculate->getLineItems()->getPrices(),
                    $context
                )
            );

            $toCalculate->add($lineItem);
        }
    }

    /**
     * Persist the redemption after order is placed.
     * Called from GiftCardOrderSubscriber once the order line item with the voucher code is confirmed.
     */
    public function persistRedemption(
        string $voucherCode,
        float $amountUsed,
        string $orderId,
        string $orderNumber,
        string $customerId,
        Context $context,
    ): void {
        $orderId = \strtolower($orderId);
        $customerId = \strtolower($customerId);

        error_log(sprintf(
            "ICTECH_GC_LOG - persistRedemption: code=%s, amountUsed=%f, orderId=%s, customerId=%s",
            $voucherCode,
            $amountUsed,
            $orderId,
            $customerId
        ));

        $voucher = $this->findValidVoucher(\strtoupper($voucherCode), $context);

        if ($voucher === null) {
            return;
        }

        $balanceBefore = $voucher->getRemainingBalance();
        $balanceAfter  = \round($balanceBefore - $amountUsed, 2);
        $newStatus     = $balanceAfter <= 0.0 ? VoucherStatus::Used : VoucherStatus::Unused;

        $this->transactionRepository->create([[
            'id'             => Uuid::randomHex(),
            'voucherId'      => $voucher->getId(),
            'orderId'        => $orderId,
            'orderVersionId' => \Shopware\Core\Defaults::LIVE_VERSION,
            'customerId'     => $customerId !== '' ? $customerId : null,
            'amountUsed'     => $amountUsed,
            'balanceBefore'  => $balanceBefore,
            'balanceAfter'   => \max(0.0, $balanceAfter),
        ]], $context);

        $this->voucherRepository->update([[
            'id'                  => $voucher->getId(),
            'remainingBalance'    => \max(0.0, $balanceAfter),
            'status'              => $newStatus->value,
            'usedInOrderNumber'   => $orderNumber,
        ]], $context);
    }

    private function findValidVoucher(string $code, Context $context): ?GiftCardVoucherEntity
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
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

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
