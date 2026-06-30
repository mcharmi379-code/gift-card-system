<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use ICTECHGiftCard\Core\Cart\GiftCardCartProcessor;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
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
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection> $voucherRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection> $giftCardRepository
     */
    public function __construct(
        private readonly EntityRepository $voucherRepository,
        private readonly GiftCardCartProcessor $cartProcessor,
        private readonly \Symfony\Component\Messenger\MessageBusInterface $messageBus,
        private readonly EntityRepository $giftCardRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
            MailBeforeValidateEvent::class => 'onMailBeforeValidate',
        ];
    }

    // -------------------------------------------------------------------------
    // Order placed → process gift card line items
    // -------------------------------------------------------------------------

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();
        $lineItems = $order->getLineItems();

        if ($lineItems === null) {
            return;
        }

        $customerId = \strtolower($order->getOrderCustomer()?->getCustomerId() ?? '');
        $orderNumber = $order->getOrderNumber() ?? '';
        $orderId = \strtolower($order->getId());

        // Gather purchaser info for confirmation emails
        $purchaserEmail = $order->getOrderCustomer()?->getEmail() ?? '';
        $purchaserName = \trim(
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
        $order = $event->getTemplateData()['order'] ?? null;
        $orderId = $this->getOrderId($order);

        if ($orderId === null || $orderId === '') {
            return;
        }

        $vouchers = $this->getOrderVouchers($orderId, $event->getContext());

        if ($vouchers->getTotal() === 0) {
            return;
        }

        $mailBlocks = $this->buildMailBlocks($vouchers);
        $this->appendMailContent($event, $mailBlocks);
    }

    /**
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection>
     */
    private function getOrderVouchers(string $orderId, Context $context): \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', \strtolower($orderId)));
        $criteria->setLimit(50);

        return $this->voucherRepository->search($criteria, $context);
    }

    /**
     * @param array{html: string, plain: string, codes: list<string>} $mailBlocks
     */
    private function appendMailContent(MailBeforeValidateEvent $event, array $mailBlocks): void
    {
        $data = $event->getData();
        $contentHtml = $data['contentHtml'] ?? '';
        $contentPlain = $data['contentPlain'] ?? '';

        if (\is_string($contentHtml) && $contentHtml !== '') {
            $event->addData('contentHtml', $contentHtml . $mailBlocks['html']);
        }
        if (\is_string($contentPlain) && $contentPlain !== '') {
            $event->addData('contentPlain', $contentPlain . $mailBlocks['plain']);
        }

        $event->addTemplateData('giftCardCodes', \implode(', ', $mailBlocks['codes']));
    }

    private function getOrderId(mixed $order): ?string
    {
        if (\is_object($order) && \method_exists($order, 'getId')) {
            return $order->getId();
        }
        return $this->getOrderIdFromArray($order);
    }

    private function getOrderIdFromArray(mixed $order): ?string
    {
        if (\is_array($order) && isset($order['id']) && \is_string($order['id'])) {
            return $order['id'];
        }
        return null;
    }

    /**
     * @param \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult<GiftCardVoucherCollection> $vouchers
     *
     * @return array{html: string, plain: string, codes: list<string>}
     */
    private function buildMailBlocks(\Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult $vouchers): array
    {
        $htmlBlock = '<div style="margin-top:20px;padding:16px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;">';
        $htmlBlock .= '<h3 style="margin:0 0 12px;font-size:16px;color:#333;">🎁 Gift Card Code(s)</h3>';
        $htmlBlock .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $htmlBlock .= '<tr style="background:#e9ecef;"><th style="padding:8px;text-align:left;">Code</th><th style="padding:8px;text-align:right;">Value</th></tr>';

        $plainBlock = "\n--- Gift Card Code(s) ---\n";
        $codesList = [];

        foreach ($vouchers->getElements() as $voucher) {
            /** @var GiftCardVoucherEntity $voucher */
            $code = $voucher->getCode();
            $amount = \number_format($voucher->getOriginalAmount(), 2);
            $codesList[] = $code;

            $htmlBlock .= '<tr><td style="padding:8px;font-family:monospace;font-weight:bold;">' . \htmlspecialchars($code) . '</td>';
            $htmlBlock .= '<td style="padding:8px;text-align:right;">€' . $amount . '</td></tr>';

            $plainBlock .= "Code: {$code} — Value: €{$amount}\n";
        }

        $htmlBlock .= '</table></div>';

        return [
            'html' => $htmlBlock,
            'plain' => $plainBlock,
            'codes' => $codesList,
        ];
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

        $giftCard = $this->findGiftCardByProductId($productId, $context);
        if ($giftCard === null) {
            return;
        }

        $giftCardId = \is_string($giftCard['id']) ? $giftCard['id'] : '';
        if ($giftCardId === '') {
            return;
        }

        $this->processVoucherPurchase(
            $lineItem,
            $giftCardId,
            $giftCard,
            $orderId,
            $customerId,
            $purchaserEmail,
            $purchaserName,
            $context
        );
    }

    /**
     * @param array<string, mixed> $giftCard
     */
    private function processVoucherPurchase(
        OrderLineItemEntity $lineItem,
        string $giftCardId,
        array $giftCard,
        string $orderId,
        string $customerId,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $payload = $lineItem->getPayload() ?? [];
        $deliveryMethod = $this->extractPayloadString($payload, ['giftCardDeliveryMethod', 'deliveryMethod'], 'email');
        $recipientEmail = $this->extractPayloadString($payload, ['giftCardEmail', 'recipientEmail']);
        $recipientName = $this->extractPayloadString($payload, ['giftCardRecipientName', 'recipientName']);
        $senderName = $this->extractPayloadString($payload, ['giftCardSenderName', 'senderName']);
        $personalMessage = $this->extractPayloadString($payload, ['giftCardMessage', 'personalMessage']);
        $scheduledSendDate = $this->extractPayloadString($payload, ['giftCardSendDate', 'scheduledSendDate']);
        $templateId = $this->extractPayloadString($payload, ['giftCardTemplateId']);

        if ($scheduledSendDate === '') {
            $scheduledSendDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        if ($deliveryMethod === 'print') {
            $recipientEmail = $purchaserEmail;
            $recipientName = $recipientName !== '' ? $recipientName : $purchaserName;
        }

        $quantity = $lineItem->getQuantity();
        for ($i = 0; $i < $quantity; $i++) {
            $this->createAndSendVoucher(
                $giftCardId,
                $giftCard,
                $orderId,
                $lineItem->getId(),
                $customerId,
                $scheduledSendDate,
                $recipientEmail,
                $recipientName,
                $senderName,
                $personalMessage,
                $templateId,
                $deliveryMethod,
                $purchaserEmail,
                $purchaserName,
                $context
            );
        }
    }

    /**
     * @param array<string, mixed> $giftCard
     */
    private function createAndSendVoucher(
        string $giftCardId,
        array $giftCard,
        string $orderId,
        string $lineItemId,
        string $customerId,
        string $scheduledSendDate,
        string $recipientEmail,
        string $recipientName,
        string $senderName,
        string $personalMessage,
        string $templateId,
        string $deliveryMethod,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $voucher = $this->pickOrGenerateVoucher($giftCardId, $giftCard, $context);
        if ($voucher === null) {
            return;
        }

        $expiresAt = $this->calculateExpiryDate($giftCard, $scheduledSendDate);
        $this->updateVoucherPersonalisation(
            $voucher,
            $orderId,
            $lineItemId,
            $customerId,
            $expiresAt,
            $recipientEmail,
            $recipientName,
            $senderName,
            $personalMessage,
            $scheduledSendDate,
            $templateId,
            $deliveryMethod,
            $context
        );

        $updatedVoucher = $this->reloadVoucher($voucher->getId(), $context);
        if ($updatedVoucher === null) {
            return;
        }

        $this->messageBus->dispatch(new \ICTECHGiftCard\Core\Message\SendGiftCardMailMessage(
            $updatedVoucher->getId(),
            $deliveryMethod,
            $purchaserEmail,
            $purchaserName,
            $scheduledSendDate,
            $recipientEmail,
            $context->getLanguageId(),
            $context->getCurrencyId(),
        ));
    }

    /**
     * @param array<string, mixed> $giftCard
     */
    private function calculateExpiryDate(array $giftCard, string $scheduledSendDate): string
    {
        $validityDays = \is_numeric($giftCard['validity_days']) ? (int) $giftCard['validity_days'] : 365;
        $baseDate = \DateTimeImmutable::createFromFormat('Y-m-d', $scheduledSendDate);
        if (! $baseDate) {
            $baseDate = new \DateTimeImmutable();
        }
        return $baseDate->modify("+{$validityDays} days")->format('Y-m-d');
    }

    private function getNullableString(string $val): ?string
    {
        return $val !== '' ? $val : null;
    }

    private function updateVoucherPersonalisation(
        GiftCardVoucherEntity $voucher,
        string $orderId,
        string $lineItemId,
        string $customerId,
        string $expiresAt,
        string $recipientEmail,
        string $recipientName,
        string $senderName,
        string $personalMessage,
        string $scheduledSendDate,
        string $templateId,
        string $deliveryMethod,
        Context $context,
    ): void {
        $updateData = [
            'id' => $voucher->getId(),
            'orderId' => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'orderLineItemId' => $lineItemId,
            'customerId' => $this->getNullableString($customerId),
            'status' => VoucherStatus::Unused->value,
            'expiresAt' => $expiresAt,
            'recipientName' => $this->getNullableString($recipientName),
            'recipientEmail' => $this->getNullableString($recipientEmail),
            'senderName' => $this->getNullableString($senderName),
            'personalMessage' => $this->getNullableString($personalMessage),
            'scheduledSendDate' => $scheduledSendDate,
        ];

        $updateData['customFields'] = $this->buildPersonalisationCustomFields($voucher, $templateId, $deliveryMethod);

        $this->voucherRepository->update([$updateData], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPersonalisationCustomFields(
        GiftCardVoucherEntity $voucher,
        string $templateId,
        string $deliveryMethod,
    ): array {
        $customFields = $voucher->getCustomFields() ?? [];
        if ($templateId !== '') {
            $customFields['giftCardTemplateId'] = $templateId;
        }
        if ($deliveryMethod !== '') {
            $customFields['deliveryMethod'] = $deliveryMethod;
        }
        return $customFields;
    }

    private function reloadVoucher(string $voucherId, Context $context): ?GiftCardVoucherEntity
    {
        return $this->voucherRepository->search(new Criteria([$voucherId]), $context)->first();
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
        $prefix = \strtoupper(\trim(\is_string($giftCard['code_prefix']) ? $giftCard['code_prefix'] : ''));
        $amount = \is_numeric($giftCard['amount']) ? (float) $giftCard['amount'] : 0.0;
        $validity = \is_numeric($giftCard['validity_days']) ? (int) $giftCard['validity_days'] : 365;

        $code = $this->generateUniqueCode($prefix, $giftCardId, $context);

        $voucherId = Uuid::randomHex();
        $expiresAt = (new \DateTimeImmutable())->modify("+{$validity} days")->format('Y-m-d');

        $this->voucherRepository->create([[
            'id' => $voucherId,
            'giftCardId' => $giftCardId,
            'code' => $code,
            'originalAmount' => $amount,
            'remainingBalance' => $amount,
            'currencyId' => $context->getCurrencyId(),
            'status' => VoucherStatus::WaitingValidOrder->value,
            'expiresAt' => $expiresAt,
        ],
        ], $context);

        return $this->voucherRepository->search(new Criteria([$voucherId]), $context)->first();
    }

    /**
     * Generate a unique code with the given prefix, checking for collisions.
     */
    private function generateUniqueCode(string $prefix, string $giftCardId, Context $context): string
    {
        $maxAttempts = 50;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $suffix = \strtoupper(\bin2hex(\random_bytes(4)));
            $code = $prefix !== '' ? $prefix . '-' . $suffix : $suffix;

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
    private function findGiftCardByProductId(string $productId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', \strtolower($productId)));
        $criteria->setLimit(1);

        /** @var \ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity|null $giftCard */
        $giftCard = $this->giftCardRepository->search($criteria, $context)->first();

        if ($giftCard === null) {
            return null;
        }

        return [
            'id' => $giftCard->getId(),
            'amount' => $giftCard->getAmount(),
            'validity_days' => $giftCard->getValidityDays(),
            'code_prefix' => $giftCard->getCodePrefix(),
        ];
    }
}
