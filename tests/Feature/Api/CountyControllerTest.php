<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\County;
use App\Models\Postcode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_counties_matching_the_search_parameter(): void
    {
        $county = County::query()->create(['name' => 'Bács-Kiskun']);
        County::query()->create(['name' => 'Pest']);

        $this->getJson('/api/counties?search=Bács')
            ->assertOk()
            ->assertJsonPath('data.0.id', $county->id)
            ->assertJsonPath('data.0.name', 'Bács-Kiskun')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_store_and_update_assign_audit_fields_to_authenticated_users(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/counties', [
            'name' => 'Pest',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $creator->id);

        Sanctum::actingAs($editor);
        $updatedResponse = $this->putJson('/api/counties/'.$response->json('data.id'), [
            'name' => 'Pest updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $editor->id);

        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
        $this->assertNotNull($updatedResponse->json('data.updated_at'));
        $this->assertDatabaseHas('counties', [
            'id' => $response->json('data.id'),
            'created_by' => $creator->id,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_core_county_cannot_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);

        $this->putJson('/api/counties/'.$county->id, ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertDatabaseHas('counties', [
            'id' => $county->id,
            'name' => 'Pest',
            'created_by' => null,
        ]);
    }

    public function test_core_county_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $county = County::query()->create(['name' => 'Pest']);

        $this->deleteJson('/api/counties/'.$county->id)->assertForbidden();

        $this->assertModelExists($county);
    }

    public function test_student_cannot_delete_a_county_with_a_core_city(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $county = new County(['name' => 'Student county']);
        $county->created_by = $student->id;
        $county->save();
        $city = City::query()->create([
            'county_id' => $county->id,
            'name' => 'Core city',
        ]);

        $this->deleteJson('/api/counties/'.$county->id)->assertForbidden();

        $this->assertModelExists($county);
        $this->assertModelExists($city);
    }

    public function test_student_cannot_delete_a_county_with_a_core_postcode(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $county = new County(['name' => 'Student county']);
        $county->created_by = $student->id;
        $county->save();
        $city = new City([
            'county_id' => $county->id,
            'name' => 'Student city',
        ]);
        $city->created_by = $student->id;
        $city->save();
        $postcode = Postcode::query()->create([
            'city_id' => $city->id,
            'postcode' => '2132',
        ]);

        $this->deleteJson('/api/counties/'.$county->id)->assertForbidden();

        $this->assertModelExists($county);
        $this->assertModelExists($city);
        $this->assertModelExists($postcode);
    }

    public function test_student_can_delete_a_county_created_by_another_student(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $response = $this->postJson('/api/counties', ['name' => 'Pest'])
            ->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/counties/'.$response->json('data.id'))
            ->assertOk();

        $this->assertDatabaseMissing('counties', ['id' => $response->json('data.id')]);
    }
}
