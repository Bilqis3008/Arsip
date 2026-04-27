<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_nip'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$nip = $_SESSION['user_nip'];
$action = $_GET['action'] ?? 'fetch';

if ($action === 'fetch') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE nip = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$nip]);
    $notifications = $stmt->fetchAll();

    $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE nip = ? AND status = 'unread'");
    $stmt_unread->execute([$nip]);
    $unread_count = $stmt_unread->fetchColumn();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count
    ]);
} elseif ($action === 'mark_read') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE id_notification = ? AND nip = ?");
        $stmt->execute([$id, $nip]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE nip = ?");
        $stmt->execute([$nip]);
    }
    echo json_encode(['success' => true]);
}
