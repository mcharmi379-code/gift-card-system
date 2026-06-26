<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\ScheduledTask;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use ICTECHGiftCard\Core\Service\GiftCardEmailService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ICTECHGiftCardEmailTask::class)]
final class ICTECHGiftCardEmailTaskHandler extends ScheduledTaskHandler
{
    private LoggerInterface $logger;

    /** @var EntityRepository<GiftCardVoucherCollection> */
    private readonly EntityRepository $voucherRepository;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<GiftCardVoucherCollection> $voucherRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        EntityRepository $voucherRepository,
        private readonly GiftCardEmailService $emailService,
        private readonly \Doctrine\DBAL\Connection $connection,
    ) {
        $this->logger = $logger;
        $this->voucherRepository = $voucherRepository;
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $today   = (new \DateTimeImmutable())->format('Y-m-d');

        // 1. Mark expired vouchers as 'expired'
        try {
            $this->connection->executeStatement(
                "UPDATE `ictech_gift_card_voucher`
                 SET `status` = :expiredStatus, `updated_at` = :now
                 WHERE `expires_at` < :today
                   AND `status` NOT IN (:usedStatus, :canceledStatus, :expiredStatus, :waitingValidOrderStatus)",
                [
                    'expiredStatus' => VoucherStatus::Expired->value,
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.000'),
                    'today' => $today,
                    'usedStatus' => VoucherStatus::Used->value,
                    'canceledStatus' => VoucherStatus::Canceled->value,
                    'waitingValidOrderStatus' => VoucherStatus::WaitingValidOrder->value,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to update expired gift card vouchers: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }

        // 2. Process pending scheduled emails
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('status', VoucherStatus::Unused->value),
            new RangeFilter('scheduledSendDate', [RangeFilter::LTE => $today]),
            new EqualsFilter('sentAt', null),
        ]));
        $criteria->setLimit(100);

        $vouchers = $this->voucherRepository->search($criteria, $context);

        foreach ($vouchers->getElements() as $voucher) {
            /** @var GiftCardVoucherEntity $voucher */
            if ($voucher->getRecipientEmail() === null) {
                continue;
            }

            try {
                $this->emailService->sendForVoucher($voucher, $context);
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf('GiftCard email failed for voucher %s: %s', $voucher->getId(), $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }
}
