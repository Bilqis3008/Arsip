<?php
require_once 'config/db.php';
$stmt = $pdo->query('SELECT nip, id_bidang, id_seksi FROM users WHERE role = "staff" AND id_bidang = 8');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
