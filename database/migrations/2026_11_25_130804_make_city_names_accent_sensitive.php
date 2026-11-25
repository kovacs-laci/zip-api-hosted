<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->string('name')->collation('utf8mb4_bin')->change();
        });
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $duplicate = DB::selectOne(
            'SELECT county_id FROM cities GROUP BY county_id, name COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1 LIMIT 1',
        );

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot restore accent-insensitive city-name collation while distinct city names would conflict.',
            );
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->string('name')->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
