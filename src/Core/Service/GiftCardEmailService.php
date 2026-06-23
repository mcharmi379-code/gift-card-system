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
    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $mailTemplateRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $voucherRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $salesChannelRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity>> $templateRepository
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

        $template = $this->loadMailTemplate($context);
        if ($template === null) {
            return;
        }

        $salesChannelId = $this->getDefaultSalesChannelId($context);

        $templateVars = $template->getVars();
        $contentHtml  = \is_string($templateVars['contentHtml'] ?? null) ? $templateVars['contentHtml'] : '';
        $contentPlain = \is_string($templateVars['contentPlain'] ?? null) ? $templateVars['contentPlain'] : '';
        
        $subjectFormat = $this->systemConfigService->getString('ICTECHGiftCard.config.emailSubjectRecipient', $salesChannelId) ?: 'Gift card offer from %s';
        $senderNameVal = $voucher->getSenderName() ?? '';
        $subject = \sprintf($subjectFormat, $senderNameVal);

        $senderNameValShop = $this->systemConfigService->getString(
            'core.basicInformation.shopName',
            $salesChannelId
        );

        $expiresAt = $voucher->getExpiresAt();

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$recipientEmail => $recipientName],
            'contentHtml'     => $contentHtml,
            'contentPlain'    => $contentPlain,
        ];

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
                    ]
                ];
            } catch (\Throwable $e) {
                // Fail silently to not block mail sending if PDF generation has issues
            }
        }

        $templateData = [
            'voucher_code'    => $voucher->getCode(),
            'amount'          => number_format($voucher->getOriginalAmount(), 2),
            'recipient_name'  => $recipientName,
            'sender_name'     => $voucher->getSenderName() ?? '',
            'message'         => $voucher->getPersonalMessage() ?? '',
            'validity_date'   => $expiresAt?->format('d.m.Y') ?? '',
            'shop_url'        => $this->getShopUrl($salesChannelId),
        ];

        $this->mailService->send($data, $context, $templateData);

        // Mark voucher as sent
        $this->voucherRepository->update([[
            'id'     => $voucher->getId(),
            'sentAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
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
                    ]
                ];
            } catch (\Throwable $e) {
                // Fail silently to not block mail sending if PDF generation has issues
            }
        }

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

        $contentHtml = '
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

        $contentPlain = "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card. Here are your details:\n\nYour voucher code: {{ voucher_code }}\nAmount: €{{ amount }}\nValid until: {{ validity_date }}\n\nRedemption details: Enter the code in the shopping cart before checking out.\n";

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderNameValShop !== '' ? $senderNameValShop : 'Gift Card',
            'recipients'      => [$purchaserEmail => $purchaserName],
            'contentHtml'     => $contentHtml,
            'contentPlain'    => $contentPlain,
        ];

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
                    ]
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
        
        if (empty($html)) {
            $html = '<html><body>Gift Card Code: {{card_code}}</body></html>';
        }

        $customFields = $voucher->getCustomFields() ?? [];
        $templateId = $customFields['giftCardTemplateId'] ?? null;
        $cardImage = '';

        if ($templateId !== null && $templateId !== '') {
            $criteria = new Criteria([$templateId]);
            $criteria->addAssociation('media');
            $template = $this->templateRepository->search($criteria, $context)->first();
            if ($template !== null && $template->getMedia() !== null) {
                $media = $template->getMedia();
                $mode = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfImageSourceMode', $salesChannelId) ?: 'http';
                $w = $this->systemConfigService->getInt('ICTECHGiftCard.config.pdfCardWidth', $salesChannelId) ?: 300;
                $h = $this->systemConfigService->getInt('ICTECHGiftCard.config.pdfCardHeight', $salesChannelId) ?: 192;
                
                $imgUrl = '';
                if ($mode === 'local') {
                    $relativePath = $media->getPath();
                    $publicDir = '/var/www/html/sw6.6.10.8/public/';
                    $localPath = $publicDir . $relativePath;
                    if (\file_exists($localPath)) {
                        $imgUrl = $localPath;
                    } else {
                        $imgUrl = $media->getUrl();
                    }
                } else {
                    $imgUrl = $media->getUrl();
                }
                
                $cardImage = '<img src="' . \htmlspecialchars($imgUrl) . '" width="' . $w . '" height="' . $h . '" alt="Gift Card" style="max-width:100%">';
            }
        }

        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId) ?: 'Our Shop';
        $expiresAt = $voucher->getExpiresAt();
        
        $currencySymbol = '€';
        if ($voucher->getCurrency() !== null) {
            $currencySymbol = $voucher->getCurrency()->getSymbol();
        } else {
            // Reload voucher with currency association to get the symbol
            $voucherCriteria = new Criteria([$voucher->getId()]);
            $voucherCriteria->addAssociation('currency');
            $reloaded = $this->voucherRepository->search($voucherCriteria, $context)->first();
            if ($reloaded !== null && $reloaded->getCurrency() !== null) {
                $currencySymbol = $reloaded->getCurrency()->getSymbol();
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

        $result = $this->mailTemplateRepository->search($criteria, $context);

        return $result->first();
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
}
