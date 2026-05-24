<div align="center">

<img src="https://typestor.lnln.eu//brand/pressline/image/pressline.png" alt="PressLine" width="120" />

# PressLine

A small CMS with a real plugin SDK.
PHP 8 and MySQL 8. Under 5 MB on disk. No JavaScript framework.

[pressline.app](https://pressline.app) ·
[live demo](https://admin.pressline.app) ·
[marketplace](https://marketplace.pressline.app) ·
[docs](https://pressline.app/docs.php)

</div>

---

## What it is

PressLine is a self-hosted CMS for people who think WordPress is too big and Ghost is too clever. Articles, media, users, plugins, a marketplace. Nothing else.

- PHP 8, MySQL 8, MIT licensed.
- Hooks and filters in the style of WordPress (`add_action`, `add_filter`).
- A fluent plugin SDK (`PL_Plugin::register(...)`).
- A built-in marketplace client with sha256-verified installs.
- Light, dark, and AMOLED themes.
- CSRF tokens, prepared statements, bcrypt hashes, role-based access.

## Install

```bash
git clone https://github.com/maskalix/PressLine.git
cd PressLine

cp pl-config.example.php pl-config.php
$EDITOR pl-config.php          # or export PL_DB_HOST / PL_DB_USER / PL_DB_PASS / PL_DB_NAME

# Open /instalace/ in your browser, then change the default password.
chown -R www-data:www-data plugins
```

Default login: `pressline / pressline`. Change it immediately.

Requirements: PHP 8.0+ with `gd`, `curl`, `zip`, `mysqli`, `mbstring`. MySQL 8.0+ or MariaDB 10.5+. HTTPS for the marketplace client.

## Plugin SDK

A plugin is a folder in `/plugins/<id>/` with a `plugin.json` and an entry script.

```php
<?php
PL_Plugin::register('hello-world')
    ->onArticleSave(function ($article, $id, $action) {
        error_log("Article #$id saved: " . ($article['name'] ?? '?'));
    })
    ->addAdminMenuItem('Hello', './plugins/hello-world/page.php', 'ic:round-waving-hand', 2)
    ->addDashboardCard(function () {
        echo '<section class="dashboard-block"><h2>Hello!</h2></section>';
    });
```

Every entity (`article`, `category`, `media`, `user`, `preference`, `role`, `webset`) fires `beforeSave`, `afterSave`, `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`. Specials: `media.afterUpload`, `user.afterLogin`, `article.content`, `admin.menu`, `admin.dashboardCards`, `editor.toolbarButtons`.

Migrations live in `<plugin>/migrations/*.sql` and run on first activation.

## Marketplace

Open **Rozšíření → Marketplace** in the admin. Plugins come from [marketplace.pressline.app](https://marketplace.pressline.app), are downloaded with a sha256 check, extracted atomically, and have their migrations run on install. You can self-host the registry by changing `marketplace_url` in settings.

## Project layout

```
*.php           Top-level admin pages
ajax/           XHR endpoints (CSRF-verified)
includes/       Bootstrap, hooks, plugin loader, marketplace, fetcher/setter
plugins/        Drop-in plugin folders
res/            CSS and JS
media/          User uploads (gitignored)
version.php     Single source of truth for $version
```

## Security

CSRF token on every POST. Prepared statements everywhere. Bcrypt password hashes. MIME and extension whitelists on uploads. The plugin extractor refuses paths with `..`, leading `/`, or `.git/`. Hard 5 MB compressed / 25 MB extracted limits on plugin packages.

## Contributing

Run `php -l` on changed files. Test on a clean install. Add a `migrations/*.sql` if you touch the schema. Bump `version.php` (semver).

## Licence

[MIT](LICENSE) © 2025–present PressLine contributors.

PressLine is built by [LNLN.eu](https://lnln.eu).

---

<div align="center">

## Česky

Malý český CMS s opravdovým plugin SDK. PHP 8, MySQL 8. Pod 5 MB. Bez JavaScriptového frameworku.

[pressline.app](https://pressline.app) ·
[živá ukázka](https://admin.pressline.app) ·
[marketplace](https://marketplace.pressline.app) ·
[dokumentace](https://pressline.app/docs.php)

</div>

### Co to je

Self-hostovaný CMS pro lidi, kterým je WordPress velký a Ghost moc chytrý. Články, média, uživatelé, pluginy, marketplace. Nic jiného. MIT licence. Hooky a filtry, plugin SDK, vestavěný marketplace. Light, dark a AMOLED téma. CSRF, prepared statements, bcrypt, role.

### Instalace

```bash
git clone https://github.com/maskalix/PressLine.git
cd PressLine
cp pl-config.example.php pl-config.php
$EDITOR pl-config.php
# Otevři /instalace/ v prohlížeči a hned změň heslo.
chown -R www-data:www-data plugins
```

Výchozí přihlášení: `pressline / pressline`. Hned po instalaci změň.

### Plugin

```php
<?php
PL_Plugin::register('ahoj-svete')
    ->onArticleSave(function ($article, $id, $action) {
        error_log("Článek #$id uložen: " . ($article['name'] ?? '?'));
    })
    ->addAdminMenuItem('Ahoj', './plugins/ahoj-svete/page.php', 'ic:round-waving-hand', 2);
```

Obnov `/rozsireni.php` a klikni na **Aktivovat**.

### Licence

[MIT](LICENSE) © 2025–současnost přispěvatelé PressLine. Vyrobeno v [LNLN.eu](https://lnln.eu).
