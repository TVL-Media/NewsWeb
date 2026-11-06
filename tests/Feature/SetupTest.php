<?php

use App\Models\Briefing;
use App\Models\Category;
use App\Models\City;
use App\Models\Page;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('seeds the demo content described in the brief', function () {
    expect(Category::count())->toBe(8)
        ->and(City::pluck('name_en')->sort()->values()->all())->toBe(['Bhopal', 'Delhi', 'Jaipur', 'Lucknow', 'Mumbai', 'Patna'])
        ->and(Page::count())->toBe(10)
        ->and(Story::published()->count())->toBe(30)
        ->and(Poll::count())->toBe(3)
        ->and(Briefing::today()?->story_ids)->toHaveCount(5)
        ->and(Quiz::today()?->questions()->count())->toBe(5);
});

it('gives every story an English and a Hindi version with a 60-word card and 3 key points', function () {
    Story::with('versions')->get()->each(function (Story $story) {
        expect($story->versions->pluck('language')->sort()->values()->all())->toBe(['en', 'hi']);
        $story->versions->each(function ($v) {
            expect(count(preg_split('/\s+/u', trim($v->card_text))))->toBeLessThanOrEqual(60)
                ->and($v->key_points)->toHaveCount(3);
        });
    });
});

it('falls back to the other language when a version is missing', function () {
    $story = Story::first();
    $story->versions()->where('language', 'hi')->delete();

    app()->setLocale('hi');

    expect($story->fresh()->version()->language)->toBe('en');
});

it('renders the home page for guests with the layout and components', function () {
    $top = Story::published()->orderByDesc('importance')->first();

    $this->get('/')
        ->assertOk()
        ->assertSee('NextGenNews')
        ->assertSee("Today's top news")
        ->assertSee($top->version('en')->headline)
        ->assertSee('Latest News')
        ->assertSee('Log in to continue')       // login sheet
        ->assertSee('Get the Android app')      // mobile banner + footer
        ->assertSee('Grievance officer');       // footer
});

it('greets signed-in readers by name and hides the login sheet', function () {
    $user = User::where('phone', '9876543210')->first();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Riya')
        ->assertSee('Lucknow')                  // city chip
        ->assertDontSee('Log in to continue');
});

it('switches guests to Hindi with a cookie', function () {
    $story = Story::published()->orderByDesc('importance')->first();

    $this->post('/preferences/language', ['language' => 'hi'])
        ->assertRedirect()
        ->assertPlainCookie('lang', 'hi');

    $this->withUnencryptedCookie('lang', 'hi')->get('/')
        ->assertOk()
        ->assertSee('<html lang="hi"', false)
        ->assertSee($story->version('hi')->headline);
});

it('saves the language on the user when signed in', function () {
    $user = User::factory()->create(['language' => 'en']);

    $this->actingAs($user)->post('/preferences/language', ['language' => 'hi']);

    expect($user->fresh()->language)->toBe('hi');
});

it('sends guests from login-only pages to home with the login sheet open', function (string $url) {
    $this->get($url)->assertRedirect(route('home', ['login' => 1]));
})->with(['/for-you', '/account', '/account/saved', '/quiz', '/onboarding']);

it('serves every public route', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/', '/?category=cricket', '/explore', '/search', '/search?q=metro', '/briefing', '/about', '/privacy', '/terms', '/grievance-officer', '/community-rules', '/page/cricket', '/page/lucknow', '/story/india-beat-new-zealand-by-seven-wickets-to-clinch-test-series-2-0']);
