<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\County;
use App\Models\Postcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostcodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_matches_postcode_column_and_returns_schema_fields(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2131',
        ]);
        Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);

        $this->getJson('/api/postcodes?search=2131')
            ->assertOk()
            ->assertJsonPath('data.0.id', $postcode->id)
            ->assertJsonPath('data.0.city_id', $city->id)
            ->assertJsonPath('data.0.postcode', '2131')
            ->assertJsonPath('data.0.city.id', $city->id)
            ->assertJsonPath('data.0.city.name', 'Göd')
            ->assertJsonPath('data.0.city.county.id', $county->id)
            ->assertJsonPath('data.0.city.county.name', 'Pest')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_show_returns_the_city_and_county(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2131',
        ]);

        $this->getJson('/api/postcodes/'.$postcode->id)
            ->assertOk()
            ->assertJsonPath('data.city.name', 'Göd')
            ->assertJsonPath('data.city.county.name', 'Pest');
    }

    public function test_store_and_update_return_the_city_and_county(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $firstCounty = County::query()->create(['name' => 'Pest']);
        $firstCity = City::query()->create([
            'county_id' => $firstCounty->id,
            'name' => 'Göd',
        ]);
        $secondCounty = County::query()->create(['name' => 'Fejér']);
        $secondCity = City::query()->create([
            'county_id' => $secondCounty->id,
            'name' => 'Székesfehérvár',
        ]);

        $response = $this->postJson('/api/postcodes', [
            'city_id' => $firstCity->id,
            'postcode' => '2131',
        ])
            ->assertCreated()
            ->assertJsonPath('data.city.name', 'Göd')
            ->assertJsonPath('data.city.county.name', 'Pest');

        $this->putJson('/api/postcodes/'.$response->json('data.id'), [
            'city_id' => $secondCity->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.city.name', 'Székesfehérvár')
            ->assertJsonPath('data.city.county.name', 'Fejér');
    }

    public function test_rejects_postcodes_longer_than_the_database_column_limit(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $this->withoutMiddleware()
            ->postJson('/api/postcodes', [
                'city_id' => $city->id,
                'postcode' => '12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['postcode']);
    }
}
