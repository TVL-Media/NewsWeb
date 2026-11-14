<?php

use App\Models\Admin;
use App\Models\Media;
use App\Models\Story;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = Admin::where('role', 'admin')->first();
});

it('opens the media library when it has files, marking used and unused ones', function () {
    $story = Story::published()->whereNotNull('photo_path')->first();
    $used = Media::create(['disk' => 'public', 'path' => $story->photo_path, 'original_name' => 'used.jpg', 'kind' => 'image', 'provider' => 'upload', 'size_bytes' => 1000]);
    $unused = Media::create(['disk' => 'public', 'path' => 'media/unused.jpg', 'original_name' => 'unused.jpg', 'kind' => 'image', 'provider' => 'upload', 'size_bytes' => 1000]);

    $this->actingAs($this->admin, 'admin');

    $this->get('/admin/media')->assertOk()->assertSee('used.jpg')->assertSee('unused.jpg');
    $this->get('/admin/media?unused=1')->assertOk()->assertSee('unused.jpg')->assertDontSee('>used.jpg<', false);
    $this->get("/admin/media/{$used->id}")->assertOk();

    expect($used->usages()->pluck('id')->all())->toContain($story->id)
        ->and($unused->usageCount())->toBe(0);
});
