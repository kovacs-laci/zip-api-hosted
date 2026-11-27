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
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        Sanctum::actingAs($creator);

        $firstCounty = County::query()->create(['name' => 'Pest']);
        $secondCounty = County::query()->create(['name' => 'Fejér']);

        $response = $this->postJson('/api/cities', [
            'county_id' => $firstCounty->id,
            'name' => 'Göd',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.county.id', $firstCounty->id)
            ->assertJsonPath('data.county.name', 'Pest')
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $creator->id);

        Sanctum::actingAs($editor);
        $updatedResponse = $this->putJson('/api/cities/'.$response->json('data.id'), [
            'county_id' => $secondCounty->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.county.id', $secondCounty->id)
            ->assertJsonPath('data.county.name', 'Fejér')
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $editor->id);

        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
        $this->assertNotNull($updatedResponse->json('data.updated_at'));
        $this->assertDatabaseHas('cities', [
            'id' => $response->json('data.id'),
            'created_by' => $creator->id,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_core_city_cannot_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);

        $this->putJson('/api/cities/'.$city->id, ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'Göd',
            'created_by' => null,
        ]);
    }

    public function test_core_city_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);

        $this->deleteJson('/api/cities/'.$city->id)->assertForbidden();

        $this->assertModelExists($city);
    }

    public function test_student_city_cannot_be_deleted_if_it_contains_a_core_postcode(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = new City([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $city->created_by = $student->id;
        $city->save();
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);

        $this->deleteJson('/api/cities/'.$city->id)->assertForbidden();

        $this->assertModelExists($city);
        $this->assertModelExists($postcode);
    }

    public function test_student_can_delete_a_city_created_by_another_student(): void
    {
        $creator = User::factory()->create();
        $cityEditor = User::factory()->create();
        Sanctum::actingAs($creator);

        $county = County::query()->create(['name' => 'Pest']);
        $response = $this->postJson('/api/cities', [
            'county_id' => $county->id,
            'name' => 'Göd',
        ])->assertCreated();

        Sanctum::actingAs($cityEditor);
        $this->deleteJson('/api/cities/'.$response->json('data.id'))
            ->assertOk();

        $this->assertDatabaseMissing('cities', ['id' => $response->json('data.id')]);
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
