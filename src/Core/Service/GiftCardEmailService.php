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
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $mailTemplateRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $voucherRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $salesChannelRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity>> $templateRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $templateRepository
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
        $recipientName  = $voucher->getRecipientName() ?? '';

        if ($recipientEmail === null || $recipientEmail === '') {
            return;
        }

        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $deliveryMethod = $voucher->getDeliveryMethod() ?? 'email';

        if ($deliveryMethod === 'print') {
            $this->sendPrintEmail($voucher, $recipientEmail, $recipientName, $salesChannelId, $context);
        } else {
            $this->sendGiftCardEmail($voucher, $recipientEmail, $recipientName, $salesChannelId, $context);
        }

        $this->voucherRepository->update([[
            'id'     => $voucher->getId(),
            'sentAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
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

        $contentHtml = $template->getTranslation('contentHtml');
        if (!\is_string($contentHtml) || $contentHtml === '') {
            $contentHtml = $this->getDefaultHtmlTemplate();
        }

        $contentPlain = $template->getTranslation('contentPlain');
        if (!\is_string($contentPlain) || $contentPlain === '') {
            $contentPlain = $this->getDefaultPlainTemplate();
        }

        $subjectFormat = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectRecipient', $salesChannelId) ?: 'Gift card offer from %s';
        $senderNameVal = $voucher->getSenderName() ?? '';
        $subject = \sprintf($subjectFormat, $senderNameVal);

        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        $expiresAt  = $voucher->getExpiresAt();
        $cardImgHtml = $this->buildCardImageHtml($voucher, $salesChannelId, 'email');

        $data = [
            'salesChannelId' => $salesChannelId,
            'subject'        => $subject,
            'senderName'     => $shopName !== '' ? $shopName : 'Gift Card',
            'recipients'     => [$recipientEmail => $recipientName],
            'contentHtml'    => $contentHtml,
            'contentPlain'   => $contentPlain,
        ];

        // Attach Inline Template Image for email
        $customFields = $voucher->getCustomFields() ?? [];
        $rawTemplateId = $customFields['giftCardTemplateId'] ?? $voucher->getTemplateId();
        $templateId = \is_string($rawTemplateId) ? $rawTemplateId : null;
        if ($templateId !== null && $templateId !== '') {
            $criteria = new Criteria([$templateId]);
            $criteria->addAssociation('media');
            $template = $this->templateRepository->search($criteria, $context)->first();
            if ($template !== null) {
                $media = $template->get('media');
                if ($media instanceof \Shopware\Core\Content\Media\MediaEntity) {
                    $relativePath = $media->getPath();
                    $projectDir = dirname(__DIR__, 6);
                    $publicDir = \rtrim($projectDir, '/') . '/public/';
                    $localPath = $publicDir . $relativePath;
                    if (\file_exists($localPath)) {
                        $mimeType = $media->getMimeType() ?: 'image/png';
                        $fileHandle = fopen($localPath, 'r');
                        if ($fileHandle !== false) {
                            $part = new \Symfony\Component\Mime\Part\DataPart($fileHandle, 'giftcard_image', $mimeType);
                            $part->asInline();
                            $part->setContentId('giftcard_image@plugin');
                            $data['attachments'] = [$part];
                        }
                    }
                }
            }
        }

        // Attach PDF if enabled
        $enablePdf = $this->systemConfigService->getBool('ICTECHGiftCard.config.enablePdf', $salesChannelId);
        if ($enablePdf) {
            $pdfPrefix = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfPrefix', $salesChannelId) ?: 'GIFTCARD-';
            $pdfFilename = $pdfPrefix . $voucher->getCode() . '.pdf';
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
                // Fail silently to not block mail sending if PDF generation has issues
            }
        }

        $templateData = [
            'voucher_code'   => $voucher->getCode(),
            'amount'         => \number_format($voucher->getOriginalAmount(), 2),
            'recipient_name' => $recipientName,
            'sender_name'    => $voucher->getSenderName() ?? '',
            'message'        => $voucher->getPersonalMessage() ?? '',
            'validity_date'  => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'       => $this->getShopUrl($salesChannelId),
            'card_image'     => $cardImgHtml,
        ];

        $this->mailService->send($data, $context, $templateData);
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
        $pdfContent = $this->buildPdfContent($voucher, $salesChannelId);
        if ($pdfContent === '') {
            return;
        }

        $shopName  = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        $expiresAt = $voucher->getExpiresAt();
        $subject = \sprintf(
            $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectRecipient', $salesChannelId) ?: 'Your Gift Card',
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

        $this->mailService->send($data, $context, []);
    }

    /**
     * Build the pdfContent HTML with all {{variables}} replaced by real values.
     */
    public function buildPdfContent(GiftCardVoucherEntity $voucher, ?string $salesChannelId): string
    {
        $pdfContent = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfContent', $salesChannelId) ?: '';

        if ($pdfContent === '') {
            return '';
        }

        $expiresAt  = $voucher->getExpiresAt();
        $shopName   = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        $cardImgHtml = $this->buildCardImageHtml($voucher, $salesChannelId, 'pdf');

        return \str_replace(
            ['{{card_lastname}}', '{{card_price}}', '{{card_from}}', '{{card_code}}', '{{card_message}}', '{{card_image}}', '{{shop_name}}', '{{validity_date}}'],
            [
                \htmlspecialchars($voucher->getRecipientName() ?? '', \ENT_QUOTES),
                \htmlspecialchars(\number_format($voucher->getOriginalAmount(), 2), \ENT_QUOTES),
                \htmlspecialchars($voucher->getSenderName() ?? '', \ENT_QUOTES),
                \htmlspecialchars($voucher->getCode(), \ENT_QUOTES),
                \nl2br(\htmlspecialchars($voucher->getPersonalMessage() ?? '', \ENT_QUOTES)),
                $cardImgHtml,
                \htmlspecialchars($shopName, \ENT_QUOTES),
                \htmlspecialchars($expiresAt?->format('d.m.Y') ?? '', \ENT_QUOTES),
            ],
            $pdfContent
        );
    }

    private function buildCardImageHtml(GiftCardVoucherEntity $voucher, ?string $salesChannelId, string $mode): string
    {
        $customFields = $voucher->getCustomFields() ?? [];
        $rawTemplateId = $customFields['giftCardTemplateId'] ?? $voucher->getTemplateId();
        $templateId = \is_string($rawTemplateId) ? $rawTemplateId : null;
        if ($templateId === null || $templateId === '') {
            return '';
        }

        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('media');
        $template = $this->templateRepository->search($criteria, Context::createDefaultContext())->first();
        if ($template === null) {
            return '';
        }

        $media = $template->get('media');
        if (!$media instanceof \Shopware\Core\Content\Media\MediaEntity) {
            return '';
        }

        $url = $media->getUrl();
        if ($url === '') {
            return '';
        }

        if (\str_starts_with($url, '/')) {
            $url = \rtrim($this->getShopUrl($salesChannelId), '/') . $url;
        }

        $configKey = $mode === 'pdf' ? 'pdfCardWidth' : 'emailCardWidth';
        $configKeyH = $mode === 'pdf' ? 'pdfCardHeight' : 'emailCardHeight';
        $w = (int) ($this->systemConfigService->get(self::CONFIG . $configKey, $salesChannelId) ?? 300);
        $h = (int) ($this->systemConfigService->get(self::CONFIG . $configKeyH, $salesChannelId) ?? 192);

        $relativePath = $media->getPath();
        $projectDir   = dirname(__DIR__, 6);
        $publicDir    = \rtrim($projectDir, '/') . '/public/';
        $localPath    = $publicDir . $relativePath;

        $imgUrl = $url;
        if (\file_exists($localPath)) {
            if ($mode === 'pdf') {
                $imgUrl = $localPath;
            } else {
                $imgUrl = 'cid:giftcard_image@plugin';
            }
        }

        // ── Read customization overlay stored by the admin template editor ──
        $templateCustomFields = $template->get('customFields') ?? [];
        if (!is_array($templateCustomFields)) {
            $templateCustomFields = [];
        }
        $customize = $templateCustomFields['giftCardTemplateCustomize'] ?? [];
        if (!is_array($customize)) {
            $customize = [];
        }

        // Design elements (textOne/textTwo/textThree/colorOne) come from the admin template.
        // Price and Discount Code are ALWAYS the real purchase values from the voucher entity.
        $rawTextOne   = $customize['textOne']   ?? '';
        $rawTextTwo   = $customize['textTwo']   ?? '';
        $rawTextThree = $customize['textThree'] ?? '';
        $rawColorOne  = $customize['colorOne']  ?? '#ffffff';
        $textOne      = \htmlspecialchars(\is_string($rawTextOne)   ? $rawTextOne   : '', \ENT_QUOTES);
        $textTwo      = \htmlspecialchars(\is_string($rawTextTwo)   ? $rawTextTwo   : '', \ENT_QUOTES);
        $textThree    = \htmlspecialchars(\is_string($rawTextThree) ? $rawTextThree : '', \ENT_QUOTES);
        $colorOne     = \htmlspecialchars(\is_string($rawColorOne)  ? $rawColorOne  : '#ffffff', \ENT_QUOTES);

        // Dynamic values from the actual purchase
        $price        = \htmlspecialchars(\number_format($voucher->getOriginalAmount(), 2), \ENT_QUOTES);
        $discountCode = \htmlspecialchars($voucher->getCode(), \ENT_QUOTES);

        // Percentage-based font sizes relative to card width
        $priceFontSize    = (int) \round($w * 0.10);   // ~10% of width
        $headlineFontSize = (int) \round($w * 0.075);  // ~7.5% of width
        $brandFontSize    = (int) \round($w * 0.045);  // ~4.5% of width
        $codeFontSize     = (int) \round($w * 0.038);  // ~3.8% of width

        // Build the overlay — price and discountCode are always shown (they always have real values)
        $headlineBlock = '';
        if ($textTwo !== '' || $textThree !== '') {
            $headlineBlock = '
                <div style="position:absolute;top:8%;left:6%;max-width:42%;
                            font-size:' . $headlineFontSize . 'px;line-height:1.08;font-weight:700;
                            text-transform:uppercase;color:' . $colorOne . ';">
                    ' . ($textTwo   !== '' ? '<strong style="display:block;">' . $textTwo   . '</strong>' : '') . '
                    ' . ($textThree !== '' ? '<strong style="display:block;">' . $textThree . '</strong>' : '') . '
                </div>';
        }

        $priceBlock = '
            <b style="position:absolute;top:5%;right:5%;max-width:28%;
                       font-size:' . $priceFontSize . 'px;line-height:1;font-weight:700;
                       text-align:right;color:' . $colorOne . ';">
                ' . $price . '
            </b>';

        $brandBlock = '';
        if ($textOne !== '') {
            $brandBlock = '
                <span style="position:absolute;bottom:16%;right:6%;max-width:40%;
                              font-size:' . $brandFontSize . 'px;font-weight:700;
                              text-align:right;color:' . $colorOne . ';">
                    ' . $textOne . '
                </span>';
        }

        $codeBlock = '
            <em style="position:absolute;bottom:7%;right:6%;max-width:40%;
                        font-size:' . $codeFontSize . 'px;font-weight:700;font-style:normal;
                        text-align:right;color:' . $colorOne . ';">
                ' . $discountCode . '
            </em>';

        $overlayHtml = $headlineBlock . $priceBlock . $brandBlock . $codeBlock;

        // Wrap image + overlay in a relative container
        return '
            <div style="position:relative;display:inline-block;width:' . $w . 'px;height:' . $h . 'px;overflow:hidden;vertical-align:top;">
                <img src="' . \htmlspecialchars($imgUrl, \ENT_QUOTES) . '"
                     width="' . $w . '" height="' . $h . '"
                     alt="Gift Card"
                     style="display:block;width:100%;height:100%;object-fit:cover;">
                ' . $overlayHtml . '
            </div>';
    }

    public function sendPurchaserConfirmationEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);

        $subject = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectPurchaser', $salesChannelId) ?: 'Your gift card purchase';

        $senderNameValShop = $this->systemConfigService->getString(
            'core.basicInformation.shopName',
            $salesChannelId
        );

        $expiresAt = $voucher->getExpiresAt();

        $contentHtml = '
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

        $contentPlain = "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card of €{{ amount }} for {{ recipient_name }}.\n\nIt is scheduled to be sent to {{ recipient_email }} on {{ send_date }}.\n\nGift Card Code: {{ voucher_code }}\nValid until: {{ validity_date }}\n";

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $contentHtml,
            'contentPlain'    => $contentPlain,
        ];

        $templateData = [
            'purchaser_name'  => $purchaserName,
            'voucher_code'    => $voucher->getCode(),
            'amount'          => number_format($voucher->getOriginalAmount(), 2),
            'recipient_name'  => $voucher->getRecipientName() ?? '',
            'recipient_email' => $voucher->getRecipientEmail() ?? '',
            'send_date'       => $voucher->getScheduledSendDate()?->format('d.m.Y') ?? '',
            'validity_date'   => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'        => $this->getShopUrl($salesChannelId),
        ];

        $this->mailService->send($data, $context, $templateData);
    }

    public function sendPurchaserSelfEmail(
        GiftCardVoucherEntity $voucher,
        string $purchaserEmail,
        string $purchaserName,
        Context $context,
    ): void {
        $salesChannelId = $this->getDefaultSalesChannelId($context);

        $subject = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectPurchaser', $salesChannelId) ?: 'Your gift card';

        $senderNameValShop = $this->systemConfigService->getString(
            'core.basicInformation.shopName',
            $salesChannelId
        );

        $expiresAt = $voucher->getExpiresAt();

        $cardImgHtml = $this->buildCardImageHtml($voucher, $salesChannelId, 'email');

        $contentHtml = '
            <div style="font-family: \'Outfit\', \'Inter\', Arial, sans-serif; max-width: 600px; margin: auto; padding: 24px; border: 1px solid #e1e8ed; border-radius: 16px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div style="text-align: center; margin-bottom: 24px;">
                    <span style="font-size: 40px;">🎁</span>
                    <h2 style="margin: 12px 0 6px; font-size: 24px; color: #1e293b; font-weight: 700;">Your Gift Card is Ready!</h2>
                    <p style="margin: 0; font-size: 14px; color: #64748b;">Print at home or use it online</p>
                </div>
                
                <p style="font-size: 16px; color: #334155; line-height: 1.6;">Hi {{ purchaser_name }},</p>
                <p style="font-size: 16px; color: #334155; line-height: 1.6;">Thank you for your purchase! Your gift card has been generated. Below are your card details. We have also attached a printable PDF version of your gift card to this email.</p>
                
                <div style="text-align: center; margin: 24px 0;">
                    {{ card_image|raw }}
                </div>

                <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 24px; text-align: center; border-radius: 12px; border: 1px solid #e2e8f0; margin: 24px 0;">
                    <p style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; font-weight: 600;">Your Voucher Code</p>
                    <p style="margin: 0 0 12px; font-size: 28px; font-weight: 800; letter-spacing: 4px; color: #0f172a; font-family: monospace;">{{ voucher_code }}</p>
                    <p style="margin: 0; font-size: 16px; color: #334155; font-weight: 600;">Value: <span style="color: #10b981;">€{{ amount }}</span></p>
                    <p style="margin: 8px 0 0; font-size: 12px; color: #94a3b8;">Valid until {{ validity_date }}</p>
                </div>

                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <p style="font-size: 14px; color: #64748b; line-height: 1.5; margin: 0 0 16px;">
                        <strong>How to redeem:</strong> Simply enter the voucher code <strong>{{ voucher_code }}</strong> in the shopping cart before proceeding to checkout. The card value will be deducted from your total.
                    </p>
                    <div style="text-align: center;">
                        <a href="{{ shop_url }}" style="background: #10b981; color: #ffffff; padding: 12px 32px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; font-size: 15px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">
                            Shop Now
                        </a>
                    </div>
                </div>
            </div>
        ';

        $contentPlain = "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card. Here are your details:\n\nYour voucher code: {{ voucher_code }}\nAmount: €{{ amount }}\nValid until: {{ validity_date }}\n\nRedemption details: Enter the code in the shopping cart before checking out.\n";

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $contentHtml,
            'contentPlain'    => $contentPlain,
        ];

        // Attach Inline Template Image for email
        $customFields = $voucher->getCustomFields() ?? [];
        $rawTemplateId = $customFields['giftCardTemplateId'] ?? $voucher->getTemplateId();
        $templateId = \is_string($rawTemplateId) ? $rawTemplateId : null;
        if ($templateId !== null && $templateId !== '') {
            $criteria = new Criteria([$templateId]);
            $criteria->addAssociation('media');
            $template = $this->templateRepository->search($criteria, $context)->first();
            if ($template !== null) {
                $media = $template->get('media');
                if ($media instanceof \Shopware\Core\Content\Media\MediaEntity) {
                    $relativePath = $media->getPath();
                    $projectDir = dirname(__DIR__, 6);
                    $publicDir = \rtrim($projectDir, '/') . '/public/';
                    $localPath = $publicDir . $relativePath;
                    if (\file_exists($localPath)) {
                        $mimeType = $media->getMimeType() ?: 'image/png';
                        $fileHandle = fopen($localPath, 'r');
                        if ($fileHandle !== false) {
                            $part = new \Symfony\Component\Mime\Part\DataPart($fileHandle, 'giftcard_image', $mimeType);
                            $part->asInline();
                            $part->setContentId('giftcard_image@plugin');
                            $data['attachments'] = [$part];
                        }
                    }
                }
            }
        }

        // Attach PDF if enabled
        $enablePdf = $this->systemConfigService->getBool('ICTECHGiftCard.config.enablePdf', $salesChannelId);
        if ($enablePdf) {
            $pdfPrefix = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfPrefix', $salesChannelId) ?: 'GIFTCARD-';
            $pdfFilename = $pdfPrefix . $voucher->getCode() . '.pdf';
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
                // Fail silently to not block mail sending if PDF generation has issues
            }
        }

        $templateData = [
            'purchaser_name'  => $purchaserName,
            'voucher_code'    => $voucher->getCode(),
            'amount'          => number_format($voucher->getOriginalAmount(), 2),
            'validity_date'   => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'        => $this->getShopUrl($salesChannelId),
            'card_image'      => $cardImgHtml,
        ];

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

        $cardImage = $this->buildCardImageHtml($voucher, $salesChannelId, 'pdf');

        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId) ?: 'Our Shop';
        $expiresAt = $voucher->getExpiresAt();

        $currencySymbol = '€';
        $currency = $voucher->get('currency');
        if ($currency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
            $currencySymbol = $currency->getSymbol();
        } else {
            // Reload voucher with currency association to get the symbol
            $voucherCriteria = new Criteria([$voucher->getId()]);
            $voucherCriteria->addAssociation('currency');
            $reloaded = $this->voucherRepository->search($voucherCriteria, $context)->first();
            $reloadedCurrency = $reloaded?->get('currency');
            if ($reloadedCurrency instanceof \Shopware\Core\System\Currency\CurrencyEntity) {
                $currencySymbol = $reloadedCurrency->getSymbol();
            }
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

    private function getShopUrl(?string $salesChannelId): string
    {
        return $this->systemConfigService->getString('core.basicInformation.shopUrl', $salesChannelId);
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
