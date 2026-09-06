<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Mcp\Briefing\Providers\CoreBriefingProvider;
use App\Mcp\Servers\LaraDashboardServer;
use App\Mcp\Tools\GetDailyBriefingTool;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\McpBriefingService;
use App\Services\Mcp\McpRegistryService;
use App\Services\Mcp\McpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Enums\TicketStatus;
use Modules\Crm\Mcp\Briefing\CrmBriefingProvider;
use Modules\Crm\Models\Contact;
use Modules\Crm\Models\ContactActivity;
use Modules\Crm\Models\Ticket;
use Modules\CustomForm\Models\CustomForm;
use Modules\CustomForm\Models\CustomFormSubmission;
use Modules\LaraDashboard\Models\Module;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'dashboard.view',
        'post.view',
        'post.create',
        'settings.edit',
        'ticket.view',
        'contact_activity.view',
        'deals.view',
        'module.view',
        'custom_form.view',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
    $this->user->assignRole($role);
    $this->user->syncPermissions([
        'dashboard.view',
        'post.view',
        'settings.edit',
        'ticket.view',
        'contact_activity.view',
        'deals.view',
        'module.view',
        'custom_form.view',
    ]);

    app(McpTokenService::class)->createToken($this->user);
    $accessToken = $this->user->tokens()->latest()->first();
    $this->user->withAccessToken($accessToken);

    $this->actingAs($this->user);
});

test('daily briefing tool is registered in mcp registry', function () {
    expect(app(McpRegistryService::class)->toolClasses())->toContain(GetDailyBriefingTool::class);

    $definitions = collect(app(McpRegistryService::class)->toolDefinitions());

    expect($definitions->pluck('name')->all())->toContain('get-daily-briefing');
});

test('briefing providers are registered from core and modules', function () {
    $providers = app(McpBriefingService::class)->providerClasses();

    expect($providers)->toContain(CoreBriefingProvider::class)
        ->toContain(CrmBriefingProvider::class);
});

test('get daily briefing mcp tool returns structured response', function () {
    LaraDashboardServer::actingAs($this->user, 'sanctum')
        ->tool(GetDailyBriefingTool::class, [])
        ->assertOk()
        ->assertSee('greeting')
        ->assertSee('generated_at')
        ->assertSee('summary')
        ->assertSee('sections')
        ->assertSee('all_clear');
});

test('daily briefing includes pending posts for authorized users', function () {
    Post::factory()->create([
        'title' => 'Briefing Pending Post',
        'user_id' => $this->user->id,
        'status' => PostStatus::PENDING->value,
        'post_type' => 'post',
    ]);

    LaraDashboardServer::actingAs($this->user, 'sanctum')
        ->tool(GetDailyBriefingTool::class, [])
        ->assertOk()
        ->assertSee('Briefing Pending Post')
        ->assertSee('core_posts_pending_review');
});

test('daily briefing includes crm ticket and activity signals', function () {
    $contact = Contact::factory()->create([
        'email' => 'briefing-contact@example.com',
    ]);

    Ticket::create([
        'title' => 'Briefing Ticket Subject',
        'description' => 'Needs attention',
        'priority' => 'normal',
        'status' => TicketStatus::OPEN->value,
        'customer_email' => 'ticket@example.com',
        'contact_id' => $contact->id,
        'assigned_to' => null,
    ]);

    ContactActivity::query()->create([
        'contact_id' => $contact->id,
        'type' => 'task',
        'note' => 'Briefing overdue task',
        'date' => now()->subDay(),
        'created_by' => $this->user->id,
        'is_completed' => false,
        'notify_contact' => false,
    ]);

    LaraDashboardServer::actingAs($this->user, 'sanctum')
        ->tool(GetDailyBriefingTool::class, [])
        ->assertOk()
        ->assertSee('crm_unassigned_tickets')
        ->assertSee('Briefing Ticket Subject')
        ->assertSee('crm_overdue_activities');
});

test('daily briefing includes marketplace and form items when data exists', function () {
    Module::create([
        'name' => 'Briefing Module',
        'slug' => 'briefing-module',
        'status' => 'pending',
        'description' => 'Needs review',
        'version' => '1.0.0',
        'zip_file' => 'modules/briefing-module.zip',
        'version_json' => ['name' => 'briefing-module', 'version' => '1.0.0'],
        'user_id' => $this->user->id,
    ]);

    $form = CustomForm::create([
        'title' => 'Contact Form',
        'slug' => 'contact-form-briefing',
        'description' => 'Test form',
        'form_schema' => [
            ['type' => 'text', 'name' => 'message', 'label' => 'Message', 'required' => true],
        ],
        'is_active' => true,
    ]);

    CustomFormSubmission::create([
        'form_id' => $form->id,
        'submission_data' => ['message' => 'Important lead message'],
        'viewed_at' => null,
    ]);

    LaraDashboardServer::actingAs($this->user, 'sanctum')
        ->tool(GetDailyBriefingTool::class, [])
        ->assertOk()
        ->assertSee('marketplace_pending_modules')
        ->assertSee('Briefing Module')
        ->assertSee('forms_unviewed_submissions');
});

test('daily briefing respects section filter', function () {
    Post::factory()->create([
        'title' => 'Hidden By Section Filter',
        'user_id' => $this->user->id,
        'status' => PostStatus::PENDING->value,
        'post_type' => 'post',
    ]);

    LaraDashboardServer::actingAs($this->user, 'sanctum')
        ->tool(GetDailyBriefingTool::class, ['sections' => ['crm']])
        ->assertOk()
        ->assertDontSee('Hidden By Section Filter')
        ->assertDontSee('core_posts_pending_review');
});
