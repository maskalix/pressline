<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_level'] < $dev_level) {
    header("Location: chyba.php?err=403&msg=Nemáte oprávnění k této akci");
    exit();
}

// Get all tables in the database
$tables = array();
$result = $connection->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Initialize SQL variable
$sql = "";

// Loop through each table and format the SQL
foreach ($tables as $table) {
    // Get table structure
    $result = $connection->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    $sql .= "\n\n" . $row[1] . ";\n\n";

    // Get table data
    $result = $connection->query("SELECT * FROM `$table`");
    $columnCount = $result->field_count;

    while ($row = $result->fetch_row()) {
        $sql .= "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            if ($row[$j] === null) {
                $sql .= 'NULL';
            } else {
                $sql .= '"' . $connection->real_escape_string($row[$j]) . '"';
            }
            if ($j < ($columnCount - 1)) {
                $sql .= ',';
            }
        }
        $sql .= ");\n";
    }

    $sql .= "\n";
}

// Close the connection
$connection->close();

// Set headers to download the file
header('Content-Type: application/sql');
$safeUrl = preg_replace('/[^a-zA-Z0-9._-]/', '_', $url);
$safeDbName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $db_name);
header('Content-Disposition: attachment; filename="backup_' . $safeUrl . '_db=' . $safeDbName . '_' . date('Y-m-d_H-i-s') . '.sql"');
header('Content-Length: ' . strlen($sql));

// Output the SQL
echo $sql;
?>
