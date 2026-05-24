<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Lightweight i18n for PressLine admin.
 *
 * Resolution order for the active language:
 *   1. $_SESSION['lang']            — set after login from preferences.language
 *   2. $_COOKIE['selectedLanguage'] — pre-existing legacy cookie (functions.php)
 *   3. $websets['language']         — global default
 *   4. 'cs'                         — final fallback
 *
 * Strings live in $LANG_STRINGS keyed by [lang][key]. Missing keys fall back
 * to Czech, then to the key itself (so untranslated UI is still readable).
 *
 * Translations are kept in three side-by-side files for readability:
 *   includes/lang/cs.php, includes/lang/en.php, includes/lang/de.php
 */

$LANG_AVAILABLE = [
    'cs' => 'Čeština',
    'en' => 'English',
    'de' => 'Deutsch',
];

$LANG_STRINGS = [
    'cs' => require __DIR__ . '/lang/cs.php',
    'en' => require __DIR__ . '/lang/en.php',
    'de' => require __DIR__ . '/lang/de.php',
];

/**
 * Resolve the active language for the current request. Pure — no side effects.
 *
 * The result is cached on $GLOBALS['__pl_active_lang'] by pl-load.php so
 * pages can read it without re-walking the resolution chain.
 */
function pl_resolve_lang(): string {
    global $LANG_AVAILABLE, $websets;
    $candidates = [
        $_SESSION['lang']             ?? null,
        $_COOKIE['selectedLanguage']  ?? null,
        $websets['language']          ?? null,
        'cs',
    ];
    foreach ($candidates as $c) {
        if (is_string($c) && isset($LANG_AVAILABLE[$c])) return $c;
    }
    return 'cs';
}

/**
 * Translate a key. Extra args are passed to vsprintf for %s/%d placeholders.
 *
 *   t('dash.meta.admins_n', 3)   →  "3 admins"
 *   t('nav.dashboard')           →  "Dashboard"
 */
function t(string $key, ...$args): string {
    global $LANG_STRINGS;
    $lang = $GLOBALS['__pl_active_lang'] ?? 'cs';
    $str = $LANG_STRINGS[$lang][$key]
        ?? $LANG_STRINGS['cs'][$key]
        ?? $key;
    if (!$args) return $str;
    $out = @vsprintf($str, $args);
    return $out === false ? $str : $out;
}

/**
 * Translate + HTML-escape. Use for plain text in templates.
 * Note: some strings (e.g. dash.welcome_back) contain intentional HTML —
 * use t() directly for those, this helper for everything else.
 */
function te(string $key, ...$args): string {
    return esc(t($key, ...$args));
}

/**
 * Pluralise a count using a CLDR-like (one / few / other) shape and a key
 * stem. The key stem is suffixed with `.one`, `.few`, `.other` for Czech;
 * `.one` / `.other` for English/German.
 *
 *   tn(1, 'count.articles')   → "1 článek" / "1 article" / "1 Artikel"
 *   tn(3, 'count.articles')   → "3 články" / "3 articles" / "3 Artikel"
 *   tn(8, 'count.articles')   → "8 článků" / "8 articles" / "8 Artikel"
 */
/**
 * Plugin-side string registration. Plugins call this from plugin.php so
 * their UI keys are available the same way as core ones.
 *
 *   pl_lang_register([
 *       'cs' => ['plugin.foo.greeting' => 'Ahoj'],
 *       'en' => ['plugin.foo.greeting' => 'Hi'],
 *       'de' => ['plugin.foo.greeting' => 'Hallo'],
 *   ]);
 */
function pl_lang_register(array $stringsByLang): void {
    global $LANG_STRINGS;
    foreach ($stringsByLang as $lang => $strings) {
        if (!isset($LANG_STRINGS[$lang])) $LANG_STRINGS[$lang] = [];
        $LANG_STRINGS[$lang] = array_merge($LANG_STRINGS[$lang], $strings);
    }
}

function tn(int $n, string $stem): string {
    $lang = $GLOBALS['__pl_active_lang'] ?? 'cs';
    if ($lang === 'cs') {
        $bucket = ($n === 1) ? 'one' : (($n >= 2 && $n <= 4) ? 'few' : 'other');
    } else {
        $bucket = ($n === 1) ? 'one' : 'other';
    }
    return t($stem . '.' . $bucket, $n);
}
