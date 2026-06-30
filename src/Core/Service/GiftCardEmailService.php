<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Environment;

final class GiftCardEmailService
{
    private const CONFIG = 'ICTECHGiftCard.config.';

    /**
     * @param EntityRepository<\Shopware\Core\Content\MailTemplate\MailTemplateCollection> $mailTemplateRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection> $voucherRepository
     * @param EntityRepository<\Shopware\Core\System\SalesChannel\SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $voucherRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $templateRepository,
<<<<<<< HEAD
        private readonly \League\Flysystem\FilesystemOperator $publicFilesystem,
=======
        private readonly Environment $twig,
>>>>>>> 3a3d49b39548f8ee4288cf63f36fa4676fe419e1
    ) {
    }

    /**
     * Legacy support & scheduled tasks delegation.
     */
    public function sendForVoucher(GiftCardVoucherEntity $voucher, Context $context): void
    {
        $this->sendRecipientEmail($voucher, $context);
    }

    public function sendRecipientEmail(GiftCardVoucherEntity $voucher, Context $context): void
    {
        $recipientEmail = $voucher->getRecipientEmail();
        if ($recipientEmail === null || $recipientEmail === '') {
            return;
        }

        $this->dispatchEmailByDeliveryMethod($voucher, $recipientEmail, $context);

        $this->voucherRepository->update([[
            'id'     => $voucher->getId(),
            'sentAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
    }

    /**
     * Build the pdfContent HTML with all {{variables}} replaced by real values.
     */
    public function buildPdfContent(GiftCardVoucherEntity $voucher, ?string $salesChannelId, string $mode = 'pdf', ?Context $context = null): string
    {
        $expiresAt = $voucher->getExpiresAt();
        $shopName = $this->getShopName($salesChannelId);

        $voucher = $this->resolveVoucherCurrency($voucher, $context);
        $currencySymbol = $this->getCurrencySymbol($voucher);

        $priceStr = \number_format($voucher->getOriginalAmount(), 2) . ' ' . $currencySymbol;
        $cardImage = $this->buildCardImageHtml($voucher, $salesChannelId, $mode, $context);

        try {
            $html = $this->twig->render('@ICTECHGiftCard/documents/gift_card_pdf.html.twig', [
                'card_lastname' => $voucher->getRecipientName() ?? '',
                'card_price'    => $priceStr,
                'card_from'     => $voucher->getSenderName() ?? '',
                'card_code'     => $voucher->getCode(),
                'card_message'  => $voucher->getPersonalMessage() ?? '',
                'card_image'    => $cardImage,
                'shop_name'     => $shopName,
                'validity_date' => $expiresAt?->format('d.m.Y') ?? '',
            ]);

            if ($mode === 'email') {
                return "<div style=\"background-color:#f8f9fa;padding:20px 0;width:100%;min-height:100%;\"><div style=\"font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;border:1px solid #dee2e6;border-radius:8px;background:#ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.05);\">{$html}</div></div>";
            }

            return $html;
        } catch (\Throwable $e) {
            return '<html><body>Gift Card Code: ' . \htmlspecialchars($voucher->getCode()) . '</body></html>';
        }
    }
    public function sendPurchaserConfirmationEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $senderNameValShop = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);

        $templateData = $this->loadConfirmationMailTemplateData($salesChannelId, $context);

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $templateData['subject'],
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $templateData['contentHtml'],
            'contentPlain'    => $templateData['contentPlain'],
        ];

        $data = $this->attachPdfIfEnabled($data, $voucher, $salesChannelId, $context);
        $mailTemplateData = $this->buildPurchaserConfirmationTemplateData($voucher, $purchaserName, $salesChannelId, $context);

        $this->mailService->send($data, $context, $mailTemplateData);
    }

    public function sendPurchaserSelfEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $senderNameValShop = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);

        $templateData = $this->loadSelfMailTemplateData($salesChannelId, $context);

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $templateData['subject'],
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $templateData['contentHtml'],
            'contentPlain'    => $templateData['contentPlain'],
        ];

        $data = $this->attachInlineTemplateImage($data, $voucher, $context);
        $data = $this->attachPdfIfEnabled($data, $voucher, $salesChannelId, $context);
        $mailTemplateData = $this->buildPurchaserSelfTemplateData($voucher, $purchaserName, $salesChannelId, $context);

        $this->mailService->send($data, $context, $mailTemplateData);

        // Mark voucher as sent
        $this->voucherRepository->update([[
            'id'     => $voucher->getId(),
            'sentAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
    }

    public function generatePdfForVoucher(GiftCardVoucherEntity $voucher, Context $context): string
    {
        $salesChannelId = $this->getDefaultSalesChannelId($context);

        $html = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfContent', $salesChannelId);
        if ($html === '') {
            $html = '<html><body>Gift Card Code: {{card_code}}</body></html>';
        }

        $cardImage = $this->buildCardImageHtml($voucher, $salesChannelId, 'pdf', $context);
        $shopName = $this->getShopName($salesChannelId);
        $expiresAt = $voucher->getExpiresAt();

        $voucher = $this->resolveVoucherCurrency($voucher, $context);
        $currencySymbol = $this->getCurrencySymbol($voucher);
        $priceStr = \number_format($voucher->getOriginalAmount(), 2) . ' ' . $currencySymbol;

        $replacements = [
            '{{card_lastname}}'  => \htmlspecialchars($voucher->getRecipientName() ?? ''),
            '{{card_firstname}}' => '',
            '{{card_price}}'     => \htmlspecialchars($priceStr),
            '{{card_from}}'      => \htmlspecialchars($voucher->getSenderName() ?? ''),
            '{{card_code}}'      => \htmlspecialchars($voucher->getCode()),
            '{{card_message}}'   => \nl2br(\htmlspecialchars($voucher->getPersonalMessage() ?? '')),
            '{{card_image}}'     => $cardImage,
            '{{shop_name}}'      => \htmlspecialchars($shopName),
            '{{validity_date}}'  => $expiresAt?->format('d.m.Y') ?? '',
            '{{shop_url}}'       => \htmlspecialchars($this->getShopUrl($salesChannelId, $context)),
        ];

        $html = \str_replace(\array_keys($replacements), \array_values($replacements), $html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', dirname(__DIR__, 6));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        return $output !== null ? $output : '';
    }

    private function dispatchEmailByDeliveryMethod(GiftCardVoucherEntity $voucher, string $recipientEmail, Context $context): void
    {
        $recipientName = $voucher->getRecipientName() ?? '';
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $deliveryMethod = $voucher->getDeliveryMethod() ?? 'email';

        if ($deliveryMethod === 'print') {
            $this->sendPrintEmail($voucher, $recipientEmail, $recipientName, $salesChannelId, $context);
            return;
        }

        $this->sendGiftCardEmail($voucher, $recipientEmail, $recipientName, $salesChannelId, $context);
    }

    // -------------------------------------------------------------------------
    // Email delivery — uses Shopware mail template (ictech_gift_card)
    // -------------------------------------------------------------------------

    private function sendGiftCardEmail(
        GiftCardVoucherEntity $voucher,
        string $recipientEmail,
        string $recipientName,
        ?string $salesChannelId,
        Context $context,
    ): void {
        $template = $this->loadMailTemplate(\ICTECHGiftCard\ICTECHGiftCard::MAIL_TYPE_RECIPIENT, $context);
        if ($template === null) {
            return;
        }

        $data = $this->buildGiftCardMailData($template, $voucher, $recipientEmail, $recipientName, $salesChannelId);
        $data = $this->attachInlineTemplateImage($data, $voucher, $context);
        $data = $this->attachRecipientPdfIfEnabled($data, $voucher, $salesChannelId, $context);

        $templateData = $this->buildGiftCardTemplateData($voucher, $recipientName, $salesChannelId, $context);

        $this->mailService->send($data, $context, $templateData);
    }

    /**
     * @param \Shopware\Core\Framework\DataAbstractionLayer\Entity $template
     * @return array<string, mixed>
     */
    private function buildGiftCardMailData(
        $template,
        GiftCardVoucherEntity $voucher,
        string $recipientEmail,
        string $recipientName,
        ?string $salesChannelId,
    ): array {
        $contentHtml = $this->getMailTemplateHtml($template);
        $contentPlain = $this->getMailTemplatePlain($template);

        $subject = $template->getTranslation('subject');
        if (!\is_string($subject) || $subject === '') {
            $subject = 'Gift card offer from {{ sender_name }}';
        }

        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);

        return [
            'salesChannelId' => $salesChannelId,
            'subject'        => $subject,
            'senderName'     => $shopName !== '' ? $shopName : 'Gift Card',
            'recipients'     => [$recipientEmail => $recipientName],
            'contentHtml'    => $contentHtml,
            'contentPlain'   => $contentPlain,
        ];
    }

    /**
     * @param \Shopware\Core\Framework\DataAbstractionLayer\Entity $template
     */
    private function getMailTemplateHtml($template): string
    {
        $contentHtml = $template->getTranslation('contentHtml');
        if (!\is_string($contentHtml) || $contentHtml === '') {
            return $this->getDefaultHtmlTemplate();
        }
        return $contentHtml;
    }

    /**
     * @param \Shopware\Core\Framework\DataAbstractionLayer\Entity $template
     */
    private function getMailTemplatePlain($template): string
    {
        $contentPlain = $template->getTranslation('contentPlain');
        if (!\is_string($contentPlain) || $contentPlain === '') {
            return $this->getDefaultPlainTemplate();
        }
        return $contentPlain;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function attachInlineTemplateImage(array $data, GiftCardVoucherEntity $voucher, Context $context): array
    {
        $rawTemplateId = $this->getVoucherTemplateId($voucher);
        if ($rawTemplateId === null) {
            return $data;
        }

        $criteria = new Criteria([$rawTemplateId]);
        $criteria->addAssociation('media');
        $template = $this->templateRepository->search($criteria, $context)->first();
        if ($template === null) {
            return $data;
        }

        $media = $template->get('media');
        if (!$media instanceof \Shopware\Core\Content\Media\MediaEntity) {
            return $data;
        }

        return $this->addInlineMediaDataPart($data, $media);
    }

<<<<<<< HEAD
=======
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
>>>>>>> 3a3d49b39548f8ee4288cf63f36fa4676fe419e1
    private function addInlineMediaDataPart(array $data, \Shopware\Core\Content\Media\MediaEntity $media): array
    {
        $relativePath = $media->getPath();

        if ($this->publicFilesystem->has($relativePath)) {
            $mimeType = $media->getMimeType();
            if ($mimeType === null || $mimeType === '') {
                $mimeType = 'image/png';
            }
            $fileContent = $this->publicFilesystem->read($relativePath);
            if ($fileContent !== '') {
                $part = new \Symfony\Component\Mime\Part\DataPart($fileContent, 'giftcard_image', $mimeType);
                $part->asInline();
                $part->setContentId('giftcard_image@plugin');
                $data['attachments'] = [$part];
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function attachRecipientPdfIfEnabled(array $data, GiftCardVoucherEntity $voucher, ?string $salesChannelId, Context $context): array
    {
        $enablePdf = $this->systemConfigService->getBool('ICTECHGiftCard.config.enablePdf', $salesChannelId);
        if ($enablePdf) {
            $pdfPrefix = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfPrefix', $salesChannelId);
            $pdfFilename = ($pdfPrefix !== '' ? $pdfPrefix : 'GIFTCARD-') . $voucher->getCode() . '.pdf';
            try {
                $pdfBinary = $this->generatePdfForVoucher($voucher, $context);
                $data['binAttachments'] = [
                    [
                        'content' => $pdfBinary,
                        'fileName' => $pdfFilename,
                        'mimeType' => 'application/pdf',
                    ],
                ];
            } catch (\Throwable $e) {
                error_log($e->getMessage());
            }
        }
        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function buildGiftCardTemplateData(GiftCardVoucherEntity $voucher, string $recipientName, ?string $salesChannelId, Context $context): array
    {
        $expiresAt = $voucher->getExpiresAt();
        $cardImgHtml = $this->buildCardImageHtml($voucher, $salesChannelId, 'email', $context);

        return [
            'voucher_code'   => $voucher->getCode(),
            'amount'         => \number_format($voucher->getOriginalAmount(), 2),
            'recipient_name' => $recipientName,
            'sender_name'    => $voucher->getSenderName() ?? '',
            'message'        => $voucher->getPersonalMessage() ?? '',
            'validity_date'  => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'       => $this->getShopUrl($salesChannelId, $context),
            'card_image'     => $cardImgHtml,
        ];
    }

    // -------------------------------------------------------------------------
    // Print delivery — uses pdfContent config template, sends as HTML attachment
    // -------------------------------------------------------------------------

    private function sendPrintEmail(
        GiftCardVoucherEntity $voucher,
        string $recipientEmail,
        string $recipientName,
        ?string $salesChannelId,
        Context $context,
    ): void {
        $pdfContent = $this->buildPdfContent($voucher, $salesChannelId, 'email', $context);
        if ($pdfContent === '') {
            return;
        }

        $shopName  = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        $expiresAt = $voucher->getExpiresAt();

        $template = $this->loadMailTemplate(\ICTECHGiftCard\ICTECHGiftCard::MAIL_TYPE_RECIPIENT, $context);
        if ($template !== null) {
            $subject = $template->getTranslation('subject') ?: 'Your Gift Card';
        } else {
            $subject = 'Your Gift Card';
        }

        $data = [
            'salesChannelId' => $salesChannelId,
            'subject'        => $subject,
            'senderName'     => $shopName !== '' ? $shopName : 'Gift Card',
            'recipients'     => [$recipientEmail => $recipientName],
            'contentHtml'    => $pdfContent,
            'contentPlain'   => \sprintf(
                "Your gift card code: %s\nValid until: %s\n\nShopping at: %s",
                $voucher->getCode(),
                $expiresAt?->format('d.m.Y') ?? '',
                $this->getShopUrl($salesChannelId)
            ),
        ];

        $data = $this->attachInlineTemplateImage($data, $voucher, $context);
        $templateData = $this->buildGiftCardTemplateData($voucher, $recipientName, $salesChannelId, $context);
        $this->mailService->send($data, $context, $templateData);
    }


    private function buildCardImageHtml(GiftCardVoucherEntity $voucher, ?string $salesChannelId, string $mode, ?Context $context = null): string
    {
        $media = $this->getTemplateMedia($voucher, $context);
        if ($media === null) {
            return '';
        }

        $url = $media->getUrl();
        if ($url === '') {
            return '';
        }

        if (\str_starts_with($url, '/')) {
            $url = \rtrim($this->getShopUrl($salesChannelId, $context), '/') . $url;
        }

        $dimensions = $this->getCardImageDimensions($salesChannelId, $mode);
        $imgUrl = $this->getCardImageUrl($media, $url, $mode);

        return '<img src="' . \htmlspecialchars($imgUrl, \ENT_QUOTES) . '" width="' . $dimensions['width'] . '" height="' . $dimensions['height'] . '" alt="Gift Card" style="display:block;margin:0 auto;max-width:100%;height:auto;">';
    }

    private function getTemplateMedia(GiftCardVoucherEntity $voucher, ?Context $context = null): ?\Shopware\Core\Content\Media\MediaEntity
    {
        $rawTemplateId = $this->getVoucherTemplateId($voucher);
        if ($rawTemplateId === null) {
            return null;
        }

        $criteria = new Criteria([$rawTemplateId]);
        $criteria->addAssociation('media');
        $template = $this->templateRepository->search($criteria, $context ?? Context::createDefaultContext())->first();
        if ($template === null) {
            return null;
        }

        $media = $template->get('media');
        return $media instanceof \Shopware\Core\Content\Media\MediaEntity ? $media : null;
    }

    private function getVoucherTemplateId(GiftCardVoucherEntity $voucher): ?string
    {
        $customFields = $voucher->getCustomFields() ?? [];
        $rawTemplateId = $customFields['giftCardTemplateId'] ?? $voucher->getTemplateId();

        return \is_string($rawTemplateId) && $rawTemplateId !== '' ? $rawTemplateId : null;
    }

    /**
     * @return array{width: int, height: int}
     */
    private function getCardImageDimensions(?string $salesChannelId, string $mode): array
    {
        $configKey = $mode === 'pdf' ? 'pdfCardWidth' : 'emailCardWidth';
        $configKeyH = $mode === 'pdf' ? 'pdfCardHeight' : 'emailCardHeight';
        $width = (int) ($this->systemConfigService->get(self::CONFIG . $configKey, $salesChannelId) ?? 300);
        $height = (int) ($this->systemConfigService->get(self::CONFIG . $configKeyH, $salesChannelId) ?? 192);

        return ['width' => $width, 'height' => $height];
    }

    private function getCardImageUrl(\Shopware\Core\Content\Media\MediaEntity $media, string $url, string $mode): string
    {
        if ($mode === 'email_print') {
            return $url;
        }

        $relativePath = $media->getPath();

        if ($this->publicFilesystem->has($relativePath)) {
            if ($mode === 'pdf') {
                $mimeType = $media->getMimeType() ?: 'image/png';
                $content = $this->publicFilesystem->read($relativePath);
                return 'data:' . $mimeType . ';base64,' . base64_encode($content);
            }
            return 'cid:giftcard_image@plugin';
        }

        return $url;
    }

    private function getPurchaserSubject(?string $salesChannelId): string
    {
        return 'Your gift card purchase';
    }

    private function getPurchaserConfirmationHtml(): string
    {
        return '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2>🎁 Gift Card Purchase Confirmation</h2>
                <p>Hi {{ purchaser_name }},</p>
                <p>Thank you for purchasing a gift card of <strong>€{{ amount }}</strong> for {{ recipient_name }}.</p>
                <p>It is scheduled to be sent to {{ recipient_email }} on {{ send_date }}.</p>
                <p>Gift Card Code: {{ voucher_code }}</p>
                <p>Valid until {{ validity_date }}</p>
                <p><a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">Visit our Shop</a></p>
            </div>
        ';
    }

    private function getPurchaserConfirmationPlain(): string
    {
        return "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card of €{{ amount }} for {{ recipient_name }}.\n\nIt is scheduled to be sent to {{ recipient_email }} on {{ send_date }}.\n\nGift Card Code: {{ voucher_code }}\nValid until: {{ validity_date }}\n";
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function attachPdfIfEnabled(array $data, GiftCardVoucherEntity $voucher, ?string $salesChannelId, Context $context): array
    {
        $enablePdf = $this->systemConfigService->getBool('ICTECHGiftCard.config.enablePdf', $salesChannelId);
        if ($enablePdf) {
            $pdfPrefix = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfPrefix', $salesChannelId);
            $pdfFilename = ($pdfPrefix !== '' ? $pdfPrefix : 'GIFTCARD-') . $voucher->getCode() . '.pdf';
            try {
                $pdfBinary = $this->generatePdfForVoucher($voucher, $context);
                $data['binAttachments'] = [
                    [
                        'content' => $pdfBinary,
                        'fileName' => $pdfFilename,
                        'mimeType' => 'application/pdf',
                    ],
                ];
            } catch (\Throwable $e) {
                error_log($e->getMessage());
            }
        }
        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function buildPurchaserConfirmationTemplateData(
        GiftCardVoucherEntity $voucher,
        string $purchaserName,
        ?string $salesChannelId,
        Context $context,
    ): array {
        $expiresAt = $voucher->getExpiresAt();
        return [
            'purchaser_name'  => $purchaserName,
            'voucher_code'    => $voucher->getCode(),
            'amount'          => number_format($voucher->getOriginalAmount(), 2),
            'recipient_name'  => $voucher->getRecipientName() ?? '',
            'recipient_email' => $voucher->getRecipientEmail() ?? '',
            'send_date'       => $voucher->getScheduledSendDate()?->format('d.m.Y') ?? '',
            'validity_date'   => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'        => $this->getShopUrl($salesChannelId, $context),
        ];
    }

    private function getPurchaserSelfSubject(?string $salesChannelId): string
    {
        return 'Your gift card';
    }

    private function getDefaultSelfHtml(): string
    {
        return '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2>🎁 Your Gift Card</h2>
                <p>Hi {{ purchaser_name }},</p>
                <p>Thank you for purchasing a gift card. Here are your gift card details:</p>
                <div style="background:#f5f5f5;padding:20px;text-align:center;border-radius:8px;margin:24px 0;">
                    <p style="margin:0;font-size:12px;color:#999;">Your voucher code</p>
                    <p style="margin:8px 0;font-size:28px;font-weight:bold;letter-spacing:4px;color:#333;">{{ voucher_code }}</p>
                    <p style="margin:0;font-size:12px;color:#999;">Amount: <strong>€{{ amount }}</strong></p>
                    <p style="margin:0;font-size:12px;color:#999;">Valid until {{ validity_date }}</p>
                </div>
                <p>Redemption details: Enter the voucher code in the shopping cart before checking out to redeem it.</p>
                <p><a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">Shop Now</a></p>
            </div>
        ';
    }

    private function getDefaultSelfPlain(): string
    {
        return "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card. Here are your details:\n\nYour voucher code: {{ voucher_code }}\nAmount: €{{ amount }}\nValid until: {{ validity_date }}\n\nRedemption details: Enter the code in the shopping cart before checking out.\n";
    }

    /**
     * @return array<string, string>
     */
    private function buildPurchaserSelfTemplateData(
        GiftCardVoucherEntity $voucher,
        string $purchaserName,
        ?string $salesChannelId,
        Context $context,
    ): array {
        $expiresAt = $voucher->getExpiresAt();
        return [
            'purchaser_name'  => $purchaserName,
            'voucher_code'    => $voucher->getCode(),
            'amount'          => number_format($voucher->getOriginalAmount(), 2),
            'validity_date'   => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'        => $this->getShopUrl($salesChannelId, $context),
        ];
    }

    private function loadMailTemplate(string $technicalName, Context $context): ?\Shopware\Core\Framework\DataAbstractionLayer\Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $context)->first();
    }

    /**
     * @return array{subject: string, contentHtml: string, contentPlain: string}
     */
    private function loadSelfMailTemplateData(?string $salesChannelId, Context $context): array
    {
        $template = $this->loadMailTemplate(\ICTECHGiftCard\ICTECHGiftCard::MAIL_TYPE_PURCHASER_SELF, $context);
        if ($template === null) {
            return [
                'subject' => $this->getPurchaserSelfSubject($salesChannelId),
                'contentHtml' => $this->getDefaultSelfHtml(),
                'contentPlain' => $this->getDefaultSelfPlain(),
            ];
        }

        return [
            'subject' => $this->getTranslationString($template->getTranslation('subject'), 'Your Gift Card'),
            'contentHtml' => $this->getTranslationString($template->getTranslation('contentHtml'), $this->getDefaultSelfHtml()),
            'contentPlain' => $this->getTranslationString($template->getTranslation('contentPlain'), $this->getDefaultSelfPlain()),
        ];
    }

    /**
     * @return array{subject: string, contentHtml: string, contentPlain: string}
     */
    private function loadConfirmationMailTemplateData(?string $salesChannelId, Context $context): array
    {
        $template = $this->loadMailTemplate(\ICTECHGiftCard\ICTECHGiftCard::MAIL_TYPE_PURCHASER_CONFIRMATION, $context);
        if ($template === null) {
            return [
                'subject' => $this->getPurchaserSubject($salesChannelId),
                'contentHtml' => $this->getPurchaserConfirmationHtml(),
                'contentPlain' => $this->getPurchaserConfirmationPlain(),
            ];
        }

        return [
            'subject' => $this->getTranslationString($template->getTranslation('subject'), 'Gift Card Purchase Confirmation'),
            'contentHtml' => $this->getTranslationString($template->getTranslation('contentHtml'), $this->getPurchaserConfirmationHtml()),
            'contentPlain' => $this->getTranslationString($template->getTranslation('contentPlain'), $this->getPurchaserConfirmationPlain()),
        ];
    }

    private function getTranslationString(mixed $value, string $fallback): string
    {
        if (! \is_string($value) || $value === '') {
            return $fallback;
        }

        return $value;
    }

    private function getDefaultSalesChannelId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->setLimit(1);

        return $this->salesChannelRepository->searchIds($criteria, $context)->firstId();
    }

    private function getShopUrl(?string $salesChannelId, ?Context $context = null): string
    {
        if ($context === null) {
            $context = Context::createDefaultContext();
        }

        if (!$salesChannelId) {
            return $this->getFallbackUrl($salesChannelId);
        }

        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('domains');
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        if ($salesChannel instanceof \Shopware\Core\System\SalesChannel\SalesChannelEntity) {
            $domainUrl = $this->resolveDomainUrl($salesChannel);
            if ($domainUrl !== null) {
                return $domainUrl;
            }
        }

        return $this->getFallbackUrl($salesChannelId);
    }

    private function getShopName(?string $salesChannelId): string
    {
        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        return $shopName !== '' ? $shopName : 'Our Shop';
    }

    private function resolveVoucherCurrency(GiftCardVoucherEntity $voucher, ?Context $context): GiftCardVoucherEntity
    {
        $currency = $voucher->get('currency');
        if ($currency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
            return $voucher;
        }

        $voucherCriteria = new Criteria([$voucher->getId()]);
        $voucherCriteria->addAssociation('currency');
        $reloaded = $this->voucherRepository->search($voucherCriteria, $context ?? Context::createDefaultContext())->first();
        if ($reloaded instanceof GiftCardVoucherEntity) {
            return $reloaded;
        }

        return $voucher;
    }

    private function getCurrencySymbol(GiftCardVoucherEntity $voucher): string
    {
        $currency = $voucher->get('currency');
        if ($currency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
            return $currency->getSymbol();
        }

        return '€';
    }

    private function getFallbackUrl(?string $salesChannelId): string
    {
        $url = $this->systemConfigService->getString('core.basicInformation.shopUrl', $salesChannelId);
        return $url !== '' ? \rtrim($url, '/') : '';
    }

    private function resolveDomainUrl(\Shopware\Core\System\SalesChannel\SalesChannelEntity $salesChannel): ?string
    {
        $domainCollection = $salesChannel->getDomains();
        if (!$domainCollection || $domainCollection->count() === 0) {
            return null;
        }

        $systemDomain = $this->findSystemDomain($domainCollection);
        if ($systemDomain !== null) {
            return \rtrim((string) $systemDomain->getUrl(), '/');
        }

        $firstDomain = $domainCollection->first();
        return $firstDomain ? \rtrim((string) $firstDomain->getUrl(), '/') : null;
    }

    private function findSystemDomain(\Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection $domains): ?\Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity
    {
        foreach ($domains as $domain) {
            if ($domain->getLanguageId() === Defaults::LANGUAGE_SYSTEM) {
                return $domain;
            }
        }
        return null;
    }

    private function getDefaultHtmlTemplate(): string
    {
        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
    <h2>🎁 Your Gift Card</h2>
    <p>Hi {{ recipient_name }},</p>
    <p>{{ sender_name }} has sent you a gift card worth <strong>€{{ amount }}</strong>!</p>
    {% if message %}
    <blockquote style="border-left:4px solid #57D9A3;padding-left:16px;color:#555;">
        {{ message }}
    </blockquote>
    {% endif %}
    <div style="text-align:center;margin:20px 0;">
        {{ card_image|raw }}
    </div>
    <div style="background:#f5f5f5;padding:20px;text-align:center;border-radius:8px;margin:24px 0;">
        <p style="margin:0;font-size:12px;color:#999;">Your voucher code</p>
        <p style="margin:8px 0;font-size:28px;font-weight:bold;letter-spacing:4px;color:#333;">{{ voucher_code }}</p>
        <p style="margin:0;font-size:12px;color:#999;">Valid until {{ validity_date }}</p>
    </div>
    <p>
        <a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">
            Shop Now
        </a>
    </p>
    <p style="font-size:12px;color:#999;">
        To redeem, enter the voucher code at checkout. The amount will be deducted from your cart total.
        Any remaining balance can be used in future purchases until {{ validity_date }}.
    </p>
</div>
HTML;
    }

    private function getDefaultPlainTemplate(): string
    {
        return <<<TEXT
Your Gift Card

Hi {{ recipient_name }},

{{ sender_name }} has sent you a gift card worth €{{ amount }}!

{% if message %}
Message: {{ message }}
{% endif %}

Your voucher code: {{ voucher_code }}
Valid until: {{ validity_date }}

Shop at: {{ shop_url }}

To redeem, enter the code at checkout.
TEXT;
    }
}
