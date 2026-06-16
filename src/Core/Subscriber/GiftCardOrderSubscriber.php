<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use Doctrine\DBAL\Connection;
use ICTECHGiftCard\Core\Cart\GiftCardCartProcessor;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use ICTECHGiftCard\Core\Service\GiftCardEmailService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GiftCardOrderSubscriber implements EventSubscriberInterface
{

    /**
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $voucherRepository,
        private readonly GiftCardCartProcessor $cartProcessor,
        private readonly GiftCardEmailService $emailService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order      = $event->getOrder();
        $context    = $event->getContext();
        $lineItems  = $order->getLineItems();

        if ($lineItems === null) {
            return;
        }

        $customerId = \strtolower($order->getOrderCustomer()?->getCustomerId() ?? '');
        $orderNumber = $order->getOrderNumber() ?? '';
        $orderId = \strtolower($order->getId());

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $this->handleGiftCardPurchase($lineItem, $orderId, $context);
                continue;
            }

            if ($lineItem->getType() === GiftCardCartProcessor::LINE_ITEM_TYPE) {
                $this->handleVoucherRedemption($lineItem, $orderId, $orderNumber, $customerId, $context);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Gift card product purchased → assign pool voucher + personalise + maybe send
    // -------------------------------------------------------------------------

    private function handleGiftCardPurchase(
        OrderLineItemEntity $lineItem,
        string $orderId,
        Context $context,
    ): void {
        $productId = $lineItem->getProductId();
        if ($productId === null) {
            return;
        }

        $giftCard = $this->findGiftCardByProductId($productId);
        if ($giftCard === null) {
            return;
        }

        $giftCardId = \is_string($giftCard['id']) ? $giftCard['id'] : '';
        if ($giftCardId === '') {
            return;
        }

        // Pick one unassigned voucher from the pool
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('giftCardId', $giftCardId),
            new EqualsFilter('status', VoucherStatus::WaitingValidOrder->value),
            new EqualsFilter('orderId', null),
        ]));
        $criteria->setLimit(1);

        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

        if ($voucher === null) {
            return;
        }

        // Personalisation comes from the cart line item payload
        $payload           = $lineItem->getPayload() ?? [];
        $recipientName     = \is_string($payload['recipientName'] ?? null) ? \trim($payload['recipientName']) : '';
        $recipientEmail    = \is_string($payload['recipientEmail'] ?? null) ? \trim($payload['recipientEmail']) : '';
        $senderName        = \is_string($payload['senderName'] ?? null) ? $payload['senderName'] : '';
        $personalMessage   = \is_string($payload['personalMessage'] ?? null) ? $payload['personalMessage'] : null;
        $scheduledSendDate = \is_string($payload['scheduledSendDate'] ?? null)
            ? $payload['scheduledSendDate']
            : (new \DateTimeImmutable())->format('Y-m-d');

        $validityDays = \is_numeric($giftCard['validity_days']) ? (int) $giftCard['validity_days'] : 365;
        $expiresAt    = (new \DateTimeImmutable())->modify("+{$validityDays} days")->format('Y-m-d');

        $this->voucherRepository->update([[
            'id'                => $voucher->getId(),
            'orderId'           => $orderId,
            'orderVersionId'    => Defaults::LIVE_VERSION,
            'orderLineItemId'   => $lineItem->getId(),
            'status'            => VoucherStatus::Unused->value,
            'expiresAt'         => $expiresAt,
            'recipientName'     => $recipientName !== '' ? $recipientName : null,
            'recipientEmail'    => $recipientEmail !== '' ? $recipientEmail : null,
            'senderName'        => $senderName !== '' ? $senderName : null,
            'personalMessage'   => $personalMessage,
            'scheduledSendDate' => $scheduledSendDate,
        ]], $context);

        // Send immediately if scheduled for today or earlier
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        if ($scheduledSendDate <= $today && $recipientEmail !== '') {
            // reload the voucher with updated fields
            /** @var GiftCardVoucherEntity|null $updatedVoucher */
            $updatedVoucher = $this->voucherRepository
                ->search(new Criteria([$voucher->getId()]), $context)
                ->first();

            if ($updatedVoucher instanceof GiftCardVoucherEntity) {
                try {
                    $this->emailService->sendForVoucher($updatedVoucher, $context);
                } catch (\Throwable) {
                    // email failure must not break the order
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Voucher code redeemed at checkout → persist balance deduction
    // -------------------------------------------------------------------------

    private function handleVoucherRedemption(
        OrderLineItemEntity $lineItem,
        string $orderId,
        string $orderNumber,
        string $customerId,
        Context $context,
    ): void {
        $code = $lineItem->getReferencedId();
        if ($code === null || $code === '') {
            return;
        }

        $price = $lineItem->getPrice();
        if ($price === null) {
            return;
        }

        $amountUsed = \abs($price->getTotalPrice());
        if ($amountUsed <= 0.0) {
            return;
        }

        $this->cartProcessor->persistRedemption($code, $amountUsed, $orderId, $orderNumber, $customerId, $context);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function findGiftCardByProductId(string $productId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT HEX(id) AS id, amount, validity_days, code_prefix
             FROM ictech_gift_card
             WHERE product_id = UNHEX(:productId)
             LIMIT 1',
            ['productId' => $productId]
        );

        return $row !== false ? $row : null;
    }
}
