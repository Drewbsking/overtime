<?php

class EqualizationSheet
{
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

        $asOf = null;
        $rowNum = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $asOf = trim($data[0] ?? '');
            }
            // Data rows start after the first line (as-of); ignore empty rows

            $name = trim($data[3] ?? ''); // column D (index 3)
            $hoursRaw = $data[9] ?? '';   // column J (index 9)
            $doubleRaw = $data[16] ?? ''; // column Q (index 16)

            if ($name === '' && ($hoursRaw === '' || $hoursRaw === null) && ($doubleRaw === '' || $doubleRaw === null)) {
                continue;
            }

            $hours = (is_numeric($hoursRaw) ? (float)$hoursRaw : 0.0) + (is_numeric($doubleRaw) ? (float)$doubleRaw : 0.0);

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

        return [
            'rows' => $rows,
            'as_of' => $asOf ?: null,
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
}
