<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\InstallSeeder;
use Spatie\Permission\Models\Role;

it('seeds an install with base data only: no people, no stories', function () {
    $this->seed(InstallSeeder::class);

    expect(Category::count())->toBeGreaterThan(5)
        ->and(Role::where('name', 'Super Admin')->exists())->toBeTrue()
        ->and(Admin::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(Story::count())->toBe(0);
});
