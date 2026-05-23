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
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ? OR sk.tujuan LIKE ?) 
              ORDER BY sk.created_at DESC";
} else {
    $query = "SELECT sk.*, u.nama as pengirim_staf, s.nama_seksi 
              FROM surat_keluar sk 
              JOIN users u ON sk.uploaded_by = u.nip 
              LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
              WHERE u.id_bidang = ? AND sk.status IN ('disetujui', 'diarsipkan')
              AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ? OR sk.tujuan LIKE ?) 
              ORDER BY sk.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$id_bidang_sekretariat, "%$search%", "%$search%", "%$search%"]);
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
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Verifikasi Surat Keluar</h1><p class="header-desc-text">Verifikasi draf surat dari unit Sekretariat Umum untuk pengarsipan.</p></div>
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
                <a href="?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Belum Diverifikasi</a>
                <a href="?tab=verified" class="tab-btn <?= $tab === 'verified' ? 'active' : '' ?>"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg> Riwayat Disetujui</a>
            </div>

            <!-- Search & Control -->
            <div class="table-controls">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
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
                                <th class="action-th-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mails)): ?>
                                <tr><td colspan="5" class="empty-table-row">Tidak ada draf surat keluar ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                    <tr>
                                        <td><b><?= date('d/m/Y', strtotime($m['tanggal_surat'] ?? 'now')) ?></b></td>
                                        <td>
                                            <div class="title-primary-bold"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                            <div class="subtitle-muted-sm">No: <?= htmlspecialchars($m['nomor_surat_keluar'] ?? '') ?> • Tujuan: <?= htmlspecialchars($m['tujuan'] ?? '-') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($m['pengirim_staf'] ?? '-') ?></td>
                                        <td><span class="badge-status status-<?= $m['status'] ?>"><?= $m['status'] === 'pending_approval' ? 'Draf Selesai' : 'Diarsipkan' ?></span></td>
                                        <td>
                                            <div class="action-btns action-btns-flex action-btns-center">
                                                <?php if ($m['file_path']): ?>
                                                    <a href="../uploads/surat_keluar/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Preview Dokumen"><svg class="icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                                <?php endif; ?>
                                                <?php if ($tab === 'pending'): ?>
                                                    <button onclick="openConfirmModal(<?= $m['id_surat_keluar'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'] ?? '')) ?>')" class="btn btn-primary btn-primary-xs">Arsipkan</button>
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
            <div class="modal-card modal-confirm-card">
                <div class="modal-icon-circle">
                    <svg viewBox="0 0 24 24" class="modal-icon-svg"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <h3 class="modal-title-bold">Konfirmasi Arsip</h3>
                <p class="modal-desc-muted">Apakah Anda yakin draf surat <strong id="modal-perihal" class="modal-desc-highlight"></strong> ini sudah sesuai untuk diarsipkan?</p>
                <div class="modal-button-grid">
                    <button onclick="closeConfirmModal()" class="btn btn-cancel-light">Batal</button>
                    <form method="POST" id="approveForm" class="hidden-form"><input type="hidden" name="approve_id" id="approve_target_id"></form>
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
