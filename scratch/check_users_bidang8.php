<?php
require_once 'config/db.php';
$stmt = $pdo->query('SELECT nip, nama, role FROM users WHERE id_bidang = 8');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
