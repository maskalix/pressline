<?php
/**
 * PressLine canonical schema.
 *
 * Single source of truth for every table + seed row the application needs.
 * Returns an array of named SQL operations that can be replayed on a blank
 * database (installer) or used to detect drift on an existing one
 * (activity_log_init.php, preferences_migrate.php, future migrators).
 *
 * Each operation is independent: drop checks → create-if-not-exists →
 * seed-only-on-create. No DROP TABLE statements; the installer handles
 * wipe explicitly when the user opts in.
 *
 * Args:
 *   $collation — utf8mb4_czech_ci or utf8mb4_0900_ai_ci
 *   $defaultCategory — localized name for the seeded "uncategorized" row
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

function pl_schema(string $collation, string $defaultCategory): array {
    $c = $collation;
    return [
        'tables' => [
            'articles' => "CREATE TABLE IF NOT EXISTS `articles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `slug` text NOT NULL,
                `name` varchar(255) NOT NULL,
                `content` text,
                `time` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `picture` varchar(255) DEFAULT NULL,
                `author` varchar(255) DEFAULT NULL,
                `tags` text,
                `category` int DEFAULT '1',
                `public` enum('1','0') NOT NULL DEFAULT '1',
                `deletable` enum('0','1') NOT NULL DEFAULT '1',
                `level` int DEFAULT '0',
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'categories' => "CREATE TABLE IF NOT EXISTS `categories` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'media' => "CREATE TABLE IF NOT EXISTS `media` (
                `id` int NOT NULL AUTO_INCREMENT,
                `filename` varchar(255) NOT NULL,
                `name` text,
                `description` text,
                `author` text,
                `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'preferences' => "CREATE TABLE IF NOT EXISTS `preferences` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `mode` varchar(255) NOT NULL,
                `color` varchar(255) NOT NULL,
                `language` varchar(8) NOT NULL DEFAULT 'cs',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_id` (`user_id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'users' => "CREATE TABLE IF NOT EXISTS `users` (
                `id` int NOT NULL AUTO_INCREMENT,
                `username` varchar(255) DEFAULT NULL,
                `password` varchar(255) NOT NULL,
                `mail` varchar(255) DEFAULT NULL,
                `name` varchar(255) DEFAULT NULL,
                `surname` varchar(255) DEFAULT NULL,
                `img` varchar(255) DEFAULT NULL,
                `story` varchar(255) DEFAULT NULL,
                `social_ig` varchar(255) DEFAULT NULL,
                `social_x` varchar(255) DEFAULT NULL,
                `social_fb` varchar(255) DEFAULT NULL,
                `role` int NOT NULL DEFAULT '1',
                `reset_token` text,
                `reset_token_expires` text,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'roles' => "CREATE TABLE IF NOT EXISTS `roles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) DEFAULT NULL,
                `level` int NOT NULL DEFAULT '1',
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'webset' => "CREATE TABLE IF NOT EXISTS `webset` (
                `key` VARCHAR(255) PRIMARY KEY,
                `value` TEXT
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'activity_log' => "CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NULL,
                `username` VARCHAR(64) NULL,
                `action` VARCHAR(32) NOT NULL,
                `type` VARCHAR(32) NULL,
                `target_id` INT UNSIGNED NULL,
                `payload` TEXT NULL,
                `ip` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_created` (`created_at`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_type_action` (`type`, `action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$c",

            'plugins' => "CREATE TABLE IF NOT EXISTS `plugins` (
                `id` VARCHAR(64) PRIMARY KEY,
                `version` VARCHAR(32) NOT NULL,
                `status` VARCHAR(16) NOT NULL DEFAULT 'installed',
                `installed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `migrations_run` TEXT NULL
            ) DEFAULT CHARSET=utf8mb4 COLLATE=$c",
        ],

        // Seed rows: only inserted if the target table is empty for that scope.
        'seeds' => [
            'roles' => [
                "INSERT INTO `roles` (`name`, `level`) VALUES ('user', 1)",
                "INSERT INTO `roles` (`name`, `level`) VALUES ('admin', 2)",
                "INSERT INTO `roles` (`name`, `level`) VALUES ('dev', 3)",
            ],
            'users' => [
                // password = 'pressline24' (bcrypt hash kept consistent with previous installer)
                "INSERT INTO `users` (`username`, `password`, `mail`, `name`, `surname`, `role`)
                 VALUES ('pressline', '\$2y\$10\$25xdr4KEeQVxhRPuWP7ihO42CP0tkRtAxUgzuDU/4dPAYdqssBN5O', '', 'PressLine', 'Default', 2)",
            ],
            'categories' => [
                // Seeded with the user's chosen installer language label.
                "INSERT INTO `categories` (`name`) VALUES ('" . addslashes($defaultCategory) . "')",
            ],
        ],

        // Foreign keys applied last (after seeds so referenced rows exist).
        'constraints' => [
            "ALTER TABLE `articles` ADD CONSTRAINT `fk_articles_category`
                FOREIGN KEY (`category`) REFERENCES `categories` (`id`)",
        ],
    ];
}

/**
 * Run the schema against $conn. Returns ['ok' => bool, 'errors' => string[],
 * 'log' => string[]] — caller can decide whether to abort or display.
 *
 * Idempotent: tables use IF NOT EXISTS, seeds only INSERT when scope is empty,
 * the FK is checked before adding.
 */
function pl_schema_apply(mysqli $conn, array $schema): array {
    $errors = [];
    $log = [];

    foreach ($schema['tables'] as $name => $sql) {
        if (@$conn->query($sql) === false) {
            $errors[] = "table `$name`: " . $conn->error;
        } else {
            $log[] = "table `$name` ensured";
        }
    }

    foreach ($schema['seeds'] as $table => $rows) {
        $count = 0;
        if ($res = @$conn->query("SELECT COUNT(*) AS c FROM `$table`")) {
            $count = (int)($res->fetch_assoc()['c'] ?? 0);
        }
        if ($count > 0) {
            $log[] = "seed `$table` skipped ($count existing rows)";
            continue;
        }
        foreach ($rows as $row) {
            if (@$conn->query($row) === false) {
                $errors[] = "seed `$table`: " . $conn->error;
            } else {
                $log[] = "seed `$table` row inserted";
            }
        }
    }

    foreach ($schema['constraints'] as $sql) {
        // Check whether the constraint already exists; MySQL doesn't have
        // ADD CONSTRAINT IF NOT EXISTS in older versions.
        if (preg_match('/CONSTRAINT `(\w+)`/', $sql, $m)) {
            $name = $m[1];
            $check = @$conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                                      AND CONSTRAINT_NAME = '$name'");
            if ($check && $check->num_rows > 0) {
                $log[] = "constraint `$name` already present";
                continue;
            }
        }
        if (@$conn->query($sql) === false) {
            $errors[] = "constraint: " . $conn->error;
        } else {
            $log[] = "constraint applied";
        }
    }

    return [
        'ok'     => empty($errors),
        'errors' => $errors,
        'log'    => $log,
    ];
}
