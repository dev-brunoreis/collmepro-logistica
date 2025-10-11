<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Label;
use App\Models\User;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class LabelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create labels.view permission
        $permission = Permission::create([
            'name' => 'labels.view',
            'display_name' => 'View Labels',
            'description' => 'Can view labels',
            'category' => 'labels',
        ]);

        // Assign to manager role
        RolePermission::create([
            'role' => 'manager',
            'permission_id' => $permission->id,
        ]);
    }

    public function test_index_displays_labels_list(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->count(5)->create();

        $response = $this->actingAs($user)->get('/dashboard/labels');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Labels/Index')
            ->has('labels.data', 5)
        );
    }

    public function test_index_applies_search_filter(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->create(['tracking_code' => 'ABC123']);
        Label::factory()->create(['tracking_code' => 'XYZ789']);

        $response = $this->actingAs($user)->get('/dashboard/labels?search=ABC');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('labels.data', 1)
        );
    }

    public function test_index_applies_date_range_filter(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-01')]);
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-15')]);
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-31')]);

        $response = $this->actingAs($user)->get('/dashboard/labels?start_date=2025-01-10&end_date=2025-01-20');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('labels.data', 1)
        );
    }

    public function test_index_applies_today_filter(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->create(['issue_date' => Carbon::today()]);
        Label::factory()->create(['issue_date' => Carbon::yesterday()]);

        $response = $this->actingAs($user)->get('/dashboard/labels?today=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('labels.data', 1)
        );
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'sales']); // No permission

        $response = $this->actingAs($user)->get('/dashboard/labels');

        $response->assertStatus(403);
    }

    public function test_export_generates_xls_file(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->count(3)->create();

        $response = $this->actingAs($user)->post('/dashboard/labels/export', [
            'format' => 'xls',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_generates_pdf_file(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->count(3)->create();

        $response = $this->actingAs($user)->post('/dashboard/labels/export', [
            'format' => 'pdf',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_export_applies_filters(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->create(['tracking_code' => 'ABC123']);
        Label::factory()->create(['tracking_code' => 'XYZ789']);

        $response = $this->actingAs($user)->post('/dashboard/labels/export', [
            'format' => 'xls',
            'search' => 'ABC',
        ]);

        $response->assertStatus(200);
    }

    public function test_export_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'sales']); // No permission

        $response = $this->actingAs($user)->post('/dashboard/labels/export', [
            'format' => 'xls',
        ]);

        $response->assertStatus(403);
    }

    public function test_export_validates_format(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($user)->post('/dashboard/labels/export', [
            'format' => 'invalid',
        ]);

        $response->assertSessionHasErrors('format');
    }

    public function test_pagination_works_correctly(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Label::factory()->count(100)->create();

        $response = $this->actingAs($user)->get('/dashboard/labels?per_page=25');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('labels.data', 25)
            ->where('labels.per_page', 25)
        );
    }
}

