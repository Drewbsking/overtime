<?php

class EqualizationSheet
{
    /**
     * Load equalization data from a CSV file.
     * Expected format: header row at row 6 (1-based), data starts at row 7.
     * Names in column D (4th column), hours in column J (10th column).
     */
    public static function load(): array
    {
        $path = env('EQUALIZATION_FILE', 'storage/equalization.csv');
        $fullPath = self::resolvePath($path);

        if (!is_readable($fullPath)) {
            throw new RuntimeException("Equalization file not found or not readable at {$fullPath}");
        }

        $rows = [];
        if (($handle = fopen($fullPath, 'r')) === false) {
            throw new RuntimeException("Unable to open equalization file at {$fullPath}");
        }

        $rowNum = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            // Skip rows before data start (headers at row 6, data at row 7)
            if ($rowNum < 7) {
                continue;
            }

            $name = trim($data[3] ?? ''); // column D (index 3)
            $hoursRaw = $data[9] ?? '';   // column J (index 9)

            if ($name === '' && ($hoursRaw === '' || $hoursRaw === null)) {
                continue;
            }

            $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : 0.0;

            $rows[] = [
                'username' => $name,
                'total_hours' => $hours,
                'last_assigned_at' => null,
            ];
        }
        fclose($handle);

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
        // Absolute path? return as-is
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }
        return BASE_PATH . '/' . ltrim($path, '/');
    }
}
