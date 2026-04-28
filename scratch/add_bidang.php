<?php
require 'config/db.php';
try {
    $stmt = $pdo->prepare("INSERT INTO bidang (nama_bidang) VALUES ('Sekretariat Umum')");
    $stmt->execute();
    echo "ID: " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage();
}
?>
