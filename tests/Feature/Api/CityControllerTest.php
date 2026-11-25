<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\County;
use App\Models\Postcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_cities_matching_the_search_parameter(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);
        Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2131',
        ]);
        City::query()->create([
            'county_id' => $county->id,
            'name' => 'Dunakeszi',
        ]);

        $this->getJson('/api/cities?search=Göd')
            ->assertOk()
            ->assertJsonPath('data.0.id', $city->id)
            ->assertJsonPath('data.0.county_id', $county->id)
            ->assertJsonPath('data.0.name', 'Göd')
            ->assertJsonPath('data.0.county.id', $county->id)
            ->assertJsonPath('data.0.county.name', 'Pest')
            ->assertJsonPath('data.0.postcodes.0.postcode', '2131')
            ->assertJsonPath('data.0.postcodes.1.postcode', '2132')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_show_returns_the_city_with_its_county(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);

        $this->getJson('/api/cities/'.$city->id)
            ->assertOk()
            ->assertJsonPath('data.id', $city->id)
            ->assertJsonPath('data.county.id', $county->id)
            ->assertJsonPath('data.county.name', 'Pest');
    }

    public function test_store_and_update_return_the_city_with_its_county(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $firstCounty = County::query()->create(['name' => 'Pest']);
        $secondCounty = County::query()->create(['name' => 'Fejér']);

        $response = $this->postJson('/api/cities', [
            'county_id' => $firstCounty->id,
            'name' => 'Göd',
        ])
            ->assertCreated()
            ->assertJsonPath('data.county.id', $firstCounty->id)
            ->assertJsonPath('data.county.name', 'Pest');

        $this->putJson('/api/cities/'.$response->json('data.id'), [
            'county_id' => $secondCounty->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.county.id', $secondCounty->id)
            ->assertJsonPath('data.county.name', 'Fejér');
    }

    public function test_index_filters_cities_by_county_id(): void
    {
        $county = County::query()->create(['name' => 'Pest']);
        $otherCounty = County::query()->create(['name' => 'Bács-Kiskun']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        City::query()->create([
            'county_id' => $otherCounty->id,
            'name' => 'Kecskemét',
        ]);

        $this->getJson('/api/cities?county_id='.$county->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $city->id)
            ->assertJsonPath('data.0.county_id', $county->id)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_index_rejects_a_county_id_that_does_not_exist(): void
    {
        $this->getJson('/api/cities?county_id=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['county_id']);
    }
}
