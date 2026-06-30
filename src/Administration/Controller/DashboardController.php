<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Administration\Controller;

use Doctrine\DBAL\Connection;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['api']])]
final class DashboardController
{
    public function __construct(
        private readonly Connection $connection,
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
        /** @var array<string, string|int> $rows */
        $rows = $this->connection->fetchAllKeyValue(
            "SELECT status, COUNT(*) FROM ictech_gift_card_voucher GROUP BY status"
        );

        return new JsonResponse([
            'total' => $this->getStatsCount(
                "SELECT COUNT(*) FROM ictech_gift_card_voucher"
            ),
            'totalSold' => $this->getStatsSum(
                "SELECT COALESCE(SUM(original_amount), 0) " .
                "FROM ictech_gift_card_voucher WHERE status != 'waiting_valid_order'"
            ),
            'totalRedeemed' => $this->getStatsSum(
                "SELECT COALESCE(SUM(amount_used), 0) FROM ictech_gift_card_transaction"
            ),
            'byStatus' => $rows,
            'expired' => $this->getExpiredCount(),
            'pending' => $this->parsePending($rows),
        ]);
    }

    #[Route(
        path: '/api/ictech-gift-card/dashboard/purchased-export',
        name: 'api.ictech_gift_card.dashboard.purchased_export',
        methods: ['GET']
    )]
    public function purchasedExport(Request $request): StreamedResponse
    {
        [$sql, $params] = $this->buildPurchasedExportQuery($request);
        $rows = $this->connection->fetchAllAssociative($sql, $params);

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
        [$sql, $params] = $this->buildUsedExportQuery($request);
        $rows = $this->connection->fetchAllAssociative($sql, $params);

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
                'card_price'    => '50.00 €',
                'card_from'     => 'Jane Doe',
                'card_code'     => 'PREVIEW-1234-5678',
                'card_message'  => 'Happy Birthday! Enjoy your gift.',
                'card_image'    => $sampleImageHtml,
                'shop_name'     => 'My Shop',
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

    private function getStatsCount(string $sql): int
    {
        $raw = $this->connection->fetchOne($sql);
        return is_numeric($raw) ? (int) $raw : 0;
    }

    private function getStatsSum(string $sql): float
    {
        $raw = $this->connection->fetchOne($sql);
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function getExpiredCount(): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d');
        $raw = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM ictech_gift_card_voucher " .
            "WHERE expires_at < :now AND status NOT IN ('used','canceled')",
            ['now' => $now]
        );
        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * @param array<string, string|int> $rows
     */
    private function parsePending(array $rows): int
    {
        $raw = $rows['waiting_valid_order'] ?? 0;
        return is_numeric($raw) ? (int) $raw : 0;
    }

    private function getQueryString(Request $request, string $key): string
    {
        $val = $request->query->get($key);

        return \is_string($val) ? $val : '';
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function getPurchasedFilters(Request $request): array
    {
        $statusStr = $this->getQueryString($request, 'status');
        $dateFromStr = $this->getQueryString($request, 'dateFrom');
        $dateToStr = $this->getQueryString($request, 'dateTo');

        $where = [];
        $params = [];

        if ($statusStr !== '') {
            $where[] = 'v.status = :status';
            $params['status'] = $statusStr;
        }
        if ($dateFromStr !== '') {
            $where[] = 'v.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFromStr;
        }
        if ($dateToStr !== '') {
            $where[] = 'v.created_at <= :dateTo';
            $params['dateTo'] = $dateToStr . ' 23:59:59';
        }

        return [$where, $params];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildPurchasedExportQuery(Request $request): array
    {
        [$where, $params] = $this->getPurchasedFilters($request);
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT v.code, v.original_amount, v.remaining_balance,
            v.status, v.recipient_name, v.recipient_email, v.sender_name,
            v.expires_at, v.created_at, o.order_number
            FROM ictech_gift_card_voucher v
            LEFT JOIN `order` o ON o.id = v.order_id
            AND o.version_id = v.order_version_id
            {$whereClause}
            ORDER BY v.created_at DESC";

        return [$sql, $params];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function getUsedFilters(Request $request): array
    {
        $dateFromStr = $this->getQueryString($request, 'dateFrom');
        $dateToStr = $this->getQueryString($request, 'dateTo');

        $where = [];
        $params = [];

        if ($dateFromStr !== '') {
            $where[] = 't.created_at >= :dateFrom';
            $params['dateFrom'] = $dateFromStr;
        }
        if ($dateToStr !== '') {
            $where[] = 't.created_at <= :dateTo';
            $params['dateTo'] = $dateToStr . ' 23:59:59';
        }

        return [$where, $params];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildUsedExportQuery(Request $request): array
    {
        [$where, $params] = $this->getUsedFilters($request);
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT v.code, t.amount_used, t.balance_before,
            t.balance_after, t.created_at, o.order_number,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            c.email AS customer_email
            FROM ictech_gift_card_transaction t
            INNER JOIN ictech_gift_card_voucher v ON v.id = t.voucher_id
            LEFT JOIN `order` o ON o.id = t.order_id
            AND o.version_id = t.order_version_id
            LEFT JOIN customer c ON c.id = t.customer_id
            {$whereClause}
            ORDER BY t.created_at DESC";

        return [$sql, $params];
    }

<<<<<<< HEAD
    private function generatePreviewPdfOutput(string $html): string
    {
        $sampleImageHtml = '<img src="' .
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAQoAAADBCAYAAADN98fWAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH5gYREg4Zg2W8vAAAADtJREFUeN7t1AEBAAAAwiD7p7bGDlgYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJgbN2kAAWFAmboAAAAASUVORK5CYII=' .
            '" style="max-width:300px;height:auto;" />';
        $replacements = [
            '{{card_lastname}}' => 'Doe',
            '{{card_firstname}}' => 'John',
            '{{card_price}}' => '50.00 €',
            '{{card_from}}' => 'Jane Doe',
            '{{card_code}}' => 'PREVIEW-1234-5678',
            '{{card_message}}' => 'Happy Birthday! Enjoy your gift.',
            '{{card_image}}' => $sampleImageHtml,
            '{{shop_name}}' => 'My Shop',
            '{{validity_date}}' => (new \DateTimeImmutable('+1 year'))->format('d.m.Y'),
        ];

        $html = \str_replace(
            \array_keys($replacements),
            \array_values($replacements),
            $html
        );

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
=======

>>>>>>> 3a3d49b39548f8ee4288cf63f36fa4676fe419e1

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
