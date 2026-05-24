<?php
/**
 * URL helpers — link rendering and the .htaccess writer for the
 * "hide .php extension" setting.
 *
 * pl_url('/clanky.php')  → '/clanky.php'   (default)
 *                        → '/clanky'        (when hide_php_ext is on)
 *
 * Templates wrap their hrefs with `pl_url('/foo.php')` so the same code
 * works regardless of the toggle. The toggle only changes link rendering;
 * actual files keep their .php names. Apache mod_rewrite handles the
 * inbound side (extensionless → .php).
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

const PL_HTACCESS_BEGIN = '# BEGIN PressLine extension hiding';
const PL_HTACCESS_END   = '# END PressLine extension hiding';

function pl_hide_php_enabled(): bool {
    global $websets;
    $v = $websets['hide_php_ext'] ?? '0';
    return $v === '1' || $v === 1 || $v === true;
}

/**
 * Strip ".php" from a URL when the toggle is on. Keeps query strings and
 * fragments intact. External URLs are passed through unchanged.
 */
function pl_url(string $url): string {
    if (!pl_hide_php_enabled()) return $url;
    // Don't touch absolute external URLs.
    if (preg_match('#^https?://#i', $url)) return $url;
    // Don't touch URLs that don't end in .php (with optional ?... or #...).
    return preg_replace('#\.php(?=$|[?#])#', '', $url);
}

/**
 * Detect mod_rewrite. Apache exposes loaded modules via apache_get_modules;
 * other SAPIs (FPM, nginx) won't have it — return null in that case so the
 * caller can show "unknown" instead of a hard yes/no.
 */
function pl_mod_rewrite_loaded(): ?bool {
    if (!function_exists('apache_get_modules')) return null;
    $mods = apache_get_modules();
    return in_array('mod_rewrite', $mods, true);
}

/**
 * Best-effort server detection: 'apache', 'nginx', 'unknown'. Sniffs
 * SERVER_SOFTWARE — typically "Apache/2.4.x" or "nginx/1.27.x" — falling
 * back to "unknown" when we can't tell (CLI, exotic SAPIs).
 */
function pl_server_type(): string {
    $sw = strtolower((string)($_SERVER['SERVER_SOFTWARE'] ?? ''));
    if ($sw === '') return 'unknown';
    if (str_contains($sw, 'nginx'))  return 'nginx';
    if (str_contains($sw, 'apache')) return 'apache';
    return 'unknown';
}

/**
 * The nginx server-block snippet that mirrors the .htaccess rules.
 * Returned as a plain string so the page can show + offer to copy it.
 *
 * Note: nginx config can't be written by PHP (lives in /etc/nginx, root-
 * owned). The user must paste this in themselves.
 */
function pl_hide_php_nginx_snippet(): string {
    return <<<NGINX
# BEGIN PressLine extension hiding
# Place inside your `server { ... }` block, then `nginx -s reload`.

# 1. 301-redirect /foo.php → /foo (preserves query string)
if (\$request_uri ~ ^/(.+)\\.php(\\?.*)?$) {
    return 301 /\$1\$2;
}

# 2. Internally rewrite /foo → /foo.php when /foo.php exists
location / {
    try_files \$uri \$uri.php \$uri/ =404;
}
# END PressLine extension hiding
NGINX;
}

/**
 * Apply the hide_php_ext setting by writing (or removing) PressLine's
 * managed block in /.htaccess. Other directives in the file are preserved.
 *
 * Returns ['ok' => bool, 'message' => string] — caller can surface this
 * to the user.
 */
function pl_apply_hide_php(bool $enable): array {
    $path = PL_ROOT . '/.htaccess';
    $existing = is_file($path) ? (string)@file_get_contents($path) : '';

    // Strip any previous PressLine-managed block.
    $stripped = preg_replace(
        '/' . preg_quote(PL_HTACCESS_BEGIN, '/') . '.*?' . preg_quote(PL_HTACCESS_END, '/') . '\R?/s',
        '',
        $existing
    ) ?? $existing;

    if (!$enable) {
        if ((string)$stripped === '') {
            // Removing our block left the file empty — delete it.
            if (is_file($path)) @unlink($path);
            return ['ok' => true, 'message' => '.htaccess block removed'];
        }
        if (@file_put_contents($path, rtrim($stripped) . "\n") === false) {
            return ['ok' => false, 'message' => 'cannot write .htaccess'];
        }
        return ['ok' => true, 'message' => '.htaccess block removed'];
    }

    $block = PL_HTACCESS_BEGIN . "\n"
           . "<IfModule mod_rewrite.c>\n"
           . "    RewriteEngine On\n"
           . "    # Redirect /foo.php → /foo (preserves query string)\n"
           . "    RewriteCond %{THE_REQUEST} \\s/+(.+?)\\.php([?\\s]|$)\n"
           . "    RewriteRule ^ /%1 [R=301,L,QSA]\n"
           . "    # Internally rewrite /foo → /foo.php when /foo.php exists\n"
           . "    RewriteCond %{REQUEST_FILENAME} !-d\n"
           . "    RewriteCond %{REQUEST_FILENAME}.php -f\n"
           . "    RewriteRule ^(.+?)/?$ $1.php [L]\n"
           . "</IfModule>\n"
           . PL_HTACCESS_END . "\n";

    $newContent = ((string)$stripped === '' ? '' : rtrim($stripped) . "\n\n") . $block;
    if (@file_put_contents($path, $newContent) === false) {
        return ['ok' => false, 'message' => 'cannot write .htaccess'];
    }
    return ['ok' => true, 'message' => '.htaccess updated'];
}
