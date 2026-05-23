<?php
require_once 'config/db.php';
try {
    // 1. Insert Sekretariat if not exists
    $stmt = $pdo->prepare("SELECT id_bidang FROM bidang WHERE id_bidang = 8 OR nama_bidang = 'Sekretariat'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO bidang (id_bidang, nama_bidang) VALUES (8, 'Sekretariat')")->execute();
        echo "Inserted Sekretariat (ID 8)\n";
    } else {
        echo "Sekretariat already exists\n";
    }

    // 2. Update Bila's id_bidang
    $pdo->prepare("UPDATE users SET id_bidang = 8 WHERE role = 'sekretariat'")->execute();
    echo "Updated Bila's id_bidang to 8\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
