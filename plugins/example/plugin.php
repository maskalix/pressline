<?php
/**
 * Example PressLine Plugin
 *
 * Demonstrates the plugin SDK by:
 *   - Logging article saves to error_log
 *   - Adding a custom item to the admin sidebar
 *   - Adding a card to the dashboard
 *   - Registering its UI strings into the i18n system
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

pl_lang_register([
    'cs' => [
        'plugin.example.menu'         => 'Example',
        'plugin.example.card.title'   => 'Example plugin',
        'plugin.example.card.body'    => 'Tento panel přidává Example plugin. Můžeš sem dát cokoliv — statistiku, posledních 5 zákazníků, link na externí službu, atd.',
        'plugin.example.page.title'   => 'Example Plugin',
        'plugin.example.page.subtitle'=> 'Stránka, kterou plugin přidal do administrace.',
        'plugin.example.page.greeting'=> 'Ahoj!',
        'plugin.example.page.body'    => 'Toto je vlastní stránka pluginu. Plugin si může do <code>/plugins/&lt;id&gt;/</code> dát libovolný PHP kód.',
        'plugin.example.page.path'    => 'Tato stránka je dostupná na <code>./plugins/example/page.php</code>.',
    ],
    'en' => [
        'plugin.example.menu'         => 'Example',
        'plugin.example.card.title'   => 'Example plugin',
        'plugin.example.card.body'    => 'This panel is added by the Example plugin. You can put anything here — stats, the last 5 customers, an external link, etc.',
        'plugin.example.page.title'   => 'Example Plugin',
        'plugin.example.page.subtitle'=> 'A page the plugin added to the admin.',
        'plugin.example.page.greeting'=> 'Hello!',
        'plugin.example.page.body'    => 'This is the plugin\'s own page. A plugin can drop any PHP code into <code>/plugins/&lt;id&gt;/</code>.',
        'plugin.example.page.path'    => 'This page is served at <code>./plugins/example/page.php</code>.',
    ],
    'de' => [
        'plugin.example.menu'         => 'Beispiel',
        'plugin.example.card.title'   => 'Beispiel-Plugin',
        'plugin.example.card.body'    => 'Dieses Panel wird vom Beispiel-Plugin hinzugefügt. Du kannst hier alles unterbringen — Statistiken, die letzten 5 Kunden, einen externen Link usw.',
        'plugin.example.page.title'   => 'Beispiel-Plugin',
        'plugin.example.page.subtitle'=> 'Eine Seite, die das Plugin der Administration hinzugefügt hat.',
        'plugin.example.page.greeting'=> 'Hallo!',
        'plugin.example.page.body'    => 'Dies ist die eigene Seite des Plugins. Ein Plugin kann beliebigen PHP-Code in <code>/plugins/&lt;id&gt;/</code> ablegen.',
        'plugin.example.page.path'    => 'Diese Seite ist erreichbar unter <code>./plugins/example/page.php</code>.',
    ],
]);

PL_Plugin::register('example')

    // Log every article save
    ->onArticleSave(function ($data, $id, $action) {
        $name = $data['name'] ?? '(no name)';
        error_log("[example plugin] article.afterSave action=$action id=$id name=$name");
    })

    // Log media uploads
    ->onMediaUpload(function ($info) {
        error_log("[example plugin] media.afterUpload filename=" . ($info['filename'] ?? '?'));
    })

    // Custom sidebar menu item (admin-only)
    ->addAdminMenuItem(
        t('plugin.example.menu'),
        pl_url('/plugins/example/page.php'),
        'ic:round-extension',
        2 // min level: admin
    )

    // Dashboard card
    ->addDashboardCard(function () {
        ?>
        <section class="dashboard-block">
            <header class="dashboard-block-header">
                <h2>
                    <span class="iconify-inline" data-icon="ic:round-extension"></span>
                    <?= esc(t('plugin.example.card.title')) ?>
                </h2>
            </header>
            <p style="color: var(--text-muted); margin: 0;">
                <?= esc(t('plugin.example.card.body')) ?>
            </p>
        </section>
        <?php
    });
