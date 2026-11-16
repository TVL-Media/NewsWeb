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

it('has no "Why it matters" field in the story editor and keeps any stored value when saving', function () {
    $story = Story::published()->with('versions')->first();
    $stored = $story->version('en')->why_it_matters;
    expect($stored)->not->toBeEmpty();

    $this->actingAs($this->admin, 'admin');
    $this->get("/admin/stories/{$story->id}/edit")->assertOk()->assertDontSee('Why it matters')->assertDontSee('[why_it_matters]', false);
    $this->get('/admin/stories/new')->assertOk()->assertDontSee('Why it matters');

    $en = $story->version('en');
    $this->put("/admin/stories/{$story->id}", [
        'en' => ['headline' => $en->headline.' (edited)', 'card_text' => $en->card_text, 'key_points' => $en->key_points, 'simple_text' => $en->simple_text, 'full_text' => $en->full_text],
        'category_id' => $story->category_id, 'importance' => $story->importance, 'read_minutes' => $story->read_minutes,
    ])->assertRedirect();

    $fresh = $story->fresh()->version('en');
    expect($fresh->headline)->toEndWith('(edited)')->and($fresh->why_it_matters)->toBe($stored);
});
