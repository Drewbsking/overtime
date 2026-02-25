<?php

class EqualizationSheet
{
    /**
     * Load equalization data from a CSV file.
     * Expected format:
     * - Row 1, column 1: optional "as of" note/date when the rest of the row is empty.
     * - Otherwise all rows are treated as data.
     * - Preferred CSV format: id, name, OT hours, Doubetime Hours.
     * - Legacy CSV: Name in column D (index 3); overtime hours = column J (index 9)
     *   + column Q (index 16) (YTD hours).
     * - New CSV (2026-02-06+): Number in column A, Name in column B; overtime hours = column D
     *   (index 3) + column G (index 6) (YTD hours). If YTD is blank, fall back to current hours
     *   in columns C (index 2) and F (index 5).
     * - Other columns ignored.
     */
    public static function load(): array
    {
        $path = env('EQUALIZATION_FILE', 'storage/equalization.csv');
        $fullPath = self::resolvePath($path);

        if (!is_readable($fullPath)) {
            throw new RuntimeException("Equalization file not found or not readable at {$fullPath}");
        }

        $rows = [];
        $fileMtime = @filemtime($fullPath) ?: null;
        $asOf = null;
        $rowNum = 0;
        $forceCompactFormat = false;

        if (($handle = fopen($fullPath, 'r')) === false) {
            throw new RuntimeException("Unable to open equalization file at {$fullPath}");
        }

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = array_map([self::class, 'normalizeCell'], $data);
            if ($rowNum === 1) {
                $firstCell = trim((string)($data[0] ?? ''));
                $hasOtherContent = false;
                $dataCount = count($data);
                for ($i = 1; $i < $dataCount; $i++) {
                    if (!self::isBlankValue($data[$i] ?? null)) {
                        $hasOtherContent = true;
                        break;
                    }
                }
                if ($firstCell !== '' && !$hasOtherContent) {
                    $asOf = $firstCell;
                    continue;
                }
            }

            if (self::isCompactHeaderRow($data)) {
                $forceCompactFormat = true;
                continue;
            }

            $isCompactFormat = $forceCompactFormat || self::isCompactFormatRow($data);
            $isNewFormat = !$isCompactFormat && self::isNewFormatRow($data);
            if ($isCompactFormat) {
                $name = trim((string)($data[1] ?? ''));
                $hoursRaw = $data[2] ?? '';
                $doubleRaw = $data[3] ?? '';
            } elseif ($isNewFormat) {
                $name = trim((string)($data[1] ?? ''));
                $hoursRaw = $data[3] ?? '';
                $doubleRaw = $data[6] ?? '';
                if (self::isBlankValue($hoursRaw) && !self::isBlankValue($data[2] ?? null)) {
                    $hoursRaw = $data[2];
                }
                if (self::isBlankValue($doubleRaw) && !self::isBlankValue($data[5] ?? null)) {
                    $doubleRaw = $data[5];
                }
            } else {
                $name = trim((string)($data[3] ?? ''));
                $hoursRaw = $data[9] ?? '';
                $doubleRaw = $data[16] ?? '';
            }

            // Skip blank rows
            if (self::isHeaderRow($name, $hoursRaw, $doubleRaw)) {
                continue;
            }
            if ($name === '' && self::isBlankValue($hoursRaw) && self::isBlankValue($doubleRaw)) {
                continue;
            }

            $hours = self::parseNumber($hoursRaw) + self::parseNumber($doubleRaw);

            $rows[] = [
                'username' => $name,
                'total_hours' => $hours,
                'last_assigned_at' => null,
            ];
        }
        fclose($handle);

        usort($rows, function ($a, $b) {
            $cmp = $a['total_hours'] <=> $b['total_hours'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp($a['username'], $b['username']);
        });

        return [
            'rows' => $rows,
            'as_of' => $asOf ?: null,
            'file_mtime' => $fileMtime,
        ];
    }

    private static function resolvePath(string $path): string
    {
        // Absolute path? return as-is
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }
        return BASE_PATH . '/' . ltrim($path, '/');
    }

    private static function parseNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        // Strip commas and whitespace
        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    private static function normalizeCell($value): string
    {
        $string = trim((string)$value);
        if ($string === '') {
            return '';
        }
        return str_replace("\xEF\xBB\xBF", '', $string);
    }

    private static function isBlankValue($value): bool
    {
        return trim((string)$value) === '';
    }

    private static function isNewFormatRow(array $data): bool
    {
        $number = trim((string)($data[0] ?? ''));
        $name = trim((string)($data[1] ?? ''));
        if ($number === '' || $name === '') {
            return false;
        }
        $number = str_replace("\xEF\xBB\xBF", '', $number);
        if (!preg_match('/^[0-9]+$/', $number)) {
            return false;
        }
        return str_contains($name, ',');
    }

    private static function isCompactFormatRow(array $data): bool
    {
        if (count($data) > 4) {
            return false;
        }
        $id = trim((string)($data[0] ?? ''));
        $name = trim((string)($data[1] ?? ''));
        if ($id === '' || $name === '') {
            return false;
        }
        $id = str_replace("\xEF\xBB\xBF", '', $id);
        return preg_match('/^[0-9]+$/', $id) === 1;
    }

    private static function isCompactHeaderRow(array $data): bool
    {
        $id = self::normalizeHeaderCell($data[0] ?? '');
        $name = self::normalizeHeaderCell($data[1] ?? '');
        $ot = self::normalizeHeaderCell($data[2] ?? '');
        $double = self::normalizeHeaderCell($data[3] ?? '');

        if ($id !== 'id') {
            return false;
        }
        if (!in_array($name, ['name', 'employee', 'employee name'], true)) {
            return false;
        }
        if (!in_array($ot, ['ot hours', 'overtime hours', 'ot'], true)) {
            return false;
        }
        return in_array($double, ['doubetime hours', 'doubletime hours', 'double time hours', 'doubetime', 'doubletime'], true);
    }

    private static function normalizeHeaderCell($value): string
    {
        $header = strtolower(trim((string)$value));
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        return preg_replace('/\s+/', ' ', $header) ?? '';
    }

    private static function isHeaderRow(string $name, $hoursRaw, $doubleRaw): bool
    {
        $lower = strtolower(trim($name));
        $hours = strtolower(trim((string)$hoursRaw));
        $double = strtolower(trim((string)$doubleRaw));
        if ($lower === '' ) {
            return $hours === 'hours' || $double === 'hours';
        }
        if (in_array($lower, ['name', 'employee', 'employee name', 'number'], true)) {
            return true;
        }
        return in_array($hours, ['hours', 'ot hours', 'overtime hours'], true)
            || in_array($double, ['hours', 'doubetime hours', 'doubletime hours', 'double time hours'], true);
    }
}
