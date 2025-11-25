<?php

class EqualizationSheet
{
    /**
     * Load equalization data from a CSV file.
     * Expected format:
     * - Row 1, column 1: optional "as of" note/date.
     * - Data rows start on row 2.
     * - Name in column D (index 3).
     * - Overtime hours = column J (index 9) + column Q (index 16).
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

        if (($handle = fopen($fullPath, 'r')) === false) {
            throw new RuntimeException("Unable to open equalization file at {$fullPath}");
        }

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $asOf = trim($data[0] ?? '');
                continue;
            }

            $name = trim($data[3] ?? '');
            $hoursRaw = $data[9] ?? '';
            $doubleRaw = $data[16] ?? '';

            // Skip blank rows
            if ($name === '' && ($hoursRaw === '' || $hoursRaw === null) && ($doubleRaw === '' || $doubleRaw === null)) {
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
}
