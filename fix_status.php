<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(20) DEFAULT 'aktif'");
    echo "Success: status modified to VARCHAR(20)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
