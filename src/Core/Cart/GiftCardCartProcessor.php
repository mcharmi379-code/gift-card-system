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
use Shopware\Core\System\SystemConfig\SystemConfigService;

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
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        $active = $this->systemConfigService->getBool('ICTECHGiftCard.config.active', $context->getSalesChannelId());
        if (! $active) {
            $this->removeGiftCardLineItems($original);
            return;
        }

        $runningTotal = $this->calculateRunningTotal($toCalculate);

        $state = [
            'hasRestricted' => false,
            'appliedCount'  => 0,
            'runningTotal'  => $runningTotal,
        ];

        foreach ($original->getLineItems()->filterType(self::LINE_ITEM_TYPE) as $lineItem) {
            $state = $this->processVoucherLineItem(
                $lineItem,
                $original,
                $toCalculate,
                $context,
                $state
            );
        }
    }

    private function removeGiftCardLineItems(Cart $cart): void
    {
        foreach ($cart->getLineItems()->filterType(self::LINE_ITEM_TYPE) as $lineItem) {
            $cart->getLineItems()->remove($lineItem->getId());
        }
    }

    private function calculateRunningTotal(Cart $cart): float
    {
        $runningTotal = 0.0;
        foreach ($cart->getLineItems() as $item) {
            if ($item->getType() !== self::LINE_ITEM_TYPE && $item->getPrice() !== null) {
                $runningTotal += $item->getPrice()->getTotalPrice();
            }
        }
        return \max(0.0, $runningTotal);
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

        $voucher = $this->findValidVoucher(\strtoupper($voucherCode), $context);

        if ($voucher === null) {
            return;
        }

        $balanceBefore = $voucher->getRemainingBalance();
        $balanceAfter = \round($balanceBefore - $amountUsed, 2);

        $this->createTransaction(
            $voucher->getId(),
            $orderId,
            $customerId,
            $amountUsed,
            $balanceBefore,
            $balanceAfter,
            $context
        );

        $this->updateVoucherBalance($voucher, $balanceAfter, $orderNumber, $context);
    }

    /**
     * @param array{hasRestricted: bool, appliedCount: int, runningTotal: float} $state
     * @return array{hasRestricted: bool, appliedCount: int, runningTotal: float}
     */
    private function processVoucherLineItem(
        \Shopware\Core\Checkout\Cart\LineItem\LineItem $lineItem,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        array $state,
    ): array {
        $code = $lineItem->getReferencedId();
        if ($code === null || $code === '') {
            $original->getLineItems()->remove($lineItem->getId());
            return $state;
        }

        $voucher = $this->findValidVoucher(\strtoupper($code), $context->getContext());
        if ($voucher === null) {
            $original->getLineItems()->remove($lineItem->getId());
            return $state;
        }

        return $this->evaluateAndApplyVoucher(
            $lineItem,
            $voucher,
            $original,
            $toCalculate,
            $context,
            $state
        );
    }

    /**
     * @param array{hasRestricted: bool, appliedCount: int, runningTotal: float} $state
     * @return array{hasRestricted: bool, appliedCount: int, runningTotal: float}
     */
    private function evaluateAndApplyVoucher(
        \Shopware\Core\Checkout\Cart\LineItem\LineItem $lineItem,
        GiftCardVoucherEntity $voucher,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        array $state,
    ): array {
        $giftCard = $voucher->getGiftCard();
        $isRestricted = $this->isRestrictedGiftCard($giftCard);

        if ($this->hasRestrictionConflict($state, $isRestricted)) {
            $original->getLineItems()->remove($lineItem->getId());
            return $state;
        }

        $deductAmount = \min($voucher->getRemainingBalance(), $state['runningTotal']);
        if ($deductAmount <= 0.0) {
            $original->getLineItems()->remove($lineItem->getId());
            return $state;
        }

        $this->applyVoucherLineItem($lineItem, $voucher, $deductAmount, $toCalculate, $context);

        return [
            'hasRestricted' => $state['hasRestricted'] || $isRestricted,
            'appliedCount'  => $state['appliedCount'] + 1,
            'runningTotal'  => \max(0.0, $state['runningTotal'] - $deductAmount),
        ];
    }

    private function isRestrictedGiftCard(?\ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity $giftCard): bool
    {
        return $giftCard !== null && $giftCard->getRestrictCombine();
    }

    /**
     * @param array{hasRestricted: bool, appliedCount: int, runningTotal: float} $state
     */
    private function hasRestrictionConflict(array $state, bool $isRestricted): bool
    {
        if ($state['appliedCount'] <= 0) {
            return false;
        }
        return $state['hasRestricted'] || $isRestricted;
    }

    private function applyVoucherLineItem(
        \Shopware\Core\Checkout\Cart\LineItem\LineItem $lineItem,
        GiftCardVoucherEntity $voucher,
        float $deductAmount,
        Cart $toCalculate,
        SalesChannelContext $context,
    ): void {
        $lineItem->setLabel(\sprintf('Gift Card: %s', $voucher->getCode()));
        $lineItem->setPriceDefinition(new AbsolutePriceDefinition(-$deductAmount));

        $prices = new \Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection();
        foreach ($toCalculate->getLineItems() as $item) {
            if ($item->getType() !== self::LINE_ITEM_TYPE && $item->getPrice() !== null) {
                $prices->add($item->getPrice());
            }
        }

        $lineItem->setPrice(
            $this->priceCalculator->calculate(
                -$deductAmount,
                $prices,
                $context
            )
        );

        $toCalculate->add($lineItem);
    }

    private function createTransaction(
        string $voucherId,
        string $orderId,
        string $customerId,
        float $amountUsed,
        float $balanceBefore,
        float $balanceAfter,
        Context $context,
    ): void {
        $this->transactionRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucherId,
            'orderId' => $orderId,
            'orderVersionId' => \Shopware\Core\Defaults::LIVE_VERSION,
            'customerId' => $customerId !== '' ? $customerId : null,
            'amountUsed' => $amountUsed,
            'balanceBefore' => $balanceBefore,
            'balanceAfter' => \max(0.0, $balanceAfter),
        ]], $context);
    }

    private function updateVoucherBalance(
        GiftCardVoucherEntity $voucher,
        float $balanceAfter,
        string $orderNumber,
        Context $context,
    ): void {
        $newStatus = VoucherStatus::Unused;
        if ($balanceAfter <= 0.0) {
            $newStatus = VoucherStatus::Used;
        } elseif ($balanceAfter < $voucher->getOriginalAmount()) {
            $newStatus = VoucherStatus::PartiallyUsed;
        }

        $this->voucherRepository->update([[
            'id' => $voucher->getId(),
            'remainingBalance' => \max(0.0, $balanceAfter),
            'status' => $newStatus->value,
            'usedInOrderNumber' => $orderNumber,
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
        $criteria->addAssociation('giftCard');
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
