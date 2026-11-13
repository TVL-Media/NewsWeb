<?php

use App\Http\Controllers\NotificationController;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('makes the header bell a link to /notifications on desktop and mobile', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'href="'.route('notifications').'"'))->toBe(2)
        ->and($html)->not->toContain('role="img" aria-label="Notifications"');
});

it('shows guests breaking news and a prompt to log in', function () {
    $breaking = Story::published()->where('is_breaking', true)->first();

    $this->get('/notifications')->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Log in to see replies to your comments')
        ->assertSee('Breaking news')
        ->assertSee($breaking->version('en')->headline);
});

it('shows a reader replies, likes, followed pages and a streak reminder', function () {
    $riya = User::where('phone', '9876543210')->first();
    $mine = Comment::where('user_id', $riya->id)->firstOrFail();
    $reply = Comment::where('parent_id', $mine->id)->latest('id')->first();

    $this->actingAs($riya)->get('/notifications')->assertOk()
        ->assertDontSee('Log in to see replies')
        ->assertSee($reply->user->name.' replied to your comment')
        ->assertSee($reply->body)
        ->assertSee('3 people liked your comment')
        ->assertSee('New from ')
        ->assertSee('Keep your 12-day streak')
        ->assertSee('Read 1 more story today.');
});

it('marks everything as seen: the red dot goes until something new arrives', function () {
    $res = $this->get('/notifications');
    $seen = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === NotificationController::SEEN_COOKIE);
    expect($seen)->not->toBeNull();

    // Before: unseen → the bell carries "· New". After visiting: no dot.
    $this->get('/')->assertSee('Notifications · New', false);
    $this->withCookie(NotificationController::SEEN_COOKIE, (string) now()->addMinute()->getTimestamp())
        ->get('/')->assertDontSee('Notifications · New', false);
});
