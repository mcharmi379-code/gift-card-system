<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\MessageHandler;

use ICTECHGiftCard\Core\Message\SendGiftCardMailMessage;
use ICTECHGiftCard\Core\Service\GiftCardEmailService;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendGiftCardMailHandler
{
    /**
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     */
    public function __construct(
        private readonly EntityRepository $voucherRepository,
        private readonly GiftCardEmailService $emailService,
    ) {
    }

    public function __invoke(SendGiftCardMailMessage $message): void
    {
        $context = new Context(
            new SystemSource(),
            [],
            $message->getCurrencyId(),
            [$message->getLanguageId()]
        );

        $voucher = $this->voucherRepository->search(new Criteria([$message->getVoucherId()]), $context)->first();
        if ($voucher === null) {
            return;
        }

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        if ($message->getDeliveryMethod() === 'print') {
            $this->emailService->sendPurchaserSelfEmail($voucher, $message->getPurchaserEmail(), $message->getPurchaserName(), $context);
            return;
        }

        $this->emailService->sendPurchaserConfirmationEmail($voucher, $message->getPurchaserEmail(), $message->getPurchaserName(), $context);
        if ($message->getScheduledSendDate() <= $today && $message->getRecipientEmail() !== '') {
            $this->emailService->sendRecipientEmail($voucher, $context);
        }
    }
}
