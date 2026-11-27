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
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        Sanctum::actingAs($creator);

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
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.city.name', 'Göd')
            ->assertJsonPath('data.city.county.name', 'Pest')
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $creator->id);

        Sanctum::actingAs($editor);
        $updatedResponse = $this->putJson('/api/postcodes/'.$response->json('data.id'), [
            'city_id' => $secondCity->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.city.name', 'Székesfehérvár')
            ->assertJsonPath('data.city.county.name', 'Fejér')
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $editor->id);

        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
        $this->assertNotNull($updatedResponse->json('data.updated_at'));
        $this->assertDatabaseHas('postcodes', [
            'id' => $response->json('data.id'),
            'created_by' => $creator->id,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_core_postcode_cannot_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);

        $this->putJson('/api/postcodes/'.$postcode->id, ['postcode' => '2131'])
            ->assertForbidden();

        $this->assertDatabaseHas('postcodes', [
            'id' => $postcode->id,
            'postcode' => '2132',
            'created_by' => null,
        ]);
    }

    public function test_core_postcode_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);

        $this->deleteJson('/api/postcodes/'.$postcode->id)->assertForbidden();

        $this->assertModelExists($postcode);
    }

    public function test_student_can_delete_a_postcode_created_by_another_student(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Göd',
        ]);
        $response = $this->postJson('/api/postcodes', [
            'city_id' => $city->id,
            'postcode' => '2132',
        ])->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/postcodes/'.$response->json('data.id'))
            ->assertOk();

        $this->assertDatabaseMissing('postcodes', ['id' => $response->json('data.id')]);
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
