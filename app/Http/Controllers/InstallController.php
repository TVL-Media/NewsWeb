<?php

namespace App\Http\Controllers;

use App\Support\Install\DatabaseCheck;
use App\Support\Install\InstallRunner;
use App\Support\Install\Installer;
use App\Support\Install\Requirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

/**
 * The web installer at /install. Each step saves its answers in the session (passwords hashed or
 * encrypted, never shown again) and the last step runs InstallRunner one task per request.
 * Gone (404) once installed.
 */
class InstallController extends Controller
{
    public const LABELS = [
        'welcome' => 'Welcome', 'requirements' => 'Requirements', 'permissions' => 'Permissions', 'database' => 'Database',
        'license' => 'Licence', 'site' => 'Your site', 'admin' => 'Admin account', 'keys' => 'API keys', 'install' => 'Install',
    ];

    public static function steps(): array
    {
        return array_values(array_filter(array_keys(self::LABELS), fn ($s) => $s !== 'license' || config('installer.envato.enabled')));
    }

    public function index()
    {
        return redirect()->route('install.step', 'welcome');
    }

    public function show(Request $request, string $step)
    {
        $steps = self::steps();
        abort_unless(in_array($step, $steps, true), 404);
        $reached = (int) $request->session()->get('install.reached', 0);
        if (array_search($step, $steps, true) > $reached) {
            return redirect()->route('install.step', $steps[$reached]);
        }
        $data = $request->session()->get('install', []);

        return view('install.'.$step, [
            'step' => $step,
            'steps' => $steps,
            'data' => $data,
            'php' => in_array($step, ['requirements'], true) ? Requirements::php() : [],
            'folders' => in_array($step, ['permissions'], true) ? Requirements::folders() : [],
            'tasks' => InstallRunner::tasks((bool) ($data['sample'] ?? false)),
        ]);
    }

    public function save(Request $request, string $step)
    {
        $steps = self::steps();
        abort_unless(in_array($step, $steps, true) && $step !== 'install', 404);
        $index = array_search($step, $steps, true);
        abort_if($index > (int) $request->session()->get('install.reached', 0), 403);

        $error = match ($step) {
            'requirements' => Requirements::passes(Requirements::php()) ? null : 'Some required items are missing. Fix them, then check again.',
            'permissions' => Requirements::passes(Requirements::folders()) ? null : 'Some folders are not writable yet. Fix them, then check again.',
            'database' => $this->saveDatabase($request),
            'license' => $this->saveLicense($request),
            'site' => $this->saveSite($request),
            'admin' => $this->saveAdmin($request),
            'keys' => $this->saveKeys($request),
            default => null,
        };
        if ($error) {
            return back()->withErrors(['step' => $error])->withInput($request->except(['password', 'password_confirmation', 'db_password']));
        }

        $request->session()->put('install.reached', max((int) $request->session()->get('install.reached', 0), $index + 1));

        return redirect()->route('install.step', $steps[$index + 1]);
    }

    /** "Test connection" button. */
    public function testDatabase(Request $request)
    {
        $db = $this->databaseInput($request);
        if (is_string($db)) {
            return response()->json(['ok' => false, 'message' => $db]);
        }

        return response()->json(DatabaseCheck::test($db));
    }

    /** One install task (the Install step calls them in order and shows the progress list). */
    public function run(Request $request, InstallRunner $runner, string $task)
    {
        $data = $request->session()->get('install', []);
        $steps = self::steps();
        abort_if((int) ($data['reached'] ?? 0) < array_search('install', $steps, true), 403);
        $tasks = array_keys(InstallRunner::tasks((bool) ($data['sample'] ?? false)));
        abort_unless(in_array($task, $tasks, true), 404);

        // Tasks run in order: a task can only start when every earlier one has succeeded.
        $done = $data['done'] ?? [];
        foreach (array_slice($tasks, 0, array_search($task, $tasks, true)) as $earlier) {
            if (! in_array($earlier, $done, true)) {
                return response()->json(['ok' => false, 'message' => 'Run the earlier steps first.'], 409);
            }
        }

        $result = $runner->run($task, $this->decrypted($data));
        if ($result['ok']) {
            $request->session()->put('install.done', array_values(array_unique([...$done, $task])));
        }
        if ($result['ok'] && $task === 'finish') {
            $request->session()->forget('install'); // no passwords or keys left behind in the session
        }

        return response()->json($result);
    }

    // ─── Steps ───

    private function saveDatabase(Request $request): ?string
    {
        $db = $this->databaseInput($request);
        if (is_string($db)) {
            return $db;
        }
        $check = DatabaseCheck::test($db);
        if (! $check['ok']) {
            return $check['message'];
        }
        $db['password'] = Crypt::encryptString((string) $db['password']);
        $request->session()->put('install.db', $db);

        return null;
    }

    /** @return array<string, mixed>|string the connection details, or an error message */
    private function databaseInput(Request $request): array|string
    {
        $v = validator($request->all(), [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-$]+$/'],
            'db_username' => ['required', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ], [], ['db_host' => 'host', 'db_port' => 'port', 'db_database' => 'database name', 'db_username' => 'username']);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        // An empty password field keeps the one already saved (it is never sent back to the page).
        $password = $request->input('db_password');
        $saved = $request->session()->get('install.db.password');
        if (blank($password) && $saved && ! $request->boolean('db_no_password')) {
            $password = Crypt::decryptString($saved);
        }

        return [
            'host' => trim($request->input('db_host')), 'port' => (int) $request->input('db_port'),
            'database' => trim($request->input('db_database')), 'username' => trim($request->input('db_username')),
            'password' => (string) $password,
        ];
    }

    private function saveLicense(Request $request): ?string
    {
        $code = trim((string) $request->input('purchase_code'));
        if (! Installer::looksLikePurchaseCode($code)) {
            return 'That does not look like a purchase code. It has 36 characters, like 1a2b3c4d-1234-5678-9abc-1234567890ab.';
        }
        if ($token = config('installer.envato.token')) {
            try {
                $r = Http::withToken($token)->timeout(10)->acceptJson()->get('https://api.envato.com/v3/market/author/sale', ['code' => $code]);
            } catch (\Throwable) {
                return 'Could not reach Envato to check the code. Check the internet connection and try again.';
            }
            if ($r->status() === 404) {
                return 'Envato does not know this purchase code. Copy it again from Downloads → License certificate.';
            }
            if (! $r->ok()) {
                return 'Envato could not check the code right now. Try again in a minute.';
            }
            $item = config('installer.envato.item_id');
            if ($item && (string) $r->json('item.id') !== (string) $item) {
                return 'This purchase code is for a different item.';
            }
        }
        $request->session()->put('install.license', $code);

        return null;
    }

    private function saveSite(Request $request): ?string
    {
        $v = validator($request->all(), [
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'url', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'locale' => ['required', 'in:en,hi'],
            'timezone' => ['required', 'timezone'],
            'env' => ['required', 'in:production,local'],
        ], [], ['url' => 'website address']);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        $request->session()->put('install.site', $v->validated());
        $request->session()->put('install.sample', $request->boolean('sample'));

        return null;
    }

    private function saveAdmin(Request $request): ?string
    {
        $v = validator($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        $request->session()->put('install.admin', [
            'name' => $request->input('name'),
            'email' => strtolower(trim($request->input('email'))),
            'password_hash' => Hash::make($request->input('password')), // the plain password is never stored
        ]);

        return null;
    }

    private function saveKeys(Request $request): ?string
    {
        if ($request->boolean('skip')) {
            $request->session()->put('install.keys', []);

            return null;
        }
        $v = validator($request->all(), [
            'ai_key' => ['nullable', 'string', 'max:300'],
            'tts_provider' => ['nullable', 'in:none,google,azure'],
            'tts_google_key' => ['nullable', 'string', 'max:300'],
            'tts_azure_key' => ['nullable', 'string', 'max:300'],
            'tts_azure_region' => ['nullable', 'string', 'max:40'],
            'sms_key' => ['nullable', 'string', 'max:300'],
            'sms_sender' => ['nullable', 'string', 'max:20'],
            'sms_template' => ['nullable', 'string', 'max:60'],
        ]);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        $keys = array_filter($v->validated(), 'filled');
        $before = $request->session()->get('install.keys', []);
        foreach (['ai_key', 'tts_google_key', 'tts_azure_key', 'sms_key'] as $secret) {
            if (isset($keys[$secret])) {
                $keys[$secret] = Crypt::encryptString($keys[$secret]);
            } elseif (isset($before[$secret])) {
                $keys[$secret] = $before[$secret]; // empty field = keep the key saved earlier
            }
        }
        $request->session()->put('install.keys', $keys);

        return null;
    }

    /** The session data with secrets decrypted, for the runner. */
    private function decrypted(array $data): array
    {
        $data['db']['password'] = Crypt::decryptString($data['db']['password']);
        foreach (['ai_key', 'tts_google_key', 'tts_azure_key', 'sms_key'] as $secret) {
            if (isset($data['keys'][$secret])) {
                $data['keys'][$secret] = Crypt::decryptString($data['keys'][$secret]);
            }
        }

        return $data;
    }
}
