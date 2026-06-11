<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Service;

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
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $voucherRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function sendForVoucher(GiftCardVoucherEntity $voucher, Context $context): void
    {
        $recipientEmail = $voucher->getRecipientEmail();
        $recipientName  = $voucher->getRecipientName() ?? '';

        if ($recipientEmail === null) {
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
        $subject      = \is_string($templateVars['subject'] ?? null) ? $templateVars['subject'] : 'Your Gift Card';

        $senderName = $this->systemConfigService->getString(
            'core.basicInformation.shopName',
            $salesChannelId
        );

        $expiresAt = $voucher->getExpiresAt();

        $data = [
            'salesChannelId'  => $salesChannelId,
            'subject'         => $subject,
            'senderName'      => $senderName !== '' ? $senderName : 'Gift Card',
            'recipients'      => [$recipientEmail => $recipientName],
            'contentHtml'     => $contentHtml,
            'contentPlain'    => $contentPlain,
        ];

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
