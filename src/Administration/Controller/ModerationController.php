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
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection> $voucherRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardAuditLog\GiftCardAuditLogCollection> $auditLogRepository
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
        if (! \is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        $voucher = $this->getVoucherWithCurrency($voucherId, $context);
        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        $this->updateRecipientEmailIfNeeded($voucher, $request->request->get('recipientEmail'), $context);
        $this->giftCardEmailService->sendRecipientEmail($voucher, $context);
        $this->logManualResend($voucher->getId(), $voucher->getRecipientEmail() ?? '', $context);

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/api/ictech-gift-card/moderation/revoke',
        name: 'api.ictech_gift_card.moderation.revoke',
        methods: ['POST']
    )]
    public function revoke(Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validateRevokeRequest($request);
        if ($validationError !== null) {
            return $validationError;
        }

        $voucherId = (string) $request->request->get('voucherId');
        $voucher = $this->getVoucher($voucherId, $context);
        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        $oldStatus = $voucher->getStatus()->value;
        $this->updateVoucherStatus($voucher->getId(), VoucherStatus::Canceled->value, $context);
        $this->logRevocation($voucher->getId(), $oldStatus, (string) $request->request->get('reason'), $context);

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        path: '/api/ictech-gift-card/moderation/adjust-balance',
        name: 'api.ictech_gift_card.moderation.adjust_balance',
        methods: ['POST']
    )]
    public function adjustBalance(Request $request, Context $context): JsonResponse
    {
        $validationError = $this->validateAdjustBalanceRequest($request);
        if ($validationError !== null) {
            return $validationError;
        }

        $voucherId = (string) $request->request->get('voucherId');
        $newBalance = (float) $request->request->get('newBalance');

        $voucher = $this->getVoucher($voucherId, $context);
        if ($voucher === null) {
            return new JsonResponse(['error' => 'Voucher not found'], 404);
        }

        if ($newBalance > $voucher->getOriginalAmount()) {
            return new JsonResponse(['error' => 'New balance cannot exceed original amount'], 400);
        }

        $oldBalance = $voucher->getRemainingBalance();
        $this->performBalanceAdjustment($voucher, $newBalance, $context);
        $this->logBalanceAdjustment($voucher->getId(), $oldBalance, $newBalance, $request->request->get('reason'), $context);

        return new JsonResponse(['success' => true]);
    }

    private function validateRevokeRequest(Request $request): ?JsonResponse
    {
        $voucherId = $request->request->get('voucherId');
        if (! \is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        $reason = $request->request->get('reason');
        if (! \is_string($reason) || \trim($reason) === '') {
            return new JsonResponse(['error' => 'reason is required'], 400);
        }

        return null;
    }

    private function validateAdjustBalanceRequest(Request $request): ?JsonResponse
    {
        $voucherId = $request->request->get('voucherId');
        if (! \is_string($voucherId) || $voucherId === '') {
            return new JsonResponse(['error' => 'voucherId is required'], 400);
        }

        $newBalanceVal = $request->request->get('newBalance');
        if ($newBalanceVal === null || $newBalanceVal === '') {
            return new JsonResponse(['error' => 'newBalance is required'], 400);
        }

        return $this->validateAdjustBalanceValue($newBalanceVal);
    }

    /**
     * @param mixed $newBalanceVal
     */
    private function validateAdjustBalanceValue($newBalanceVal): ?JsonResponse
    {
        if (\is_string($newBalanceVal) || \is_numeric($newBalanceVal)) {
            if ((float) $newBalanceVal < 0.0) {
                return new JsonResponse(['error' => 'Balance cannot be negative'], 400);
            }
        }

        return null;
    }

    private function getVoucher(string $id, Context $context): ?GiftCardVoucherEntity
    {
        return $this->voucherRepository->search(new Criteria([$id]), $context)->first();
    }

    private function getVoucherWithCurrency(string $id, Context $context): ?GiftCardVoucherEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('currency');
        return $this->voucherRepository->search($criteria, $context)->first();
    }

    private function updateRecipientEmailIfNeeded(
        GiftCardVoucherEntity $voucher,
        mixed $recipientEmail,
        Context $context,
    ): void {
        if (\is_string($recipientEmail) && \trim($recipientEmail) !== '') {
            $cleanedEmail = \trim($recipientEmail);
            $this->voucherRepository->update([[
                'id' => $voucher->getId(),
                'recipientEmail' => $cleanedEmail,
            ]], $context);
            $voucher->setRecipientEmail($cleanedEmail);
        }
    }

    private function logManualResend(string $voucherId, string $recipientEmail, Context $context): void
    {
        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucherId,
            'adminUserId' => $this->getAdminUserId($context),
            'action' => AuditLogAction::ManualResend->value,
            'oldValue' => null,
            'newValue' => null,
            'reason' => 'Manually resent to ' . $recipientEmail,
        ]], $context);
    }

    private function updateVoucherStatus(string $voucherId, string $status, Context $context): void
    {
        $this->voucherRepository->update([[
            'id' => $voucherId,
            'status' => $status,
        ]], $context);
    }

    private function logRevocation(string $voucherId, string $oldStatus, string $reason, Context $context): void
    {
        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucherId,
            'adminUserId' => $this->getAdminUserId($context),
            'action' => AuditLogAction::Revoke->value,
            'oldValue' => $oldStatus,
            'newValue' => VoucherStatus::Canceled->value,
            'reason' => $reason,
        ]], $context);
    }

    private function performBalanceAdjustment(
        GiftCardVoucherEntity $voucher,
        float $newBalance,
        Context $context,
    ): void {
        $updateData = [
            'id' => $voucher->getId(),
            'remainingBalance' => $newBalance,
        ];

        $status = $this->determineVoucherStatus($voucher, $newBalance);
        if ($status !== null) {
            $updateData['status'] = $status;
        }

        $this->voucherRepository->update([$updateData], $context);
    }

    private function determineVoucherStatus(GiftCardVoucherEntity $voucher, float $newBalance): ?string
    {
        $oldStatus = $voucher->getStatus();
        if ($oldStatus === VoucherStatus::Canceled || $oldStatus === VoucherStatus::WaitingValidOrder) {
            return null;
        }

        if ($newBalance <= 0.0) {
            return VoucherStatus::Used->value;
        }

        if ($newBalance < $voucher->getOriginalAmount()) {
            return VoucherStatus::PartiallyUsed->value;
        }

        return VoucherStatus::Unused->value;
    }

    private function logBalanceAdjustment(
        string $voucherId,
        float $oldBalance,
        float $newBalance,
        mixed $reason,
        Context $context,
    ): void {
        $this->auditLogRepository->create([[
            'id' => Uuid::randomHex(),
            'voucherId' => $voucherId,
            'adminUserId' => $this->getAdminUserId($context),
            'action' => AuditLogAction::BalanceAdjust->value,
            'oldValue' => (string) $oldBalance,
            'newValue' => (string) $newBalance,
            'reason' => \is_string($reason) ? $reason : 'Balance adjusted manually',
        ]], $context);
    }

    private function getAdminUserId(Context $context): ?string
    {
        $source = $context->getSource();
        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}
