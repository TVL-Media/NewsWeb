<?php

use App\Jobs\RouteStory;
use App\Models\Admin;
use App\Models\AiCheck;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\Features;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InstallSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
| Milestone 1 (installable product): settings in the admin panel override .env/config,
| secrets are encrypted, brand comes from settings, and features hide themselves.
*/

function settingsAdmin(): Admin
{
    $admin = Admin::where('role', 'admin')->first();
    $admin->update(['password' => 'Secret!2026x']);

    return $admin;
}

function saveSettings(string $tab, array $data)
{
    $response = test()->actingAs(settingsAdmin(), 'admin')
        ->put("/admin/settings/{$tab}", $data + ['confirm_password' => 'Secret!2026x']);
    auth()->shouldUse('web'); // back to a website visitor for the next request

    return $response;
}

/** Every field of a tab with its current value, so a save only changes what the test passes. */
function tabValues(string $tab): array
{
    $out = [];
    foreach (\App\Http\Controllers\Admin\SettingsController::tabs()[$tab][1] as $key => $f) {
        $v = setting($key);
        if (in_array($f[0], ['secret', 'file'], true)) {
            continue;
        }
        $out[str_replace('.', '__', $key)] = in_array($f[0], ['bool', 'feature'], true) ? ($v ? '1' : null) : $v;
    }

    return array_filter($out, fn ($v) => $v !== null);
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('overrides config with saved settings, also after a restart', function () {
    Settings::set('site.name', 'Daily Desk');
    Settings::set('ai.api_key', 'sk-test-1234567890');
    expect(config('app.name'))->toBe('Daily Desk')
        ->and(config('services.anthropic.key'))->toBe('sk-test-1234567890');

    // A new request: config is back to .env values until the settings are applied at boot.
    config(['app.name' => 'NextGenNews', 'services.anthropic.key' => null]);
    Settings::resetBase();
    Settings::applyToConfig();

    expect(config('app.name'))->toBe('Daily Desk')
        ->and(config('services.anthropic.key'))->toBe('sk-test-1234567890');
});

it('keeps the .env value for anything nobody has saved', function () {
    expect(Setting::where('key', 'like', 'storage.%')->exists())->toBeFalse()
        ->and(setting('storage.driver'))->toBe(config('filesystems.media'))
        ->and(setting('site.name'))->toBe(config('app.name'));
});

it('stores keys encrypted and only ever shows them masked', function () {
    saveSettings('ai', ['ai__api_key' => 'sk-ant-secret-abcd', 'ai__model' => 'claude-opus-5-5'])->assertSessionHasNoErrors();

    $raw = Setting::where('key', 'ai.api_key')->first();
    expect($raw->is_encrypted)->toBeTrue()
        ->and($raw->value)->not->toContain('sk-ant-secret')
        ->and(Crypt::decryptString($raw->value))->toBe('sk-ant-secret-abcd');

    $this->actingAs(settingsAdmin(), 'admin')->get('/admin/settings/ai')->assertOk()->assertDontSee('sk-ant-secret-abcd')->assertSee('••••••••abcd');

    // Saving with the field left empty keeps the key; "Remove" clears it.
    saveSettings('ai', ['ai__model' => 'claude-opus-5-5']);
    expect(setting('ai.api_key'))->toBe('sk-ant-secret-abcd');
    saveSettings('ai', ['ai__model' => 'claude-opus-5-5', 'ai__api_key__clear' => '1']);
    expect(Setting::where('key', 'ai.api_key')->value('value'))->toBeNull();

    // The audit log never holds the key.
    expect(\App\Models\AuditLog::where('action', 'settings.updated')->get()->toJson())->not->toContain('sk-ant-secret');
});

it('opens every settings tab', function () {
    $this->actingAs(settingsAdmin(), 'admin');
    foreach (array_keys(\App\Http\Controllers\Admin\SettingsController::tabs()) as $tab) {
        $this->get("/admin/settings/{$tab}")->assertOk();
    }
});

it('uses the website name, colour and logo from settings instead of NextGenNews', function () {
    Storage::fake('public');
    saveSettings('general', ['site__logo' => UploadedFile::fake()->image('logo.png', 200, 56), 'site__name' => 'Daily Desk', 'site__brand_color' => '#1A56DB'] + tabValues('general'))
        ->assertSessionHasNoErrors();

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('<title>Daily Desk · ')
        ->and($html)->toContain('--color-brand:#1A56DB')
        ->and($html)->toContain('storage/branding/')
        ->and($html)->not->toContain('NextGenNews');

    $this->get('/privacy')->assertOk()->assertSee('Daily Desk collects')->assertDontSee('NextGenNews');
});

it('shows custom legal pages, cleaned of scripts', function () {
    Settings::set('legal.terms_html', '<h2>Our terms</h2><p>Be kind.</p><script>alert(1)</script>');
    Settings::set('legal.grievance_email', 'officer@example.org');

    $this->get('/terms')->assertOk()->assertSee('Be kind.')->assertDontSee('alert(1)', false);
    $this->get('/grievance-officer')->assertOk()->assertSee('officer@example.org')->assertDontSee('details below are placeholders');
});

it('switches a feature off everywhere', function () {
    saveSettings('features', array_merge(tabValues('features'), ['features__quiz' => null, 'features__comments' => null]))->assertSessionHasNoErrors();
    expect(Features::on('quiz'))->toBeFalse()->and(Features::on('comments'))->toBeFalse();

    $this->get('/')->assertOk()->assertDontSee('quiz-card-title', false);
    $story = Story::published()->first();
    $this->get("/story/{$story->slug}")->assertOk()->assertDontSee('comments-title', false);

    $this->actingAs(User::first());
    $this->get('/quiz')->assertNotFound();
    $this->postJson("/stories/{$story->id}/comments", ['body' => 'Hello'])->assertNotFound();

    $this->artisan('quiz:catch-up')->expectsOutputToContain('switched off');

    // Polls off: no Polls section or "Create poll" button on /briefing, no Polls tab on pages, no endpoint.
    Settings::set('features.polls', false);
    $this->get('/briefing')->assertOk()->assertDontSee('polls-title', false)->assertDontSee('ngn-create-poll', false);
    $page = \App\Models\Page::first();
    $this->get("/page/{$page->slug}")->assertOk()->assertDontSee('tab=polls', false);
    $this->postJson('/polls', ['question' => 'Is this switched off?', 'options' => ['Yes', 'No']])->assertNotFound();
});

it('hides a feature whose key is missing on a live server', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

    // No SMS key: phone login hides itself and its endpoint is a 404.
    Settings::set('sms.provider', 'msg91');
    expect(Features::on('otp_login'))->toBeFalse()
        ->and(Features::missing('otp_login'))->toContain('SMS');
    $this->postJson('/auth/otp/send', ['phone' => '9876543210'])->assertNotFound();
    $this->get('/')->assertOk()->assertSee('Logging in is not available right now');

    Settings::set('sms.api_key', 'msg91-key');
    Settings::set('sms.template_id', 'tpl-1');
    expect(Features::on('otp_login'))->toBeTrue();

    // No text-to-speech key: Listen and Morning 5 are off even though they are switched on.
    Settings::set('features.listen', true);
    Settings::set('tts.provider', 'google');
    expect(Features::on('listen'))->toBeFalse()->and(Features::on('morning5'))->toBeFalse();
    Settings::set('tts.google_key', 'g-key');
    expect(Features::on('listen'))->toBeTrue()->and(Features::on('morning5'))->toBeTrue();

    // No Claude key: nothing is auto-published; the story waits for an editor.
    config(['services.anthropic.key' => null]);
    $story = Story::published()->first();
    $story->update(['status' => 'draft']);
    AiCheck::where('story_id', $story->id)->update(['decision' => 'auto']);
    RouteStory::dispatchSync($story->id);
    expect($story->fresh()->status)->toBe('pending_approval')
        ->and(Features::missing('ai_autopublish'))->toContain('Claude API key');
});

it('needs every S3 key before switching storage to S3', function () {
    saveSettings('storage', ['storage__driver' => 's3', 'storage__aws_bucket' => 'my-bucket'])
        ->assertSessionHasErrors('storage__aws_key');
    expect(config('filesystems.media'))->not->toBe('s3');

    saveSettings('storage', ['storage__driver' => 's3', 'storage__aws_key' => 'AKIATEST', 'storage__aws_secret' => 'shh',
        'storage__aws_bucket' => 'my-bucket', 'storage__aws_region' => 'ap-south-1'])->assertSessionHasNoErrors();
    expect(config('filesystems.media'))->toBe('s3')
        ->and(config('filesystems.disks.s3.bucket'))->toBe('my-bucket')
        ->and(config('filesystems.disks.s3.secret'))->toBe('shh');
});

it('only offers the development OTP outside production', function () {
    Settings::set('sms.dev_otp', true);
    expect(config('services.sms.dev_code'))->toBe('123456')
        ->and(app(\App\Services\OtpService::class)->devCode())->toBe('123456');

    app()->detectEnvironment(fn () => 'production');
    expect(app(\App\Services\OtpService::class)->devCode())->toBeNull()
        ->and(\App\Http\Controllers\Admin\SettingsController::tabs()['sms'][1])->not->toHaveKey('sms.dev_otp');
});
