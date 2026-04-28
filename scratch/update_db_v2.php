<?php
require_once 'config/db.php';

try {
    // Check if name_tag column exists
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'name_tag'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name_tag VARCHAR(255) AFTER foto");
        echo "Column 'name_tag' added.\n";
    }

    // Check if status column exists and modify it to include 'pending'
    // Actually the existing 'status' column is ENUM('aktif', 'nonaktif') in manajemen_pengguna.php (line 30)
    // Wait, setup.sql didn't have 'status' column in my previous view.
    // Let's check users columns again.
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('pending', 'aktif', 'nonaktif') DEFAULT 'aktif' AFTER role");
        echo "Column 'status' added.\n";
    } else {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'aktif', 'nonaktif') DEFAULT 'aktif'");
        echo "Column 'status' updated.\n";
    }

    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
