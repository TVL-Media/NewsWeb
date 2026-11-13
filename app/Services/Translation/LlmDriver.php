<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * LLM translation through either the Anthropic Messages API or any OpenAI-compatible
 * chat-completions endpoint. Provider is chosen in Platform Settings → Translation.
 */
class LlmDriver implements TranslationDriver
{
    public function __construct(private string $provider, private string $apiKey, private string $model) {}

    public function name(): string
    {
        return $this->provider;
    }

    public function translate(array $fields, string $from, string $to, array $glossary, string $style): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('No API key set for the translation provider (Settings → Integrations / Translation).');
        }

        $gloss = collect($glossary)->map(fn ($g) => $g['do_not_translate'] ? "- Keep \"{$g['source_term']}\" untranslated" : "- \"{$g['source_term']}\" → \"{$g['target_term']}\"")->join("\n");
        $system = "You are a professional news translator. Translate from {$from} to {$to}. {$style}\n"
            .($gloss ? "Glossary (must follow):\n{$gloss}\n" : '')
            .'Preserve all HTML tags exactly. Return ONLY a JSON object with the same keys as the input.';
        $user = json_encode($fields, JSON_UNESCAPED_UNICODE);

        if ($this->provider === 'anthropic') {
            $res = Http::timeout(120)->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model ?: 'claude-sonnet-4-5', 'max_tokens' => 8000, 'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ])->throw()->json();
            $text = collect($res['content'] ?? [])->where('type', 'text')->pluck('text')->join('');
        } else {
            $base = rtrim((string) setting('translation.base_url', 'https://api.openai.com/v1'), '/');
            $res = Http::timeout(120)->withToken($this->apiKey)->post($base.'/chat/completions', [
                'model' => $this->model ?: 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            ])->throw()->json();
            $text = $res['choices'][0]['message']['content'] ?? '';
        }

        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new RuntimeException('Provider returned an unreadable response.');
        }

        return array_map(fn ($v) => is_string($v) ? $v : '', array_intersect_key($data + $fields, $fields));
    }
}
