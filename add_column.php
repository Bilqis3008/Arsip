<?php
require_once 'c:/laragon/www/Arsip/config/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN asal_instansi VARCHAR(255) NULL AFTER no_hp");
    echo "Column asal_instansi added successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
