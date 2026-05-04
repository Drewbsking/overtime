<?php

$outputPath = __DIR__ . '/../overtime-app-summary.pdf';

$sections = [
    [
        'title' => 'What It Is',
        'items' => [
            'A PHP 8.1+ web portal for managing overtime requests and showing an overtime equalization board. The repo implements authentication, request workflows, admin user management, CSV-backed equalization reporting, and email notifications.',
        ],
    ],
    [
        'title' => 'Who It Is For',
        'items' => [
            'Primary persona: internal staff who submit overtime, plus approvers/admins who review requests and manage accounts.',
        ],
    ],
    [
        'title' => 'What It Does',
        'items' => [
            'Admin-only account creation with temporary passwords and forced first-login reset.',
            'Lets signed-in users submit up to 10 overtime dates at once with hours, reason, and office/field work type.',
            'Shows each user their request history; admins can view all requests.',
            'Gives approvers/admins a pending review queue to approve or deny requests with decision notes.',
            'Sends email notifications on submission and on approval/denial to the requester and opted-in approvers/admins.',
            'Displays a CSV-driven equalization board with stats, rankings, and file/as-of metadata.',
        ],
    ],
    [
        'title' => 'How It Works',
        'items' => [
            'HTTP entry points live under public/ and all bootstrap through bootstrap.php, which loads env vars, sessions, helpers, DB, auth, mailer, and overtime services.',
            'Business data is stored in MySQL tables defined in database/schema.sql: users, overtime_requests, and request_events.',
            'Route handlers call Auth, Overtime, DB, and Mailer classes in src/; HTML is rendered with PHP templates and Bootstrap from jsDelivr.',
            'The equalization page separately reads a CSV file via src/equalization_sheet.php; the page states those hours are not connected to overtime requests.',
        ],
    ],
    [
        'title' => 'How To Run',
        'items' => [
            'Run composer install.',
            'Create .env from .env.example and set DB, SMTP, and APP_KEY values.',
            'Import database/schema.sql into MySQL.',
            'Create the first admin with: php bin/create_admin.php <username> <email>.',
            'Serve the app with public/ as the web root; specific local web-server command: Not found in repo.',
        ],
    ],
];

function pdfEscape(string $text): string
{
    return str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', '', ''],
        $text
    );
}

function wrapText(string $text, int $maxChars): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (strlen($candidate) <= $maxChars) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $lines[] = $current;
        }
        $current = $word;
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

$pageWidth = 612;
$pageHeight = 792;
$left = 42;
$right = 42;
$y = 752;
$titleSize = 18;
$headingSize = 11;
$bodySize = 9;
$lineGap = 11;
$sectionGap = 6;
$wrapChars = 92;
$bulletIndent = 14;
$content = [];

$content[] = 'BT';
$content[] = '/F2 ' . $titleSize . ' Tf';
$content[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $left, $y, pdfEscape('Overtime App Summary'));
$y -= 18;
$content[] = '/F1 8 Tf';
$content[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $left, $y, pdfEscape('Evidence source: README.md, public/*.php, src/*.php, database/schema.sql'));
$y -= 18;

foreach ($sections as $section) {
    $content[] = '/F2 ' . $headingSize . ' Tf';
    $content[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $left, $y, pdfEscape($section['title']));
    $y -= 13;

    foreach ($section['items'] as $item) {
        $isBullet = $section['title'] !== 'What It Is' && $section['title'] !== 'Who It Is For';
        $prefix = $isBullet ? '- ' : '';
        $lines = wrapText($item, $isBullet ? $wrapChars - 4 : $wrapChars);

        foreach ($lines as $index => $line) {
            $x = $left + ($isBullet ? $bulletIndent : 0);
            $text = $index === 0 ? $prefix . $line : $line;
            $content[] = '/F1 ' . $bodySize . ' Tf';
            $content[] = sprintf('1 0 0 1 %d %d Tm (%s) Tj', $x, $y, pdfEscape($text));
            $y -= $lineGap;
        }
    }

    $y -= $sectionGap;
}

$content[] = 'ET';
$stream = implode("\n", $content) . "\n";
$objects = [];

$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";
$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
$objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
$objects[] = "6 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream" . "endstream\nendobj\n";

$pdf = "%PDF-1.4\n";
$offsets = [0];

foreach ($objects as $object) {
    $offsets[] = strlen($pdf);
    $pdf .= $object;
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . count($offsets) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i < count($offsets); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . count($offsets) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n$xrefOffset\n%%EOF";

file_put_contents($outputPath, $pdf);
echo $outputPath . PHP_EOL;
