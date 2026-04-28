<?php
session_start();
require_once '../config/db.php';

// Auth Check for Sekretariat
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$tab = $_GET['tab'] ?? 'pending'; // pending | verified
$search = $_GET['search'] ?? '';

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

// Bidang ID for Sekretariat Umum is 8
$id_bidang_sekretariat = 8;

// --- HANDLE APPROVAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $id_approve = $_POST['approve_id'];
    // Verify that the surat_keluar belongs to staff in Sekretariat Umum (id_bidang = 8)
    $stmt = $pdo->prepare("UPDATE surat_keluar SET status = 'diarsipkan' WHERE id_surat_keluar = ? AND uploaded_by IN (SELECT nip FROM users WHERE id_bidang = ?)");
    if ($stmt->execute([$id_approve, $id_bidang_sekretariat])) {
        // Fetch details for notification
        $stmt_info = $pdo->prepare("SELECT sk.*, u.nip FROM surat_keluar sk JOIN users u ON sk.uploaded_by = u.nip WHERE sk.id_surat_keluar = ?");
        $stmt_info->execute([$id_approve]);
        $sk_info = $stmt_info->fetch();

        // Notification Logic
        require_once '../shared/notification_helper.php';
        if ($sk_info) {
            // Notify Staff
            addNotification($pdo, $sk_info['nip'], "Surat Balasan Anda telah disetujui & diarsipkan: " . $sk_info['perihal'], "../staff/laporan.php");
        }

        header("Location: surat_keluar.php?tab=verified");
        exit;
    }
}

// --- FETCH LIST ---
if ($tab === 'pending') {
    $query = "SELECT sk.*, u.nama as pengirim_staf, s.nama_seksi 
              FROM surat_keluar sk 
              JOIN users u ON sk.uploaded_by = u.nip 
              LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
              WHERE u.id_bidang = ? AND sk.status = 'pending_approval'
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ?) 
              ORDER BY sk.created_at DESC";
} else {
    $query = "SELECT sk.*, u.nama as pengirim_staf, s.nama_seksi 
              FROM surat_keluar sk 
              JOIN users u ON sk.uploaded_by = u.nip 
              LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
              WHERE u.id_bidang = ? AND sk.status IN ('disetujui', 'diarsipkan')
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ?) 
              ORDER BY sk.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$id_bidang_sekretariat, "%$search%", "%$search%"]);
$mails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Surat Keluar - Sekretariat Umum</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .badge-status.status-pending_approval { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-status.status-disetujui { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-status.status-diarsipkan { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Verifikasi Surat Keluar</h1><p style="font-size:0.9rem; color:var(--text-muted);">Verifikasi draf surat dari unit Sekretariat Umum untuk pengarsipan.</p></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="module-tabs">
                <a href="?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Belum Diverifikasi</a>
                <a href="?tab=verified" class="tab-btn <?= $tab === 'verified' ? 'active' : '' ?>"><svg class="icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Riwayat Disetujui</a>
            </div>

            <!-- Search & Control -->
            <div class="table-controls">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal atau nomor surat..." value="<?= htmlspecialchars((string)$search) ?>">
                </form>
            </div>

            <!-- Table Card -->
            <div class="card">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Identitas & Tujuan</th>
                                <th>Penulis Staf</th>
                                <th>Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mails)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada draf surat keluar ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                    <tr>
                                        <td><b><?= date('d/m/Y', strtotime($m['tanggal_surat'] ?? 'now')) ?></b></td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">No: <?= htmlspecialchars($m['nomor_surat_keluar'] ?? '') ?> • Tujuan: <?= htmlspecialchars($m['tujuan'] ?? '-') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($m['pengirim_staf'] ?? '-') ?></td>
                                        <td><span class="badge-status status-<?= $m['status'] ?>"><?= $m['status'] === 'pending_approval' ? 'Draf Selesai' : 'Diarsipkan' ?></span></td>
                                        <td>
                                            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                                                <?php if ($m['file_path']): ?>
                                                    <a href="../uploads/surat_keluar/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Preview Dokumen"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                                                <?php endif; ?>
                                                <?php if ($tab === 'pending'): ?>
                                                    <button onclick="openConfirmModal(<?= $m['id_surat_keluar'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'] ?? '')) ?>')" class="btn btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.85rem;">Arsipkan</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 450px; text-align: center; padding: 3rem;">
            <div style="width: 70px; height: 70px; background: rgba(99, 102, 241, 0.1); color: var(--primary); border-radius: 2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <svg viewBox="0 0 24 24" style="width:32px; height:32px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;">Konfirmasi Arsip</h3>
            <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">Apakah Anda yakin draf surat <strong id="modal-perihal" style="color:var(--text-main);"></strong> ini sudah sesuai untuk diarsipkan?</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button onclick="closeConfirmModal()" class="btn" style="background:#f1f5f9; color:#64748b;">Batal</button>
                <form method="POST" id="approveForm" style="display:none;"><input type="hidden" name="approve_id" id="approve_target_id"></form>
                <button onclick="document.getElementById('approveForm').submit()" class="btn btn-primary">Ya, Arsipkan</button>
            </div>
        </div>
    </div>

    <script>
        function openConfirmModal(id, p) {
            document.getElementById('approve_target_id').value = id;
            document.getElementById('modal-perihal').innerText = '"' + p + '"';
            document.getElementById('confirmModal').classList.add('active');
        }
        function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('active'); }
        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) closeConfirmModal(); };
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
