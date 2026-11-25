<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DataSeeder extends Seeder
{
    private const HEADERS = [
        'postcode' => 'Irányítószám',
        'county' => 'Megye név',
        'city' => 'Település név',
    ];

    private const BATCH_SIZE = 400;

    public function run(?string $filePath = null): void
    {
        $filePath ??= database_path('seeders/data/irszam.csv');
        $data = $this->readData($filePath);

        DB::transaction(function () use ($data): void {
            $countyRows = [];

            foreach (array_keys($data) as $countyName) {
                $countyRows[$countyName] = ['name' => $countyName];
            }

            foreach (array_chunk(array_values($countyRows), self::BATCH_SIZE) as $batch) {
                DB::table('counties')->upsert($batch, ['name'], ['name']);
            }

            foreach ($data as $countyName => $cities) {
                $countyId = DB::table('counties')
                    ->where('name', $countyName)
                    ->value('id') ?? throw new RuntimeException(
                        "County not found after import: {$countyName}",
                    );

                $cityRows = [];

                foreach (array_keys($cities) as $cityName) {
                    $cityRows[] = [
                        'county_id' => $countyId,
                        'name' => $cityName,
                    ];
                }

                foreach (array_chunk($cityRows, self::BATCH_SIZE) as $batch) {
                    DB::table('cities')->upsert($batch, ['county_id', 'name'], ['name']);
                }

                $cityIds = [];

                foreach (array_chunk(array_keys($cities), self::BATCH_SIZE) as $cityNames) {
                    $cityIds += DB::table('cities')
                        ->where('county_id', $countyId)
                        ->whereIn('name', $cityNames)
                        ->pluck('id', 'name')
                        ->all();
                }

                $postcodeRows = [];

                foreach ($cities as $cityName => $postcodes) {
                    $cityId = $cityIds[$cityName] ?? throw new RuntimeException(
                        "City not found after import: {$cityName}",
                    );

                    foreach ($postcodes as $postcode) {
                        $postcodeRows[] = [
                            'city_id' => $cityId,
                            'postcode' => $postcode,
                        ];
                    }
                }

                foreach (array_chunk($postcodeRows, self::BATCH_SIZE) as $batch) {
                    DB::table('postcodes')->upsert($batch, ['city_id', 'postcode'], ['postcode']);
                }
            }
        });
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function readData(string $filePath): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException(
                "Irányítószám CSV not found or unreadable: {$filePath}. Place irszam.csv in database/seeders/data.",
            );
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open Irányítószám CSV: {$filePath}");
        }

        try {
            $header = fgetcsv($handle, separator: ';', escape: '');

            if ($header === false) {
                throw new RuntimeException('Irányítószám CSV is empty.');
            }

            $header = array_map(
                static fn (?string $column): string => trim((string) $column),
                $header,
            );
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            $columnIndexes = array_flip($header);

            foreach (self::HEADERS as $headerName) {
                if (! array_key_exists($headerName, $columnIndexes)) {
                    throw new RuntimeException("Required CSV column is missing: {$headerName}");
                }
            }

            $data = [];
            $lineNumber = 1;

            while (($record = fgetcsv($handle, separator: ';', escape: '')) !== false) {
                $lineNumber++;

                if (count(array_filter($record, fn (?string $value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $postcode = trim((string) ($record[$columnIndexes[self::HEADERS['postcode']]] ?? ''));
                $county = trim((string) ($record[$columnIndexes[self::HEADERS['county']]] ?? ''));
                $city = trim((string) ($record[$columnIndexes[self::HEADERS['city']]] ?? ''));

                if ($county === '' || $city === '' || preg_match('/^\d{4}$/D', $postcode) !== 1) {
                    throw new RuntimeException("Invalid postcode, county, or city on CSV line {$lineNumber}.");
                }

                $data[$county][$city][$postcode] = $postcode;
            }
        } finally {
            fclose($handle);
        }

        if ($data === []) {
            throw new RuntimeException('Irányítószám CSV contains no data rows.');
        }

        return $data;
    }
}
