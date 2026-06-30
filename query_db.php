<?php

declare(strict_types=1);

$logFile = '/var/www/html/sw6.6.10.8/var/log/dev.log';
$handle = fopen($logFile, 'r');
$today = '2026-06-25';
$lines = [];
while (($line = fgets($handle)) !== false) {
    if (str_contains($line, $today)) {
        if (str_contains($line, 'php.INFO') || str_contains($line, 'Deprecated') || str_contains($line, 'User Deprecated')) {
            continue;
        }
        if (str_contains($line, 'mail') || str_contains($line, 'Mail') || str_contains($line, 'failed') || str_contains($line, 'Exception') || str_contains($line, 'CRITICAL') || str_contains($line, 'ERROR') || str_contains($line, 'mailer')) {
            $lines[] = $line;
        }
    }
}
fclose($handle);

file_put_contents('/var/www/html/sw6.6.10.8/custom/plugins/ICTECHGiftCard/log_out.txt', implode("", $lines));
echo "Saved " . count($lines) . " lines\n";
