<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\County;
use Database\Seeders\DataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_hungarian_semicolon_csv_and_is_idempotent(): void
    {
        $filePath = $this->createCsvFile(
            "\xEF\xBB\xBFIrányítószám;Megye név;Település név\n".
            "8127;Fejér;Aba\n".
            "8128;Fejér;Aba\n".
            "5241;Jász-Nagykun-Szolnok;Abádszalók\n".
            "4622;Szabolcs-Szatmár-Bereg;Komoró\n".
            "4943;Szabolcs-Szatmár-Bereg;Kömörő\n".
            "3882;Borsod-Abaúj-Zemplén;Abaújkér\n".
            "3882;Borsod-Abaúj-Zemplén;Abaújalpár\n".
            "8127;Fejér;Aba\n",
        );

        try {
            $seeder = new DataSeeder;
            $seeder->run($filePath);
            $seeder->run($filePath);

            $this->assertDatabaseCount('counties', 4);
            $this->assertDatabaseCount('cities', 6);
            $this->assertDatabaseCount('postcodes', 7);

            $county = County::query()->where('name', 'Fejér')->firstOrFail();
            $city = City::query()
                ->where('county_id', $county->id)
                ->where('name', 'Aba')
                ->firstOrFail();

            $this->assertDatabaseHas('postcodes', [
                'city_id' => $city->id,
                'postcode' => '8127',
            ]);
            $this->assertDatabaseHas('postcodes', [
                'city_id' => $city->id,
                'postcode' => '8128',
            ]);
            $this->assertDatabaseHas('cities', [
                'county_id' => County::query()->where('name', 'Szabolcs-Szatmár-Bereg')->value('id'),
                'name' => 'Komoró',
            ]);
            $this->assertDatabaseHas('cities', [
                'county_id' => County::query()->where('name', 'Szabolcs-Szatmár-Bereg')->value('id'),
                'name' => 'Kömörő',
            ]);
        } finally {
            unlink($filePath);
        }
    }

    public function test_rejects_csv_with_missing_required_columns(): void
    {
        $filePath = $this->createCsvFile("code;county;city\n8127;Fejér;Aba\n");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Required CSV column is missing');

            (new DataSeeder)->run($filePath);
        } finally {
            unlink($filePath);
        }
    }

    public function test_rejects_invalid_postcodes_without_importing_any_rows(): void
    {
        $filePath = $this->createCsvFile(
            "Irányítószám;Megye név;Település név\n".
            "812;Fejér;Aba\n",
        );

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid postcode, county, or city');

            (new DataSeeder)->run($filePath);
        } finally {
            unlink($filePath);
        }
    }

    private function createCsvFile(string $contents): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'irszam-');

        if ($filePath === false) {
            throw new RuntimeException('Unable to create a temporary CSV file for the test.');
        }

        file_put_contents($filePath, $contents);

        return $filePath;
    }
}
