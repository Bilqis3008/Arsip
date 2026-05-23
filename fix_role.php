<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL");
    echo "Success: role modified to VARCHAR(50)\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
