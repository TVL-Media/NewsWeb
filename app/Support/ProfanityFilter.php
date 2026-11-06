<?php

namespace App\Support;

/**
 * Small profanity/abuse filter for comments and user polls: English, Hindi (Devanagari)
 * and common romanised Hindi slurs, with simple obfuscation handling (f*ck, f.u.c.k, sh1t).
 * It is a first line of defence; reports and editors handle the rest.
 */
class ProfanityFilter
{
    private const WORDS = [
        // English
        'fuck', 'fucking', 'fucker', 'shit', 'bitch', 'bastard', 'asshole', 'dick', 'cunt', 'slut', 'whore', 'motherfucker', 'retard',
        // Romanised Hindi
        'chutiya', 'chutiye', 'madarchod', 'behenchod', 'bhenchod', 'bhosdike', 'bhosdi', 'gaandu', 'gandu', 'harami', 'kamina', 'randi', 'lavde', 'lauda', 'lodu', 'kutte', 'kutta', 'saala', 'saali',
        // Devanagari
        'चूतिया', 'मादरचोद', 'बहनचोद', 'भोसडी', 'गांडू', 'हरामी', 'कमीना', 'रंडी', 'लौड़ा', 'कुत्ते',
    ];

    public static function isClean(string $text): bool
    {
        $normal = mb_strtolower($text);
        $normal = strtr($normal, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '@' => 'a', '$' => 's', '!' => 'i']);
        // "f.u.c.k" / "f u c k" → "fuck" (only for Latin letters separated by single symbols)
        $collapsed = preg_replace('/(?<=\b\p{Latin})[\s._\-]+(?=\p{Latin}\b)/u', '', $normal);

        foreach ([$normal, $collapsed] as $candidate) {
            foreach (self::WORDS as $word) {
                // Latin words: whole-word match where any letter may be masked with "*" (f*ck, sh*t).
                $pattern = preg_match('/^\p{Latin}+$/u', $word)
                    ? '/(?<![\p{L}*])'.implode('', array_map(fn ($ch) => '['.preg_quote($ch, '/').'*]', mb_str_split($word))).'(?![\p{L}*])/u'
                    : '/'.preg_quote($word, '/').'/u';
                if (preg_match($pattern, $candidate)) {
                    return false;
                }
            }
        }

        return true;
    }
}
