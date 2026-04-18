<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (isset($_GET['id_bidang'])) {
    $id_bidang = $_GET['id_bidang'];
    
    $stmt = $pdo->prepare("SELECT id_seksi, nama_seksi FROM seksi WHERE id_bidang = ?");
    $stmt->execute([$id_bidang]);
    $seksi_list = $stmt->fetchAll();
    
    echo json_encode($seksi_list);
} else {
    echo json_encode([]);
}
?>
