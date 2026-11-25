<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postcodes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('postcode', 4);

            $table->unique(['city_id', 'postcode']);
            $table->index('postcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};