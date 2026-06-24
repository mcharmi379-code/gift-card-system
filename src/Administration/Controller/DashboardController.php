<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Administration\Controller;

use Doctrine\DBAL\Connection;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class DashboardController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/stats',
        name: 'api.ictech_gift_card.dashboard.stats',
        methods: ['GET']
    )]
    public function stats(Context $context): JsonResponse
    {
        $rows = $this->connection->fetchAllKeyValue(
            "SELECT status, COUNT(*) FROM ictech_gift_card_voucher GROUP BY status"
        );

        $rawTotal = $this->connection->fetchOne("SELECT COUNT(*) FROM ictech_gift_card_voucher");
        $total = is_numeric($rawTotal) ? (int) $rawTotal : 0;

        $rawTotalSold = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(original_amount), 0) FROM ictech_gift_card_voucher WHERE status != 'waiting_valid_order'"
        );
        $totalSold = is_numeric($rawTotalSold) ? (float) $rawTotalSold : 0.0;

        $rawTotalRedeemed = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount_used), 0) FROM ictech_gift_card_transaction"
        );
        $totalRedeemed = is_numeric($rawTotalRedeemed) ? (float) $rawTotalRedeemed : 0.0;

        $now = (new \DateTimeImmutable())->format('Y-m-d');

        $rawExpired = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM ictech_gift_card_voucher WHERE expires_at < :now AND status NOT IN ('used','canceled')",
            ['now' => $now]
        );
        $expired = is_numeric($rawExpired) ? (int) $rawExpired : 0;

        $rawPending = $rows['waiting_valid_order'] ?? 0;
        $pending = is_numeric($rawPending) ? (int) $rawPending : 0;

        return new JsonResponse([
            'total'          => $total,
            'totalSold'      => $totalSold,
            'totalRedeemed'  => $totalRedeemed,
            'byStatus'       => $rows,
            'expired'        => $expired,
            'pending'        => $pending,
        ]);
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/purchased-export',
        name: 'api.ictech_gift_card.dashboard.purchased_export',
        methods: ['GET']
    )]
    public function purchasedExport(Request $request, Context $context): StreamedResponse
    {
        $status  = $request->query->get('status');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo   = $request->query->get('dateTo');

        $where  = [];
        $params = [];

        if ($status) {
            $where[]          = 'v.status = :status';
            $params['status'] = $status;
        }

        if ($dateFrom) {
            $where[]            = 'v.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $where[]          = 'v.created_at <= :dateTo';
            $params['dateTo'] = $dateTo . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    v.code,
                    v.original_amount,
                    v.remaining_balance,
                    v.status,
                    v.recipient_name,
                    v.recipient_email,
                    v.sender_name,
                    v.expires_at,
                    v.created_at,
                    o.order_number
                FROM ictech_gift_card_voucher v
                LEFT JOIN `order` o ON o.id = v.order_id AND o.version_id = v.order_version_id
                {$whereClause}
                ORDER BY v.created_at DESC";

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return $this->streamCsv(
            'gift-cards-purchased.csv',
            ['Code', 'Original Amount', 'Remaining Balance', 'Status', 'Recipient Name', 'Recipient Email', 'Sender Name', 'Expires At', 'Created At', 'Order Number'],
            $rows,
            ['code', 'original_amount', 'remaining_balance', 'status', 'recipient_name', 'recipient_email', 'sender_name', 'expires_at', 'created_at', 'order_number']
        );
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/used-export',
        name: 'api.ictech_gift_card.dashboard.used_export',
        methods: ['GET']
    )]
    public function usedExport(Request $request, Context $context): StreamedResponse
    {
        $dateFrom = $request->query->get('dateFrom');
        $dateTo   = $request->query->get('dateTo');

        $where  = [];
        $params = [];

        if ($dateFrom) {
            $where[]            = 't.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $where[]          = 't.created_at <= :dateTo';
            $params['dateTo'] = $dateTo . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    v.code,
                    t.amount_used,
                    t.balance_before,
                    t.balance_after,
                    t.created_at,
                    o.order_number,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                    c.email AS customer_email
                FROM ictech_gift_card_transaction t
                INNER JOIN ictech_gift_card_voucher v ON v.id = t.voucher_id
                LEFT JOIN `order` o ON o.id = t.order_id AND o.version_id = t.order_version_id
                LEFT JOIN customer c ON c.id = t.customer_id
                {$whereClause}
                ORDER BY t.created_at DESC";

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return $this->streamCsv(
            'gift-cards-used.csv',
            ['Code', 'Amount Used', 'Balance Before', 'Balance After', 'Used At', 'Order Number', 'Customer Name', 'Customer Email'],
            $rows,
            ['code', 'amount_used', 'balance_before', 'balance_after', 'created_at', 'order_number', 'customer_name', 'customer_email']
        );
    }

    #[Route(
        path: '/api/ictech-gift-card/preview-pdf',
        name: 'api.ictech_gift_card.preview_pdf',
        methods: ['GET']
    )]
    public function previewPdf(Request $request, Context $context): Response
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;
        $html = $this->systemConfigService->getString('ICTECHGiftCard.config.pdfContent', $salesChannelId);

        if (empty($html)) {
            return new Response('No PDF content configured.', Response::HTTP_BAD_REQUEST);
        }

        $sampleImageHtml = '<img src="https://placehold.co/300x192/cccccc/333333?text=Gift+Card" style="max-width:300px;height:auto;" />';

        $replacements = [
            '{{card_lastname}}'  => 'Doe',
            '{{card_firstname}}' => 'John',
            '{{card_price}}'     => '50.00 €',
            '{{card_from}}'      => 'Jane Doe',
            '{{card_code}}'      => 'PREVIEW-1234-5678',
            '{{card_message}}'   => 'Happy Birthday! Enjoy your gift.',
            '{{card_image}}'     => $sampleImageHtml,
            '{{shop_name}}'      => 'My Shop',
            '{{validity_date}}'  => date('d.m.Y', strtotime('+1 year')),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gift-card-preview.pdf"',
        ]);
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, mixed>> $rows
     * @param list<string> $keys
     */
    private function streamCsv(string $filename, array $headers, array $rows, array $keys): StreamedResponse
    {
        return new StreamedResponse(static function () use ($headers, $rows, $keys): void {
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
                    $line[] = is_scalar($value) || $value === null ? $value : null;
                }
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, Response::HTTP_OK, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
