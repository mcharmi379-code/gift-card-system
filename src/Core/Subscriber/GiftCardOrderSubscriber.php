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
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
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
            MailBeforeValidateEvent::class  => 'onMailBeforeValidate',
        ];
    }

    // -------------------------------------------------------------------------
    // Order placed → process gift card line items
    // -------------------------------------------------------------------------

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order      = $event->getOrder();
        $context    = $event->getContext();
        $lineItems  = $order->getLineItems();

        if ($lineItems === null) {
            return;
        }

        $customerId  = \strtolower($order->getOrderCustomer()?->getCustomerId() ?? '');
        $orderNumber = $order->getOrderNumber() ?? '';
        $orderId     = \strtolower($order->getId());

        // Gather purchaser info for confirmation emails
        $purchaserEmail = $order->getOrderCustomer()?->getEmail() ?? '';
        $purchaserName  = \trim(
            ($order->getOrderCustomer()?->getFirstName() ?? '') . ' ' .
            ($order->getOrderCustomer()?->getLastName() ?? '')
        );

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $this->handleGiftCardPurchase($lineItem, $orderId, $customerId, $purchaserEmail, $purchaserName, $context);
                continue;
            }

            if ($lineItem->getType() === GiftCardCartProcessor::LINE_ITEM_TYPE) {
                $this->handleVoucherRedemption($lineItem, $orderId, $orderNumber, $customerId, $context);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Mail injection → append gift card codes to order confirmation emails
    // -------------------------------------------------------------------------

    public function onMailBeforeValidate(MailBeforeValidateEvent $event): void
    {
        $templateData = $event->getTemplateData();
        $data         = $event->getData();

        // Only process order-related emails
        $order = $templateData['order'] ?? null;
        if ($order === null) {
            return;
        }

        // Extract order ID — works with both entity objects and arrays
        $orderId = null;
        if (\is_object($order) && \method_exists($order, 'getId')) {
            $orderId = $order->getId();
        } elseif (\is_array($order) && isset($order['id'])) {
            $orderId = $order['id'];
        }

        if ($orderId === null || $orderId === '') {
            return;
        }

        // Find vouchers linked to this order
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', \strtolower($orderId)));
        $criteria->setLimit(50);

        $vouchers = $this->voucherRepository->search($criteria, $event->getContext());

        if ($vouchers->getTotal() === 0) {
            return;
        }

        // Build a block listing the gift card codes
        $htmlBlock = '<div style="margin-top:20px;padding:16px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;">';
        $htmlBlock .= '<h3 style="margin:0 0 12px;font-size:16px;color:#333;">🎁 Gift Card Code(s)</h3>';
        $htmlBlock .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $htmlBlock .= '<tr style="background:#e9ecef;"><th style="padding:8px;text-align:left;">Code</th><th style="padding:8px;text-align:right;">Value</th></tr>';

        $plainBlock = "\n--- Gift Card Code(s) ---\n";

        $codesList = [];
        foreach ($vouchers->getElements() as $voucher) {
            /** @var GiftCardVoucherEntity $voucher */
            $code   = $voucher->getCode();
            $amount = \number_format($voucher->getOriginalAmount(), 2);
            $codesList[] = $code;

            $htmlBlock .= '<tr><td style="padding:8px;font-family:monospace;font-weight:bold;">' . \htmlspecialchars($code) . '</td>';
            $htmlBlock .= '<td style="padding:8px;text-align:right;">€' . $amount . '</td></tr>';

            $plainBlock .= "Code: {$code} — Value: €{$amount}\n";
        }

        $htmlBlock .= '</table></div>';

        // Append to existing mail content
        $contentHtml  = $data['contentHtml'] ?? '';
        $contentPlain = $data['contentPlain'] ?? '';

        if (\is_string($contentHtml) && $contentHtml !== '') {
            $event->addData('contentHtml', $contentHtml . $htmlBlock);
        }
        if (\is_string($contentPlain) && $contentPlain !== '') {
            $event->addData('contentPlain', $contentPlain . $plainBlock);
        }

        // Also expose codes in template data for custom templates
        $event->addTemplateData('giftCardCodes', \implode(', ', $codesList));
    }

    // -------------------------------------------------------------------------
    // Gift card product purchased → assign pool voucher + personalise + email
    // -------------------------------------------------------------------------

    private function handleGiftCardPurchase(
        OrderLineItemEntity $lineItem,
        string $orderId,
        string $customerId,
        string $purchaserEmail,
        string $purchaserName,
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

        // --- Extract payload from storefront form ---
        $payload = $lineItem->getPayload() ?? [];

        // Support both storefront field names and internal names
        $deliveryMethod    = $this->extractPayloadString($payload, ['giftCardDeliveryMethod', 'deliveryMethod'], 'email');
        $recipientEmail    = $this->extractPayloadString($payload, ['giftCardEmail', 'recipientEmail']);
        $recipientName     = $this->extractPayloadString($payload, ['giftCardRecipientName', 'recipientName']);
        $senderName        = $this->extractPayloadString($payload, ['giftCardSenderName', 'senderName']);
        $personalMessage   = $this->extractPayloadString($payload, ['giftCardMessage', 'personalMessage']);
        $scheduledSendDate = $this->extractPayloadString($payload, ['giftCardSendDate', 'scheduledSendDate']);
        $templateId        = $this->extractPayloadString($payload, ['giftCardTemplateId']);

        // Default scheduled send date to today
        if ($scheduledSendDate === '') {
            $scheduledSendDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        // For "Buy for Self" (print delivery), use purchaser details
        if ($deliveryMethod === 'print') {
            $recipientEmail = $purchaserEmail;
            $recipientName  = $recipientName !== '' ? $recipientName : $purchaserName;
        }

        // --- Pick or generate a voucher ---
        $voucher = $this->pickOrGenerateVoucher($giftCardId, $giftCard, $context);
        if ($voucher === null) {
            return; // Should not happen but guard against it
        }

        // --- Compute expiry ---
        $validityDays = \is_numeric($giftCard['validity_days']) ? (int) $giftCard['validity_days'] : 365;
        $expiresAt    = (new \DateTimeImmutable())->modify("+{$validityDays} days")->format('Y-m-d');

        // --- Persist personalisation to voucher ---
        $updateData = [
            'id'                => $voucher->getId(),
            'orderId'           => $orderId,
            'orderVersionId'    => Defaults::LIVE_VERSION,
            'orderLineItemId'   => $lineItem->getId(),
            'customerId'        => $customerId !== '' ? $customerId : null,
            'status'            => VoucherStatus::Unused->value,
            'expiresAt'         => $expiresAt,
            'recipientName'     => $recipientName !== '' ? $recipientName : null,
            'recipientEmail'    => $recipientEmail !== '' ? $recipientEmail : null,
            'senderName'        => $senderName !== '' ? $senderName : null,
            'personalMessage'   => $personalMessage !== '' ? $personalMessage : null,
            'scheduledSendDate' => $scheduledSendDate,
        ];

        // Store templateId in customFields for PDF generation later
        if ($templateId !== '') {
            $updateData['customFields'] = ['giftCardTemplateId' => $templateId];
        }

        $this->voucherRepository->update([$updateData], $context);

        // --- Reload voucher with updated fields ---
        /** @var GiftCardVoucherEntity|null $updatedVoucher */
        $updatedVoucher = $this->voucherRepository
            ->search(new Criteria([$voucher->getId()]), $context)
            ->first();

        if (!$updatedVoucher instanceof GiftCardVoucherEntity) {
            return;
        }

        // --- Send emails based on delivery method ---
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        try {
            if ($deliveryMethod === 'print') {
                // "Buy for Self" → send voucher directly to purchaser
                $this->emailService->sendPurchaserSelfEmail(
                    $updatedVoucher,
                    $purchaserEmail,
                    $purchaserName,
                    $context,
                );
            } else {
                // "Send to Someone Else" → send confirmation to purchaser
                $this->emailService->sendPurchaserConfirmationEmail(
                    $updatedVoucher,
                    $purchaserEmail,
                    $purchaserName,
                    $context,
                );

                // If scheduled for today or past, also send to recipient immediately
                if ($scheduledSendDate <= $today && $recipientEmail !== '') {
                    $this->emailService->sendRecipientEmail($updatedVoucher, $context);
                }
                // Otherwise the scheduled task will pick it up on the right date
            }
        } catch (\Throwable) {
            // Email failure must not break the order flow
        }
    }

    // -------------------------------------------------------------------------
    // Pick an existing pool voucher or generate one on-the-fly
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $giftCard
     */
    private function pickOrGenerateVoucher(string $giftCardId, array $giftCard, Context $context): ?GiftCardVoucherEntity
    {
        // Try to pick an unassigned voucher from the pre-generated pool
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('giftCardId', $giftCardId),
            new EqualsFilter('status', VoucherStatus::WaitingValidOrder->value),
            new EqualsFilter('orderId', null),
        ]));
        $criteria->setLimit(1);

        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

        if ($voucher !== null) {
            return $voucher;
        }

        // Pool exhausted → generate a new voucher on-the-fly
        $prefix   = \strtoupper(\trim(\is_string($giftCard['code_prefix']) ? $giftCard['code_prefix'] : ''));
        $amount   = \is_numeric($giftCard['amount']) ? (float) $giftCard['amount'] : 0.0;
        $validity = \is_numeric($giftCard['validity_days']) ? (int) $giftCard['validity_days'] : 365;

        $code = $this->generateUniqueCode($prefix, $giftCardId, $context);

        $voucherId = Uuid::randomHex();
        $expiresAt = (new \DateTimeImmutable())->modify("+{$validity} days")->format('Y-m-d');

        $this->voucherRepository->create([[
            'id'               => $voucherId,
            'giftCardId'       => $giftCardId,
            'code'             => $code,
            'originalAmount'   => $amount,
            'remainingBalance' => $amount,
            'currencyId'       => $context->getCurrencyId(),
            'status'           => VoucherStatus::WaitingValidOrder->value,
            'expiresAt'        => $expiresAt,
        ]], $context);

        /** @var GiftCardVoucherEntity|null $newVoucher */
        $newVoucher = $this->voucherRepository->search(new Criteria([$voucherId]), $context)->first();

        return $newVoucher;
    }

    /**
     * Generate a unique code with the given prefix, checking for collisions.
     */
    private function generateUniqueCode(string $prefix, string $giftCardId, Context $context): string
    {
        $maxAttempts = 50;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $suffix = \strtoupper(\bin2hex(\random_bytes(4)));
            $code   = $prefix !== '' ? $prefix . '-' . $suffix : $suffix;

            // Check for collision
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('code', $code));
            $criteria->setLimit(1);

            if ($this->voucherRepository->searchIds($criteria, $context)->getTotal() === 0) {
                return $code;
            }
        }

        // Fallback: use UUID-based code to guarantee uniqueness
        return $prefix . '-' . \strtoupper(\substr(Uuid::randomHex(), 0, 8));
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
     * Extract a string from the payload trying multiple key names.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function extractPayloadString(array $payload, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (\is_string($value) && \trim($value) !== '') {
                return \trim($value);
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findGiftCardByProductId(string $productId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id, amount, validity_days, code_prefix
             FROM ictech_gift_card
             WHERE product_id = UNHEX(:productId)
             LIMIT 1',
            ['productId' => $productId]
        );

        return $row !== false ? $row : null;
    }
}
