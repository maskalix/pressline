<?php
/**
 * PressLine Hook System
 *
 * Provides WordPress-style action and filter hooks for plugins.
 *
 * Actions  — fire-and-forget events. Listeners run side-effects.
 * Filters  — pipeline values through listeners; each can modify the value.
 *
 * Usage from a plugin:
 *   add_action('article.afterSave', function($article) { ... });
 *   add_filter('article.content', function($html, $article) { return $html; }, 10);
 *
 * Core/app code triggers them with:
 *   do_action('article.afterSave', $article);
 *   $html = apply_filters('article.content', $html, $article);
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

class PL_Hooks {
    /** @var array<string, array<int, array{cb: callable, priority: int}>> */
    private static $actions = [];
    /** @var array<string, array<int, array{cb: callable, priority: int}>> */
    private static $filters = [];
    /** @var array<string, int> Counts how many times each action has fired (for tests/debugging) */
    private static $didCount = [];

    public static function addAction(string $hook, callable $cb, int $priority = 10): void {
        self::$actions[$hook][] = ['cb' => $cb, 'priority' => $priority];
        usort(self::$actions[$hook], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    public static function doAction(string $hook, ...$args): void {
        self::$didCount[$hook] = (self::$didCount[$hook] ?? 0) + 1;
        if (empty(self::$actions[$hook])) return;
        foreach (self::$actions[$hook] as $entry) {
            try {
                ($entry['cb'])(...$args);
            } catch (Throwable $e) {
                error_log("PressLine hook '$hook' failed: " . $e->getMessage());
            }
        }
    }

    public static function addFilter(string $hook, callable $cb, int $priority = 10): void {
        self::$filters[$hook][] = ['cb' => $cb, 'priority' => $priority];
        usort(self::$filters[$hook], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    public static function applyFilters(string $hook, $value, ...$args) {
        if (empty(self::$filters[$hook])) return $value;
        foreach (self::$filters[$hook] as $entry) {
            try {
                $value = ($entry['cb'])($value, ...$args);
            } catch (Throwable $e) {
                error_log("PressLine filter '$hook' failed: " . $e->getMessage());
            }
        }
        return $value;
    }

    public static function removeAction(string $hook, callable $cb): void {
        if (empty(self::$actions[$hook])) return;
        self::$actions[$hook] = array_values(array_filter(
            self::$actions[$hook],
            fn($e) => $e['cb'] !== $cb
        ));
    }

    public static function removeFilter(string $hook, callable $cb): void {
        if (empty(self::$filters[$hook])) return;
        self::$filters[$hook] = array_values(array_filter(
            self::$filters[$hook],
            fn($e) => $e['cb'] !== $cb
        ));
    }

    public static function didAction(string $hook): int {
        return self::$didCount[$hook] ?? 0;
    }

    public static function listActions(): array {
        return array_keys(self::$actions);
    }

    public static function listFilters(): array {
        return array_keys(self::$filters);
    }
}

// Global helper functions (WordPress-style for familiarity)
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $cb, int $priority = 10): void {
        PL_Hooks::addAction($hook, $cb, $priority);
    }
    function do_action(string $hook, ...$args): void {
        PL_Hooks::doAction($hook, ...$args);
    }
    function add_filter(string $hook, callable $cb, int $priority = 10): void {
        PL_Hooks::addFilter($hook, $cb, $priority);
    }
    function apply_filters(string $hook, $value, ...$args) {
        return PL_Hooks::applyFilters($hook, $value, ...$args);
    }
}
