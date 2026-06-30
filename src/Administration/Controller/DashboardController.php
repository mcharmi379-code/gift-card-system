<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Administration\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['api']])]
final class DashboardController
{
    /**
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection> $voucherRepository
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $voucherRepository,
        private readonly EntityRepository $transactionRepository,
        private readonly Environment $twig,
    ) {
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/stats',
        name: 'api.ictech_gift_card.dashboard.stats',
        methods: ['GET']
    )]
    public function stats(): JsonResponse
    {
        $context = Context::createDefaultContext();

        // 1. Group by status
        $criteriaStatus = new Criteria();
        $criteriaStatus->addAggregation(new TermsAggregation('statuses', 'status'));
        $resultStatus = $this->voucherRepository->aggregate($criteriaStatus, $context);
        $statusesAgg = $resultStatus->get('statuses');
        $byStatus = [];
        if ($statusesAgg instanceof TermsResult) {
            foreach ($statusesAgg->getBuckets() as $bucket) {
                $byStatus[$bucket->getKey()] = $bucket->getCount();
            }
        }

        // 2. Total count
        $criteriaTotal = new Criteria();
        $total = $this->voucherRepository->search($criteriaTotal, $context)->getTotal();

        // 3. Total sold
        $criteriaSold = new Criteria();
        $criteriaSold->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('status', 'waiting_valid_order'),
        ]));
        $criteriaSold->addAggregation(new SumAggregation('sumOriginalAmount', 'originalAmount'));
        $resultSold = $this->voucherRepository->aggregate($criteriaSold, $context);
        $sumSoldAgg = $resultSold->get('sumOriginalAmount');
        $totalSold = $sumSoldAgg instanceof SumResult ? (float) $sumSoldAgg->getSum() : 0.0;

        // 4. Total redeemed
        $criteriaRedeemed = new Criteria();
        $criteriaRedeemed->addAggregation(new SumAggregation('sumAmountUsed', 'amountUsed'));
        $resultRedeemed = $this->transactionRepository->aggregate($criteriaRedeemed, $context);
        $sumRedeemedAgg = $resultRedeemed->get('sumAmountUsed');
        $totalRedeemed = $sumRedeemedAgg instanceof SumResult ? (float) $sumRedeemedAgg->getSum() : 0.0;

        // 5. Expired count
        $now = (new \DateTimeImmutable())->format('Y-m-d');
        $criteriaExpired = new Criteria();
        $criteriaExpired->addFilter(new RangeFilter('expiresAt', [RangeFilter::LT => $now]));
        $criteriaExpired->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsAnyFilter('status', ['used', 'canceled']),
        ]));
        $expired = $this->voucherRepository->search($criteriaExpired, $context)->getTotal();

        // 6. Pending
        $pending = $byStatus['waiting_valid_order'] ?? 0;

        return new JsonResponse([
            'total' => $total,
            'totalSold' => $totalSold,
            'totalRedeemed' => $totalRedeemed,
            'byStatus' => $byStatus,
            'expired' => $expired,
            'pending' => $pending,
        ]);
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/purchased-export',
        name: 'api.ictech_gift_card.dashboard.purchased_export',
        methods: ['GET']
    )]
    public function purchasedExport(Request $request): StreamedResponse
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $statusStr = $this->getQueryString($request, 'status');
        $dateFromStr = $this->getQueryString($request, 'dateFrom');
        $dateToStr = $this->getQueryString($request, 'dateTo');

        if ($statusStr !== '') {
            $criteria->addFilter(new EqualsFilter('status', $statusStr));
        }
        if ($dateFromStr !== '') {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GTE => $dateFromStr]));
        }
        if ($dateToStr !== '') {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $dateToStr . ' 23:59:59']));
        }

        $vouchers = $this->voucherRepository->search($criteria, $context)->getEntities();

        $rows = [];
        foreach ($vouchers as $voucher) {
            /** @var \ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity $voucher */
            $rows[] = $this->formatVoucherRow($voucher);
        }

        return $this->streamCsv(
            'gift-cards-purchased.csv',
            [
                'Code',
                'Original Amount',
                'Remaining Balance',
                'Status',
                'Recipient Name',
                'Recipient Email',
                'Sender Name',
                'Expires At',
                'Created At',
                'Order Number',
            ],
            $rows,
            [
                'code',
                'original_amount',
                'remaining_balance',
                'status',
                'recipient_name',
                'recipient_email',
                'sender_name',
                'expires_at',
                'created_at',
                'order_number',
            ]
        );
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/used-export',
        name: 'api.ictech_gift_card.dashboard.used_export',
        methods: ['GET']
    )]
    public function usedExport(Request $request): StreamedResponse
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('voucher');
        $criteria->addAssociation('order');
        $criteria->addAssociation('customer');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $dateFromStr = $this->getQueryString($request, 'dateFrom');
        $dateToStr = $this->getQueryString($request, 'dateTo');

        if ($dateFromStr !== '') {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GTE => $dateFromStr]));
        }
        if ($dateToStr !== '') {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $dateToStr . ' 23:59:59']));
        }

        $transactions = $this->transactionRepository->search($criteria, $context)->getEntities();

        $rows = [];
        foreach ($transactions as $transaction) {
            /** @var \ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionEntity $transaction */
            $rows[] = $this->formatTransactionRow($transaction);
        }

        return $this->streamCsv(
            'gift-cards-used.csv',
            [
                'Code',
                'Amount Used',
                'Balance Before',
                'Balance After',
                'Used At',
                'Order Number',
                'Customer Name',
                'Customer Email',
            ],
            $rows,
            [
                'code',
                'amount_used',
                'balance_before',
                'balance_after',
                'created_at',
                'order_number',
                'customer_name',
                'customer_email',
            ]
        );
    }

    #[Route(
        path: '/api/ictech-gift-card/preview-pdf',
        name: 'api.ictech_gift_card.preview_pdf',
        methods: ['GET']
    )]
    public function previewPdf(Request $request): Response
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== ''
            ? $salesChannelId
            : null;

        $sampleImageHtml = '<img src="' .
            'https://placehold.co/300x192/cccccc/333333?text=Gift+Card' .
            '" style="max-width:300px;height:auto;" />';

        try {
            $html = $this->twig->render('@ICTECHGiftCard/documents/gift_card_pdf.html.twig', [
                'card_lastname' => 'Doe',
                'card_price' => '50.00 €',
                'card_from' => 'Jane Doe',
                'card_code' => 'PREVIEW-1234-5678',
                'card_message' => 'Happy Birthday! Enjoy your gift.',
                'card_image' => $sampleImageHtml,
                'shop_name' => 'My Shop',
                'validity_date' => (new \DateTimeImmutable('+1 year'))->format('d.m.Y'),
            ]);
        } catch (\Throwable $e) {
            $html = '<html><body>Gift Card Code: PREVIEW-1234-5678</body></html>';
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-preview.pdf"',
        ]);
    }

    private function getQueryString(Request $request, string $key): string
    {
        $val = $request->query->get($key);

        return \is_string($val) ? $val : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVoucherRow(\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity $voucher): array
    {
        $expiresAt = $voucher->getExpiresAt();
        $createdAt = $voucher->getCreatedAt();
        return [
            'code' => $voucher->getCode(),
            'original_amount' => $voucher->getOriginalAmount(),
            'remaining_balance' => $voucher->getRemainingBalance(),
            'status' => $voucher->getStatus(),
            'recipient_name' => $voucher->getRecipientName(),
            'recipient_email' => $voucher->getRecipientEmail(),
            'sender_name' => $voucher->getSenderName(),
            'expires_at' => $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : '',
            'created_at' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : '',
            'order_number' => $this->getOrderNumber($voucher->getOrder()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransactionRow(\ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionEntity $transaction): array
    {
        $createdAt = $transaction->getCreatedAt();
        [$customerName, $customerEmail] = $this->getCustomerDetails($transaction->getCustomer());

        return [
            'code' => $this->getVoucherCode($transaction->getVoucher()),
            'amount_used' => $transaction->getAmountUsed(),
            'balance_before' => $transaction->getBalanceBefore(),
            'balance_after' => $transaction->getBalanceAfter(),
            'created_at' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : '',
            'order_number' => $this->getOrderNumber($transaction->getOrder()),
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getCustomerDetails(?\Shopware\Core\Checkout\Customer\CustomerEntity $customer): array
    {
        if ($customer === null) {
            return ['', ''];
        }
        return [$customer->getFirstName() . ' ' . $customer->getLastName(), $customer->getEmail()];
    }

    private function getVoucherCode(?\ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity $voucher): string
    {
        return $voucher ? $voucher->getCode() : '';
    }

    private function getOrderNumber(?\Shopware\Core\Checkout\Order\OrderEntity $order): string
    {
        return $order ? (string) $order->getOrderNumber() : '';
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, mixed>> $rows
     * @param list<string> $keys
     */
    private function streamCsv(
        string $filename,
        array $headers,
        array $rows,
        array $keys,
    ): StreamedResponse {
        return new StreamedResponse(
            static function () use ($headers, $rows, $keys): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    /** @var list<bool|float|int|string|null> $line */
                    $line = [];
                    foreach ($keys as $key) {
                        $value = $row[$key] ?? null;
                        $line[] = is_scalar($value) || $value === null
                            ? $value
                            : null;
                    }
                    fputcsv($handle, $line);
                }
                fclose($handle);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $filename
                ),
            ]
        );
    }
}
