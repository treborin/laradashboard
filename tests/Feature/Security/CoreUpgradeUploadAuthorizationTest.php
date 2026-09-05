<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\Support\Security\InteractsWithSecurityUsers;

pest()->use(RefreshDatabase::class);

uses(InteractsWithSecurityUsers::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->setUpSecurityUsers();
    config(['app.demo_mode' => false]);
});

test('admin with settings.edit cannot upload manual core upgrades', function () {
    Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
    $this->adminUser->givePermissionTo(['settings.edit', 'settings.view']);

    $zip = UploadedFile::fake()->create('upgrade.zip', 100, 'application/zip');

    $this->actingAs($this->adminUser)
        ->post(route('admin.core-upgrades.upload'), [
            'upgrade_file' => $zip,
            'create_backup' => 0,
        ])
        ->assertForbidden();
});

test('superadmin can access manual core upgrade upload endpoint', function () {
    $zip = UploadedFile::fake()->create('upgrade.zip', 100, 'application/zip');

    $response = $this->actingAs($this->superadminUser)
        ->post(route('admin.core-upgrades.upload'), [
            'upgrade_file' => $zip,
            'create_backup' => 0,
        ]);

    expect($response->status())->not->toBe(403);
});
