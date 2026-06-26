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
        $pdfContent = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfContent', $salesChannelId);
        if ($pdfContent === '') {
            return '';
        }

        if (\str_contains($pdfContent, 'border-collapse:collapse;') && \str_contains($pdfContent, 'width:25%') && \str_contains($pdfContent, 'width:33%')) {
            $pdfContent = $this->getCleanDesignerTemplate();
        }

        $replacements = $this->getPdfReplacements($voucher, $salesChannelId, $mode, $context);
        $html = \str_replace(\array_keys($replacements), \array_values($replacements), $pdfContent);

        if ($mode === 'email') {
            return '<div style="background-color:#f8f9fa;padding:20px 0;width:100%;min-height:100%;">'
                . '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;border:1px solid #dee2e6;border-radius:8px;background:#ffffff;box-shadow:0 4px 12px rgba(0,0,0,0.05);">'
                . $html
                . '</div></div>';
        }

        return $html;
    }

    private function getCleanDesignerTemplate(): string
    {
        return <<<HTML
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-family:Arial,sans-serif;color:#333;background:#ffffff;font-size:14px;border-collapse:collapse;margin:0 auto;text-align:center;">
  <tbody>
    <tr>
      <td align="center" style="padding: 10px 0;">
        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;border:1px solid #333;text-align:center;">
          <tr>
            <td style="font-size:30px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;padding:12px 30px;">Gift Card</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding-top:20px;">
        <p style="text-align:center;margin:5px 0;font-size:16px;">Hi {{card_lastname}},</p>
        <p style="text-align:center;margin:5px 0;font-size:16px;">You have received a <strong>{{card_price}}</strong> gift card from {{card_from}}!</p>
        <p style="font-size:18px;margin:10px 0 0 0;text-align:center;color:#555;"><em>Good shopping on {{shop_name}}!</em></p>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:20px 0;">
        <div style="text-align:center;display:block;margin:0 auto;width:100%;">{{card_image}}</div>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:10px 0;">
        <table cellpadding="0" cellspacing="0" border="0" width="280" style="margin:0 auto;background-color:#333;color:#fff;text-align:center;padding:15px;border-radius:4px;">
          <tr>
            <td align="center">
              <span style="font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:5px;">Your code:</span>
              <strong style="font-size:18px;letter-spacing:1px;display:block;">{{card_code}}</strong>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding-top:20px;">
        <p style="text-align:center;margin:5px 0;font-size:14px;color:#666;"><strong>Message from {{card_from}}</strong></p>
        <div style="text-align:center;margin:10px auto;max-width:400px;font-style:italic;color:#555;line-height:1.5;">{{card_message}}</div>
      </td>
    </tr>
    <tr>
      <td style="font-size:1px;padding:10px 0;">&nbsp;</td>
    </tr>
    <tr>
      <td align="center" style="padding:10px 0;">
        <div style="width:100px;border-top:1px solid #ddd;margin:0 auto;"></div>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding-top:15px;">
        <p style="font-size:15px;text-align:center;margin:5px 0;color:#333;"><strong>To take advantage of the gift card</strong></p>
        <p style="text-align:center;margin:5px 0;color:#777;font-size:13px;">Copy/paste your code <strong>{{card_code}}</strong> into the shopping cart before checking out.</p>
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    public function sendPurchaserConfirmationEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $subject = $this->getPurchaserSubject($salesChannelId);
        $senderNameValShop = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $this->getPurchaserConfirmationHtml(),
            'contentPlain'    => $this->getPurchaserConfirmationPlain(),
        ];

        $data = $this->attachPdfIfEnabled($data, $voucher, $salesChannelId, $context);
        $templateData = $this->buildPurchaserConfirmationTemplateData($voucher, $purchaserName, $salesChannelId, $context);

        $this->mailService->send($data, $context, $templateData);
    }

    public function sendPurchaserSelfEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $subject = $this->getPurchaserSelfSubject($salesChannelId);
        $senderNameValShop = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);

        $pdfContentHtml = $this->buildPdfContent($voucher, $salesChannelId, 'email', $context);

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $pdfContentHtml !== '' ? $pdfContentHtml : $this->getDefaultSelfHtml(),
            'contentPlain'    => $this->getDefaultSelfPlain(),
        ];

        $data = $this->attachInlineTemplateImage($data, $voucher, $context);
        $data = $this->attachPdfIfEnabled($data, $voucher, $salesChannelId, $context);
        $templateData = $this->buildPurchaserSelfTemplateData($voucher, $purchaserName, $salesChannelId, $context);

        $this->mailService->send($data, $context, $templateData);

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

        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        if ($shopName === '') {
            $shopName = 'Our Shop';
        }
        $expiresAt = $voucher->getExpiresAt();

        $currency = $voucher->get('currency');
        if (! $currency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
            $voucherCriteria = new Criteria([$voucher->getId()]);
            $voucherCriteria->addAssociation('currency');
            $reloaded = $this->voucherRepository->search($voucherCriteria, $context)->first();
            $currency = $reloaded?->get('currency');
        }

        $currencySymbol = '€';
        if ($currency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
            $currencySymbol = $currency->getSymbol();
        }

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

        return $dompdf->output();
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
        $template = $this->loadMailTemplate($context);
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

        $subjectFormat = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectRecipient', $salesChannelId);
        if ($subjectFormat === '') {
            $subjectFormat = 'Gift card offer from %s';
        }
        $subject = \sprintf($subjectFormat, $voucher->getSenderName() ?? '');

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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function addInlineMediaDataPart(array $data, \Shopware\Core\Content\Media\MediaEntity $media): array
    {
        $relativePath = $media->getPath();
        $projectDir = dirname(__DIR__, 6);
        $publicDir = \rtrim($projectDir, '/') . '/public/';
        $localPath = $publicDir . $relativePath;

        if (\file_exists($localPath)) {
            $mimeType = $media->getMimeType();
            if ($mimeType === null || $mimeType === '') {
                $mimeType = 'image/png';
            }
            $fileHandle = fopen($localPath, 'r');
            if ($fileHandle !== false) {
                $part = new \Symfony\Component\Mime\Part\DataPart($fileHandle, 'giftcard_image', $mimeType);
                $part->asInline();
                $part->setContentId('giftcard_image@plugin');
                $data['attachments'] = [$part];
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
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
        $subject = \sprintf(
            (function () use ($salesChannelId): string {
                $fmt = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectRecipient', $salesChannelId);
                return $fmt !== '' ? $fmt : 'Your Gift Card';
            })(),
            $voucher->getSenderName() ?? $shopName
        );

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
        $this->mailService->send($data, $context, []);
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function getPdfReplacements(GiftCardVoucherEntity $voucher, ?string $salesChannelId, string $mode = 'pdf', ?Context $context = null): array
    {
        $expiresAt = $voucher->getExpiresAt();
        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        $cardImgHtml = $this->buildCardImageHtml($voucher, $salesChannelId, $mode, $context);

        return [
            '{{card_lastname}}' => \htmlspecialchars($voucher->getRecipientName() ?? '', \ENT_QUOTES),
            '{{card_price}}'    => \htmlspecialchars(\number_format($voucher->getOriginalAmount(), 2), \ENT_QUOTES),
            '{{card_from}}'     => \htmlspecialchars($voucher->getSenderName() ?? '', \ENT_QUOTES),
            '{{card_code}}'     => \htmlspecialchars($voucher->getCode(), \ENT_QUOTES),
            '{{card_message}}'  => \nl2br(\htmlspecialchars($voucher->getPersonalMessage() ?? '', \ENT_QUOTES)),
            '{{card_image}}'    => $cardImgHtml,
            '{{shop_name}}'     => \htmlspecialchars($shopName, \ENT_QUOTES),
            '{{validity_date}}' => \htmlspecialchars($expiresAt?->format('d.m.Y') ?? '', \ENT_QUOTES),
            '{{shop_url}}'      => \htmlspecialchars($this->getShopUrl($salesChannelId, $context), \ENT_QUOTES),
        ];
    }

    private function buildCardImageHtml(GiftCardVoucherEntity $voucher, ?string $salesChannelId, string $mode, ?Context $context = null): string
    {
        $media = $this->getTemplateMedia($voucher);
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

    private function getTemplateMedia(GiftCardVoucherEntity $voucher): ?\Shopware\Core\Content\Media\MediaEntity
    {
        $rawTemplateId = $this->getVoucherTemplateId($voucher);
        if ($rawTemplateId === null) {
            return null;
        }

        $criteria = new Criteria([$rawTemplateId]);
        $criteria->addAssociation('media');
        $template = $this->templateRepository->search($criteria, Context::createDefaultContext())->first();
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
        $relativePath = $media->getPath();
        $projectDir = dirname(__DIR__, 6);
        $publicDir = \rtrim($projectDir, '/') . '/public/';
        $localPath = $publicDir . $relativePath;

        if (\file_exists($localPath)) {
            return $mode === 'pdf' ? $localPath : 'cid:giftcard_image@plugin';
        }

        return $url;
    }

    private function getPurchaserSubject(?string $salesChannelId): string
    {
        $subject = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectPurchaser', $salesChannelId);
        return $subject !== '' ? $subject : 'Your gift card purchase';
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
        $subject = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectPurchaser', $salesChannelId);
        return $subject !== '' ? $subject : 'Your gift card';
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

    private function loadMailTemplate(Context $context): ?\Shopware\Core\Framework\DataAbstractionLayer\Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', 'ictech_gift_card'));
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $context)->first();
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
