<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Administration\Controller;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\AuditLogAction;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use ICTECHGiftCard\Core\Service\GiftCardEmailService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class ModerationController
{
    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity>> $voucherRepository
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>> $auditLogRepository
     */
    public function __construct(
        private readonly EntityRepository $voucherRepository,
        private readonly EntityRepository $auditLogRepository,
        private readonly GiftCardEmailService $giftCardEmailService,
    ) {
    }

    #[Route(
        path: '/api/ictech-gift-card/moderation/resend',
        name: 'api.ictech_gift_card.moderation.resend',
        methods: ['POST']
    )]
    public function resend(Request $request, Context $context): JsonResponse
    {
        $voucherId = $request->request->get('voucherId');
        if (!\is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        $criteria = new Criteria([$voucherId]);
        $criteria->addAssociation('currency');
        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        $recipientEmail = $request->request->get('recipientEmail');
        if (\is_string($recipientEmail) && \trim($recipientEmail) !== '') {
            $cleanedEmail = \trim($recipientEmail);
            $this->voucherRepository->update([[
                'id' => $voucher->getId(),
                'recipientEmail' => $cleanedEmail,
            ]], $context);
            $voucher->setRecipientEmail($cleanedEmail);
        }

        $this->giftCardEmailService->sendRecipientEmail($voucher, $context);

        $adminUserId = null;
        $source = $context->getSource();
        if ($source instanceof AdminApiSource) {
            $adminUserId = $source->getUserId();
        }

        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucher->getId(),
            'adminUserId' => $adminUserId,
            'action' => AuditLogAction::ManualResend->value,
            'oldValue' => null,
            'newValue' => null,
            'reason' => 'Manually resent to ' . ($voucher->getRecipientEmail() ?? ''),
        ]], $context);

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/api/ictech-gift-card/moderation/revoke',
        name: 'api.ictech_gift_card.moderation.revoke',
        methods: ['POST']
    )]
    public function revoke(Request $request, Context $context): JsonResponse
    {
        $voucherId = $request->request->get('voucherId');
        $reason = $request->request->get('reason');

        if (!\is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        if (!\is_string($reason) || \trim($reason) === '') {
            return new JsonResponse(['error' => 'reason is required'], 400);
        }

        $criteria = new Criteria([$voucherId]);
        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        $oldStatus = $voucher->getStatus()->value;

        $this->voucherRepository->update([[
            'id' => $voucher->getId(),
            'status' => VoucherStatus::Canceled->value,
        ]], $context);

        $adminUserId = null;
        $source = $context->getSource();
        if ($source instanceof AdminApiSource) {
            $adminUserId = $source->getUserId();
        }

        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucher->getId(),
            'adminUserId' => $adminUserId,
            'action' => AuditLogAction::Revoke->value,
            'oldValue' => $oldStatus,
            'newValue' => VoucherStatus::Canceled->value,
            'reason' => $reason,
        ]], $context);

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/api/ictech-gift-card/moderation/adjust-balance',
        name: 'api.ictech_gift_card.moderation.adjust_balance',
        methods: ['POST']
    )]
    public function adjustBalance(Request $request, Context $context): JsonResponse
    {
        $voucherId = $request->request->get('voucherId');
        $newBalanceVal = $request->request->get('newBalance');
        $reason = $request->request->get('reason');

        if (!is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        if ($newBalanceVal === null || $newBalanceVal === '') {
            return new JsonResponse(['error' => 'newBalance is required'], 400);
        }

        // Cast to float for calculations
        $newBalance = (float) $newBalanceVal;

        // Validate balance is not negative
        if ($newBalance < 0) {
            return new JsonResponse(['error' => 'Balance cannot be negative'], 400);
        }

        $criteria = new Criteria([$voucherId]);
        /** @var GiftCardVoucherEntity|null $voucher */
        $voucher = $this->voucherRepository->search($criteria, $context)->first();

        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        $originalAmount = $voucher->getOriginalAmount();
        // Validate new balance does not exceed original amount
        if ($newBalance > $originalAmount) {
            return new JsonResponse(['error' => 'New balance cannot exceed original amount'], 400);
        }

        $oldBalance = $voucher->getRemainingBalance();
        $oldStatus = $voucher->getStatus();

        $updateData = [
            'id' => $voucher->getId(),
            'remainingBalance' => $newBalance,
        ];

        // Determine status based on new balance compared to original amount
        if ($oldStatus !== VoucherStatus::Canceled && $oldStatus !== VoucherStatus::WaitingValidOrder) {
            if ($newBalance <= 0.0) {
                $updateData['status'] = VoucherStatus::Used->value;
            } elseif ($newBalance < $originalAmount) {
                $updateData['status'] = VoucherStatus::PartiallyUsed->value;
            } else {
                $updateData['status'] = VoucherStatus::Unused->value;
            }
        }

        $this->voucherRepository->update([$updateData], $context);

        $adminUserId = null;
        $source = $context->getSource();
        if ($source instanceof AdminApiSource) {
            $adminUserId = $source->getUserId();
        }

        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucher->getId(),
            'adminUserId' => $adminUserId,
            'action' => AuditLogAction::BalanceAdjust->value,
            'oldValue' => (string) $oldBalance,
            'newValue' => (string) $newBalance,
            'reason' => is_string($reason) ? $reason : 'Balance adjusted manually',
        ]], $context);

        return new JsonResponse(['success' => true]);
    }
}
