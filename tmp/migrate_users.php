<?php
require_once 'c:/laragon/www/Arsip/config/db.php';

try {
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('aktif', 'nonaktif') DEFAULT 'aktif'");
        echo "Migration successful: status column added.";
    } else {
        echo "Migration skipped: status column already exists.";
    }
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
