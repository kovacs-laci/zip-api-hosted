<?php

namespace Tests\Feature\Api;

use App\Models\County;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
