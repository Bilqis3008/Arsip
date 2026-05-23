<?php
session_start();
require_once '../config/db.php';

// Auth Check for Admin Bidang
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'admin_bidang') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$tab = $_GET['tab'] ?? 'pending'; // pending | verified
$search = $_GET['search'] ?? '';

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang FROM users u LEFT JOIN bidang b ON u.id_bidang = b.id_bidang WHERE u.nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

$id_bidang = $admin['id_bidang'];

// --- HANDLE APPROVAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $id_approve = $_POST['approve_id'];
    $stmt = $pdo->prepare("UPDATE surat_keluar SET status = 'diarsipkan' WHERE id_surat_keluar = ? AND uploaded_by IN (SELECT nip FROM users WHERE id_bidang = ?)");
    if ($stmt->execute([$id_approve, $id_bidang])) {
        // Fetch details for notification
        $stmt_info = $pdo->prepare("SELECT sk.*, u.nip FROM surat_keluar sk JOIN users u ON sk.uploaded_by = u.nip WHERE sk.id_surat_keluar = ?");
        $stmt_info->execute([$id_approve]);
        $sk_info = $stmt_info->fetch();

        // Notification Logic
        require_once '../shared/notification_helper.php';
        if ($sk_info) {
            // Notify Staff
            addNotification($pdo, $sk_info['nip'], "Surat Balasan Anda telah disetujui & diarsipkan: " . $sk_info['perihal'], "../staff/laporan.php");
            // Notify Sekretariat
            notifySekretariat($pdo, "Arsip Baru Tersedia (Approved by Admin Bidang): " . $sk_info['perihal'], "../sekretariat/monitoring_laporan.php");
        }

        header("Location: surat_keluar.php?tab=verified");
        exit;
    }
}

// --- HANDLE REJECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    $id_reject = $_POST['reject_id'];
    $reason = $_POST['reject_reason'] ?? 'Tidak ada alasan spesifik.';
    
    $stmt = $pdo->prepare("UPDATE surat_keluar SET status = 'draft', keterangan = ? WHERE id_surat_keluar = ? AND uploaded_by IN (SELECT nip FROM users WHERE id_bidang = ?)");
    if ($stmt->execute([$reason, $id_reject, $id_bidang])) {
        // Fetch details for notification
        $stmt_info = $pdo->prepare("SELECT sk.*, u.nip FROM surat_keluar sk JOIN users u ON sk.uploaded_by = u.nip WHERE sk.id_surat_keluar = ?");
        $stmt_info->execute([$id_reject]);
        $sk_info = $stmt_info->fetch();

        require_once '../shared/notification_helper.php';
        if ($sk_info) {
            addNotification($pdo, $sk_info['nip'], "Draft Balasan REVISI (Ditolak Admin): " . $sk_info['perihal'] . ". Alasan: " . $reason, "../staff/tindak_lanjut.php");
        }

        header("Location: surat_keluar.php?tab=pending&notif=rejected");
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
$stmt->execute([$id_bidang, "%$search%", "%$search%", "%$search%"]);
$mails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keluar / Verifikasi Draft - Admin Ops</title>
    <link rel="stylesheet" href="../css/admin_perbidang/home.css?v=1.1">
    <link rel="stylesheet" href="../css/admin_perbidang/surat_keluar.css?v=1.1">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>BIDANG OPS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Pengelolaan Surat</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi Internal</a>
            <a href="monitoring_tindakLanjut.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Seksi</a>
            <a href="surat_keluar.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Reporting & Account</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Verifikasi Surat Keluar</h1>
                <p>Otorisasi draft surat balasan dari seksi sebelum diteruskan ke Sekretariat.</p>
            </div>
            <div class="header-actions">
                <div class="date-box-header">
                    <div class="date-box-label">Tanggal</div>
                    <div class="date-box-value"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile-header">
                    <div class="user-info-header">
                        <span class="user-name-header"><?= htmlspecialchars((string)$admin['nama']) ?></span>
                        <span class="user-role-header"><?= htmlspecialchars((string)$admin['nama_bidang']) ?></span>
                    </div>
                    <div class="user-avatar-header"><?= strtoupper(substr((string)$admin['nama_bidang'], 0, 1)) ?></div>
                </div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="tabs-container">
                <a href="surat_keluar.php?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>">
                    <svg class="icon icon-tab" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    <span>Belum Diverifikasi</span>
                </a>
                <a href="surat_keluar.php?tab=verified" class="tab-btn <?= $tab === 'verified' ? 'active' : '' ?>">
                    <svg class="icon icon-tab" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>Riwayat Disetujui</span>
                </a>
            </div>

            <!-- Search Area -->
            <div class="table-controls-compact">
                <form method="GET" class="search-box-premium">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon search-icon-inside" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="search" placeholder="Cari perihal, nomor rilis, atau tujuan..." value="<?= htmlspecialchars((string)$search) ?>">
                </form>
            </div>

            <!-- List -->
            <div class="table-card-premium">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Tanggal</th>
                            <th>Identitas & Tujuan Berkas</th>
                            <th>Penulis / Unit Asal</th>
                            <th style="width: 140px;">Status Verifikasi</th>
                            <th style="width: 120px; text-align: center;">Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mails)): ?>
                            <tr><td colspan="5" class="empty-state-table">Tidak ditemukan draft surat balasan pada kategori ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td>
                                        <div class="date-cell">
                                            <span class="date-day"><?= date('d', strtotime($m['tanggal_surat'])) ?></span>
                                            <span class="date-month"><?= date('M Y', strtotime($m['tanggal_surat'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mail-info-premium">
                                            <b class="mail-title-txt"><?= htmlspecialchars($m['perihal']) ?></b>
                                            <span class="mail-meta-txt">
                                                <svg class="icon icon-tiny" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> 
                                                Tujuan: <?= htmlspecialchars($m['tujuan']) ?>
                                            </span>
                                            <span class="mail-meta-txt">
                                                <svg class="icon icon-tiny" viewBox="0 0 24 24"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8l-6 6v12a2 2 0 0 0 2 2Z"/><path d="M14 2v4a2 2 0 0 1 2 2h4"/><path d="M3 7h5v5"/></svg>
                                                No: <?= htmlspecialchars($m['nomor_surat_keluar']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="author-cell">
                                            <span class="author-name"><?= htmlspecialchars($m['pengirim_staf']) ?></span>
                                            <span class="author-unit"><?= htmlspecialchars($m['nama_seksi'] ?: 'Staf Bidang') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = ($m['status'] == 'pending_approval') ? 'warning' : 'success';
                                            $statusTxt = ($m['status'] == 'pending_approval') ? 'Perlu Review' : 'Telah Disetujui';
                                        ?>
                                        <div class="status-indicator-badge <?= $badgeClass ?>">
                                            <span class="dot-blink"></span>
                                            <?= $statusTxt ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-buttons-group">
                                            <?php if ($m['file_path']): ?>
                                                <a href="../uploads/surat_keluar/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn-circle btn-view" title="Pratinjau Dokumen">
                                                    <svg class="icon icon-btn" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($tab === 'pending'): ?>
                                                <button type="button" 
                                                        onclick="openRejectModal(<?= $m['id_surat_keluar'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'])) ?>')" 
                                                        class="btn-circle btn-reject" title="Tolak / Revisi">
                                                    <svg class="icon icon-btn" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                                </button>

                                                <button type="button" 
                                                        onclick="openConfirmModal(<?= $m['id_surat_keluar'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'])) ?>')" 
                                                        class="btn-approve-pill">
                                                    <svg class="icon icon-btn" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                    Setujui
                                                </button>
                                            <?php else: ?>
                                                <a href="monitoring_tindakLanjut.php?search=<?= urlencode($m['nomor_surat_keluar']) ?>" class="btn-circle btn-track" title="Lacak Alur">
                                                    <svg class="icon icon-btn" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg>
                                                </a>
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
    </main>

    <!-- Hidden Forms for submission -->
    <form id="approveForm" method="POST" style="display: none;">
        <input type="hidden" name="approve_id" id="approve_target_id">
    </form>
    <form id="rejectForm" method="POST" style="display: none;">
        <input type="hidden" name="reject_id" id="reject_target_id">
        <input type="hidden" name="reject_reason" id="reject_target_reason">
    </form>

    <!-- Custom Confirmation Modal (Approve) -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" style="width:32px; height:32px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h3 class="modal-title">Konfirmasi Pengarsipan</h3>
            <p class="modal-message">Apakah Anda yakin draft surat <strong id="modal-perihal" style="color:#0f172a;"></strong> ini sudah sesuai? Surat akan diarsipkan secara permanen.</p>
            <div class="modal-actions">
                <button type="button" onclick="closeConfirmModal()" class="btn-modal btn-cancel">Batal</button>
                <button type="button" onclick="submitApprove()" class="btn-modal btn-confirm">Ya, Arsipkan</button>
            </div>
        </div>
    </div>

    <!-- Custom Rejection Modal -->
    <div id="rejectModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon" style="background:#fef2f2; color:#ef4444;">
                <svg viewBox="0 0 24 24" style="width:32px; height:32px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </div>
            <h3 class="modal-title">Tolak / Revisi Draft</h3>
            <p class="modal-message">Berikan alasan penolakan agar staf dapat melakukan perbaikan pada draft <strong id="modal-reject-perihal" style="color:#0f172a;"></strong>.</p>
            
            <div class="form-group" style="text-align:left; margin-bottom:2rem;">
                <label>Alasan Penolakan</label>
                <textarea id="reject-reason-input" placeholder="Contoh: Nomor surat salah, Lampiran kurang lengkap..." style="margin-top:0.5rem;"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeRejectModal()" class="btn-modal btn-cancel">Batal</button>
                <button type="button" onclick="submitReject()" class="btn-modal btn-confirm" style="background:#ef4444;">Tolak Draft</button>
            </div>
        </div>
    </div>

    <script>
        function openConfirmModal(id, perihal) {
            document.getElementById('approve_target_id').value = id;
            document.getElementById('modal-perihal').innerText = '"' + perihal + '"';
            document.getElementById('confirmModal').classList.add('active');
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
            document.getElementById('confirmModal').style.display = 'none';
        }

        function openRejectModal(id, perihal) {
            document.getElementById('reject_target_id').value = id;
            document.getElementById('modal-reject-perihal').innerText = '"' + perihal + '"';
            document.getElementById('rejectModal').classList.add('active');
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejectModal').style.display = 'none';
        }

        function submitApprove() {
            document.getElementById('approveForm').submit();
        }

        function submitReject() {
            const reason = document.getElementById('reject-reason-input').value.trim();
            if(!reason) {
                alert('Harap masukkan alasan penolakan!');
                return;
            }
            document.getElementById('reject_target_reason').value = reason;
            document.getElementById('rejectForm').submit();
        }

        // Close on escape
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeConfirmModal();
                closeRejectModal();
            }
        });
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
