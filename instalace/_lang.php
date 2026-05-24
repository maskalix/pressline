<?php
/**
 * Installer-local i18n. Self-contained: doesn't depend on the main
 * includes/lang.php because the installer runs before pl-config has DB
 * credentials, before pl-load can boot, and before websets exist.
 *
 * Language is chosen on the welcome step and stashed in $_SESSION so it
 * persists across redirects.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$INSTALL_LANGS = ['cs' => 'Čeština', 'en' => 'English', 'de' => 'Deutsch'];

if (isset($_GET['lang']) && isset($INSTALL_LANGS[$_GET['lang']])) {
    $_SESSION['install_lang'] = $_GET['lang'];
}
$INSTALL_LANG = $_SESSION['install_lang'] ?? 'cs';
if (!isset($INSTALL_LANGS[$INSTALL_LANG])) $INSTALL_LANG = 'cs';

$INSTALL_STRINGS = [
    'cs' => [
        'page.title'          => 'Instalace',
        'page.brand'          => 'PressLine instalátor',
        'lang.label'          => 'Jazyk',

        'step.welcome'        => 'Vítej',
        'step.database'       => 'Databáze',
        'step.info'           => 'Web',
        'step.img'            => 'Loga',
        'step.smtp'           => 'SMTP',
        'step.webset'         => 'Nastavení',
        'step.exit'           => 'Hotovo',

        'common.continue'     => 'pokračovat',
        'common.skip'         => 'přeskočit',
        'common.back'         => 'zpět',
        'common.retry'        => 'Zkusit znovu',

        'welcome.heading'     => 'Vítej v PressLine instalátoru',
        'welcome.lead'        => 'Tento průvodce tě provede základním nastavením. Trvá to pár minut.',
        'welcome.requirements'=> 'Před instalací zkontroluj',
        'welcome.req.permissions' => 'Práva www-data (či alternativní uživatel webserveru)',
        'welcome.req.dir.install' => 'Adresář <code>instalace/</code>',
        'welcome.req.dir.root'    => 'Kořenový adresář PressLine',
        'welcome.req.mysql'   => 'MySQL databáze s plnými právy',
        'welcome.req.smtp'    => 'SMTP údaje (volitelné, lze nastavit později)',
        'welcome.req.ok'      => 'OK',
        'welcome.req.fail'    => 'chybí',
        'welcome.req.unknown' => 'nelze ověřit',

        'db.heading'          => 'Připojení k databázi',
        'db.lead'             => 'Zadej údaje k vytvořené MySQL databázi. Údaje uložíme do <code>config.default.php</code>.',
        'db.host'             => 'Hostitel',
        'db.user'             => 'Uživatel',
        'db.pass'             => 'Heslo',
        'db.name'             => 'Název databáze',
        'db.collate'          => 'Řazení (collation)',
        'db.collate.cz'       => 'Czech (utf8mb4_czech_ci) — doporučeno pro češtinu',
        'db.collate.universal'=> 'Universal (utf8mb4_0900_ai_ci) — doporučeno jinak',
        'db.drop'             => 'Vyčistit databázi (smazat všechny existující tabulky)',
        'db.test'             => 'Otestovat spojení',
        'db.ok'               => 'Spojení úspěšně navázáno',
        'db.create'           => 'Vytvořit schéma',
        'db.fail'             => 'Připojení k databázi se nezdařilo',

        'info.heading'        => 'Informace o webu',
        'info.lead'            => 'Tyto údaje se zobrazují v hlavičce administrace, v <code>&lt;title&gt;</code> a v meta tagách.',
        'info.name'            => 'Název webu',
        'info.url'             => 'URL administrace',
        'info.frontend'        => 'URL webu (frontend)',
        'info.description'     => 'Popisek',
        'info.keywords'        => 'Klíčová slova (oddělené čárkami)',
        'info.language'        => 'Výchozí jazyk webu',

        'img.heading'         => 'Loga a ikony',
        'img.lead'            => 'Cesty k ikonám relativně k rootu PressLine. Můžeš změnit kdykoliv v Nastavení.',
        'img.icon'            => 'Favicon',
        'img.icon_full'       => 'Logo (čtvercové)',
        'img.icon_text'       => 'Logo s textem',
        'img.light'           => 'Světlý režim',
        'img.dark'            => 'Tmavý režim',

        'smtp.heading'        => 'SMTP server',
        'smtp.lead'           => 'Pro odesílání emailů (reset hesla apod.). Lze přeskočit a doplnit později.',
        'smtp.user'           => 'Odesílatel (e-mail)',
        'smtp.pass'           => 'Heslo',
        'smtp.host'           => 'SMTP host',
        'smtp.port'           => 'Port',
        'smtp.sec'            => 'Zabezpečení',

        'webset.heading'      => 'Vytváření výchozích nastavení',
        'webset.lead'         => 'Hodnoty z předchozích kroků se nahrávají do tabulky <code>webset</code>.',
        'webset.applied'      => 'Nastavení aplikováno',

        'exit.heading'        => 'Instalace dokončena',
        'exit.lead'           => 'Pro bezpečnost klikni na "Dokončit instalaci" — vytvoří se soubor <code>.installed</code> a další pokus o instalaci se zablokuje.',
        'exit.creds'          => 'Výchozí přihlašovací údaje',
        'exit.username'       => 'Uživatel',
        'exit.password'       => 'Heslo',
        'exit.warn'           => 'Po prvním přihlášení změň heslo!',
        'exit.finish'         => 'Dokončit instalaci',
        'exit.cleanup_intro'  => 'Po dokončení smaž tento adresář <code>instalace/</code> ručně, nebo nech instalátor uzamknout.',
        'exit.blocked'        => 'Nelze dokončit — chybí povinné kroky:',
        'required.badge'      => 'povinné',
        'required.missing'    => 'Tento krok je povinný — vyplň ho před dokončením instalace.',

        'lock.heading'        => 'Instalace uzamčena',
        'lock.body'           => 'Pro novou instalaci smaž soubor <code>.installed</code> v rootu PressLine.',
        'lock.debug_hint'     => 'Nebo zapni <code>define(\'DEBUG\', true)</code> v <code>pl-config.php</code> pro simulační režim, který zámek obejde.',
        'error.no_post'       => 'Neobdržena POST data',
        'error.create_tables' => 'Chyba při aplikaci schématu',
        'error.connection'    => 'Připojení k databázi se nezdařilo',
        'error.session'       => 'Session vypršela. Začni od začátku.',
        'error.csrf'          => 'CSRF token neplatný — obnov stránku.',

        'category.default'    => 'nezařazené',

        'debug.banner'        => 'DEBUG / SIMULACE',
        'debug.lead'          => 'Žádné soubory ani SQL nebyly skutečně provedeny.',
        'debug.clear'         => 'vymazat log',
        'debug.empty'         => 'V tomto kroku zatím žádné akce.',
    ],
    'en' => [
        'page.title'          => 'Installer',
        'page.brand'          => 'PressLine installer',
        'lang.label'          => 'Language',

        'step.welcome'        => 'Welcome',
        'step.database'       => 'Database',
        'step.info'           => 'Site',
        'step.img'            => 'Logos',
        'step.smtp'           => 'SMTP',
        'step.webset'         => 'Settings',
        'step.exit'           => 'Done',

        'common.continue'     => 'continue',
        'common.skip'         => 'skip',
        'common.back'         => 'back',
        'common.retry'        => 'Try again',

        'welcome.heading'     => 'Welcome to the PressLine installer',
        'welcome.lead'        => 'This wizard walks you through the basic setup. Takes a few minutes.',
        'welcome.requirements'=> 'Before installing',
        'welcome.req.permissions' => 'www-data permissions (or your webserver user)',
        'welcome.req.dir.install' => 'Installer directory <code>instalace/</code>',
        'welcome.req.dir.root'    => 'PressLine root directory',
        'welcome.req.mysql'   => 'A MySQL database with full privileges',
        'welcome.req.smtp'    => 'SMTP credentials (optional, can set up later)',
        'welcome.req.ok'      => 'OK',
        'welcome.req.fail'    => 'missing',
        'welcome.req.unknown' => 'unknown',

        'db.heading'          => 'Database connection',
        'db.lead'             => 'Enter your MySQL credentials. These will be saved into <code>config.default.php</code>.',
        'db.host'             => 'Host',
        'db.user'             => 'User',
        'db.pass'             => 'Password',
        'db.name'             => 'Database name',
        'db.collate'          => 'Collation',
        'db.collate.cz'       => 'Czech (utf8mb4_czech_ci) — recommended for Czech',
        'db.collate.universal'=> 'Universal (utf8mb4_0900_ai_ci) — recommended otherwise',
        'db.drop'             => 'Wipe database (drop all existing tables)',
        'db.test'             => 'Test connection',
        'db.ok'               => 'Connection succeeded',
        'db.create'           => 'Create schema',
        'db.fail'             => 'Database connection failed',

        'info.heading'        => 'Site information',
        'info.lead'            => 'Used in the admin header, in <code>&lt;title&gt;</code>, and in meta tags.',
        'info.name'            => 'Site name',
        'info.url'             => 'Admin URL',
        'info.frontend'        => 'Site URL (frontend)',
        'info.description'     => 'Description',
        'info.keywords'        => 'Keywords (comma-separated)',
        'info.language'        => 'Default site language',

        'img.heading'         => 'Logos and icons',
        'img.lead'            => 'Paths relative to the PressLine root. You can change these later in Settings.',
        'img.icon'            => 'Favicon',
        'img.icon_full'       => 'Logo (square)',
        'img.icon_text'       => 'Logo with text',
        'img.light'           => 'Light mode',
        'img.dark'            => 'Dark mode',

        'smtp.heading'        => 'SMTP server',
        'smtp.lead'           => 'For sending mail (password reset, etc.). You can skip and configure later.',
        'smtp.user'           => 'Sender (email)',
        'smtp.pass'           => 'Password',
        'smtp.host'           => 'SMTP host',
        'smtp.port'           => 'Port',
        'smtp.sec'            => 'Security',

        'webset.heading'      => 'Creating default settings',
        'webset.lead'         => 'Values from previous steps are loaded into the <code>webset</code> table.',
        'webset.applied'      => 'Settings applied',

        'exit.heading'        => 'Installation complete',
        'exit.lead'           => 'For safety, click "Finish installation" — this creates a <code>.installed</code> file and blocks any further install attempts.',
        'exit.creds'          => 'Default login credentials',
        'exit.username'       => 'Username',
        'exit.password'       => 'Password',
        'exit.warn'           => 'Change the password right after the first login!',
        'exit.finish'         => 'Finish installation',
        'exit.cleanup_intro'  => 'After finishing, delete the <code>instalace/</code> directory manually, or let the installer lock things.',
        'exit.blocked'        => 'Cannot finish — required steps still missing:',
        'required.badge'      => 'required',
        'required.missing'    => 'This step is required — please complete it before finishing installation.',

        'lock.heading'        => 'Installation locked',
        'lock.body'           => 'To reinstall, delete the <code>.installed</code> file in the PressLine root.',
        'lock.debug_hint'     => 'Or set <code>define(\'DEBUG\', true)</code> in <code>pl-config.php</code> for simulation mode, which bypasses the lock.',
        'error.no_post'       => 'No POST data received',
        'error.create_tables' => 'Error applying schema',
        'error.connection'    => 'Database connection failed',
        'error.session'       => 'Session expired. Please start over.',
        'error.csrf'          => 'Invalid CSRF token — refresh and try again.',

        'category.default'    => 'uncategorized',

        'debug.banner'        => 'DEBUG / SIMULATION',
        'debug.lead'          => 'No files were written, no SQL was executed.',
        'debug.clear'         => 'clear log',
        'debug.empty'         => 'No actions intercepted on this step yet.',
    ],
    'de' => [
        'page.title'          => 'Installation',
        'page.brand'          => 'PressLine-Installer',
        'lang.label'          => 'Sprache',

        'step.welcome'        => 'Willkommen',
        'step.database'       => 'Datenbank',
        'step.info'           => 'Website',
        'step.img'            => 'Logos',
        'step.smtp'           => 'SMTP',
        'step.webset'         => 'Einstellungen',
        'step.exit'           => 'Fertig',

        'common.continue'     => 'weiter',
        'common.skip'         => 'überspringen',
        'common.back'         => 'zurück',
        'common.retry'        => 'Erneut versuchen',

        'welcome.heading'     => 'Willkommen zum PressLine-Installer',
        'welcome.lead'        => 'Dieser Assistent führt dich durch die Grundeinrichtung. Dauert wenige Minuten.',
        'welcome.requirements'=> 'Vor der Installation prüfen',
        'welcome.req.permissions' => 'Berechtigungen für www-data (oder den Webserver-Benutzer)',
        'welcome.req.dir.install' => 'Installations-Verzeichnis <code>instalace/</code>',
        'welcome.req.dir.root'    => 'PressLine-Root-Verzeichnis',
        'welcome.req.mysql'   => 'MySQL-Datenbank mit vollen Rechten',
        'welcome.req.smtp'    => 'SMTP-Daten (optional, später möglich)',
        'welcome.req.ok'      => 'OK',
        'welcome.req.fail'    => 'fehlt',
        'welcome.req.unknown' => 'unbekannt',

        'db.heading'          => 'Datenbankverbindung',
        'db.lead'             => 'Gib deine MySQL-Zugangsdaten ein. Sie werden in <code>config.default.php</code> gespeichert.',
        'db.host'             => 'Host',
        'db.user'             => 'Benutzer',
        'db.pass'             => 'Passwort',
        'db.name'             => 'Datenbankname',
        'db.collate'          => 'Sortierung (Collation)',
        'db.collate.cz'       => 'Tschechisch (utf8mb4_czech_ci) — empfohlen für Tschechisch',
        'db.collate.universal'=> 'Universell (utf8mb4_0900_ai_ci) — sonst empfohlen',
        'db.drop'             => 'Datenbank leeren (alle bestehenden Tabellen löschen)',
        'db.test'             => 'Verbindung testen',
        'db.ok'               => 'Verbindung erfolgreich',
        'db.create'           => 'Schema erstellen',
        'db.fail'             => 'Datenbankverbindung fehlgeschlagen',

        'info.heading'        => 'Website-Informationen',
        'info.lead'            => 'Wird im Admin-Header, im <code>&lt;title&gt;</code> und in Meta-Tags verwendet.',
        'info.name'            => 'Website-Name',
        'info.url'             => 'Admin-URL',
        'info.frontend'        => 'Website-URL (Frontend)',
        'info.description'     => 'Beschreibung',
        'info.keywords'        => 'Schlüsselwörter (durch Komma getrennt)',
        'info.language'        => 'Standard-Sprache der Website',

        'img.heading'         => 'Logos und Icons',
        'img.lead'            => 'Pfade relativ zum PressLine-Root. Können später in den Einstellungen geändert werden.',
        'img.icon'            => 'Favicon',
        'img.icon_full'       => 'Logo (quadratisch)',
        'img.icon_text'       => 'Logo mit Text',
        'img.light'           => 'Heller Modus',
        'img.dark'            => 'Dunkler Modus',

        'smtp.heading'        => 'SMTP-Server',
        'smtp.lead'           => 'Für E-Mail-Versand (Passwort-Reset usw.). Kann übersprungen und später konfiguriert werden.',
        'smtp.user'           => 'Absender (E-Mail)',
        'smtp.pass'           => 'Passwort',
        'smtp.host'           => 'SMTP-Host',
        'smtp.port'           => 'Port',
        'smtp.sec'            => 'Sicherheit',

        'webset.heading'      => 'Standard-Einstellungen werden erstellt',
        'webset.lead'         => 'Werte aus den vorherigen Schritten werden in die <code>webset</code>-Tabelle geladen.',
        'webset.applied'      => 'Einstellungen angewendet',

        'exit.heading'        => 'Installation abgeschlossen',
        'exit.lead'           => 'Aus Sicherheitsgründen klicke auf "Installation abschließen" — eine <code>.installed</code>-Datei wird angelegt und weitere Installationsversuche werden blockiert.',
        'exit.creds'          => 'Standard-Zugangsdaten',
        'exit.username'       => 'Benutzer',
        'exit.password'       => 'Passwort',
        'exit.warn'           => 'Ändere das Passwort direkt nach der ersten Anmeldung!',
        'exit.finish'         => 'Installation abschließen',
        'exit.cleanup_intro'  => 'Nach dem Abschluss lösche das Verzeichnis <code>instalace/</code> manuell, oder lass den Installer es sperren.',
        'exit.blocked'        => 'Abschluss nicht möglich — fehlende Pflichtschritte:',
        'required.badge'      => 'erforderlich',
        'required.missing'    => 'Dieser Schritt ist erforderlich — bitte vor dem Abschluss ausfüllen.',

        'lock.heading'        => 'Installation gesperrt',
        'lock.body'           => 'Zum Neuinstallieren lösche die Datei <code>.installed</code> im PressLine-Root.',
        'lock.debug_hint'     => 'Oder setze <code>define(\'DEBUG\', true)</code> in <code>pl-config.php</code> für den Simulationsmodus, der die Sperre umgeht.',
        'error.no_post'       => 'Keine POST-Daten empfangen',
        'error.create_tables' => 'Fehler beim Anwenden des Schemas',
        'error.connection'    => 'Datenbankverbindung fehlgeschlagen',
        'error.session'       => 'Session abgelaufen. Bitte von vorn beginnen.',
        'error.csrf'          => 'Ungültiges CSRF-Token — bitte Seite neu laden.',

        'category.default'    => 'unsortiert',

        'debug.banner'        => 'DEBUG / SIMULATION',
        'debug.lead'          => 'Es wurden keine Dateien geschrieben und kein SQL ausgeführt.',
        'debug.clear'         => 'Log leeren',
        'debug.empty'         => 'In diesem Schritt wurden noch keine Aktionen abgefangen.',
    ],
];

function ti(string $key, ...$args): string {
    global $INSTALL_STRINGS, $INSTALL_LANG;
    $str = $INSTALL_STRINGS[$INSTALL_LANG][$key]
        ?? $INSTALL_STRINGS['cs'][$key]
        ?? $key;
    if (!$args) return $str;
    $out = @vsprintf($str, $args);
    return $out === false ? $str : $out;
}
function tie(string $key, ...$args): string {
    return htmlspecialchars(ti($key, ...$args), ENT_QUOTES, 'UTF-8');
}
