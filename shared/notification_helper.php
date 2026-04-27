<?php

if (!function_exists('addNotification')) {
    function addNotification($pdo, $nip, $message, $link = '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (nip, message, link) VALUES (?, ?, ?)");
            return $stmt->execute([$nip, $message, $link]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('notifyKadin')) {
    function notifyKadin($pdo, $message, $link = '') {
        $stmt = $pdo->query("SELECT nip FROM users WHERE role = 'kepala_dinas' LIMIT 1");
        $kadin = $stmt->fetch();
        if ($kadin) {
            return addNotification($pdo, $kadin['nip'], $message, $link);
        }
        return false;
    }
}

if (!function_exists('notifyAdminBidang')) {
    function notifyAdminBidang($pdo, $id_bidang, $message, $link = '') {
        $stmt = $pdo->prepare("SELECT nip FROM users WHERE role = 'admin_bidang' AND id_bidang = ?");
        $stmt->execute([$id_bidang]);
        $admins = $stmt->fetchAll();
        foreach ($admins as $admin) {
            addNotification($pdo, $admin['nip'], $message, $link);
        }
        return true;
    }
}

if (!function_exists('notifyStaffSeksi')) {
    function notifyStaffSeksi($pdo, $id_seksi, $message, $link = '') {
        $stmt = $pdo->prepare("SELECT nip FROM users WHERE role = 'staff' AND id_seksi = ?");
        $stmt->execute([$id_seksi]);
        $staffs = $stmt->fetchAll();
        foreach ($staffs as $staff) {
            addNotification($pdo, $staff['nip'], $message, $link);
        }
        return true;
    }
}

if (!function_exists('notifySekretariat')) {
    function notifySekretariat($pdo, $message, $link = '') {
        $stmt = $pdo->query("SELECT nip FROM users WHERE role = 'sekretariat'");
        $admins = $stmt->fetchAll();
        foreach ($admins as $admin) {
            addNotification($pdo, $admin['nip'], $message, $link);
        }
        return true;
    }
}
