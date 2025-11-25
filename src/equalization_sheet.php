<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

class EqualizationSheet
{
    public static function load(): array
    {
        $path = env('EQUALIZATION_FILE', 'storage/equalization.xlsx');
        $fullPath = self::resolvePath($path);

        if (!is_readable($fullPath)) {
            throw new RuntimeException("Equalization file not found or not readable at {$fullPath}");
        }

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getSheet(0);

        $rows = [];
        $row = 7; // data starts at row 7 (row 6 is headers)
        while (true) {
            $name = trim((string)$sheet->getCell('D' . $row)->getValue());
            $hoursCell = $sheet->getCell('J' . $row);
            $hoursRaw = $hoursCell->getCalculatedValue();

            // Stop when both name and hours are empty
            if ($name === '' && ($hoursRaw === null || $hoursRaw === '')) {
                break;
            }

            if ($name !== '') {
                $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : 0.0;
                $rows[] = [
                    'username' => $name,
                    'total_hours' => $hours,
                    'last_assigned_at' => null,
                ];
            }

            $row++;
            // Safety to avoid infinite loops on malformed files
            if ($row > 5000) {
                break;
            }
        }

        // Sort: lowest hours first, then name
        usort($rows, function ($a, $b) {
            $cmp = $a['total_hours'] <=> $b['total_hours'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp($a['username'], $b['username']);
        });

        return $rows;
    }

    private static function resolvePath(string $path): string
    {
        // If absolute, return as-is
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }
        return BASE_PATH . '/' . ltrim($path, '/');
    }
}
