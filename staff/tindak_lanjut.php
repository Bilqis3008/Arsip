<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$id_surat = $_GET['id'] ?? null;
if (!$id_surat) {
    header('Location: surat_masuk.php');
    exit;
}

$nip_staff = $_SESSION['user_nip'];

// Fetch Staff Info for Header
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip_staff]);
$admin = $stmt->fetch();

// --- FETCH TASK & INSTRUCTIONS ---
$stmt = $pdo->prepare("SELECT sm.*, b.nama_bidang, s.nama_seksi 
                       FROM surat_masuk sm 
                       LEFT JOIN bidang b ON sm.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON sm.id_seksi = s.id_seksi 
                       WHERE sm.id_surat_masuk = ?");
$stmt->execute([$id_surat]);
$mail = $stmt->fetch();

if (!$mail) {
    header('Location: surat_masuk.php');
    exit;
}

// Fetch Chain of Command (Disposisi)
$stmt = $pdo->prepare("SELECT d.*, u.nama, u.role 
                       FROM disposisi d 
                       JOIN users u ON d.nip_pemberi = u.nip 
                       WHERE d.id_surat_masuk = ? 
                       ORDER BY d.tanggal_disposisi ASC");
$stmt->execute([$id_surat]);
$instructions = $stmt->fetchAll();

// Check if already has a reply and get its status
$stmt = $pdo->prepare("SELECT status FROM surat_keluar WHERE id_surat_masuk = ? ORDER BY id_surat_keluar DESC LIMIT 1");
$stmt->execute([$id_surat]);
$reply_data = $stmt->fetch();
$has_reply = (bool)$reply_data;
$is_reply_verified = $has_reply && in_array($reply_data['status'], ['disetujui', 'diarsipkan']);
$is_fully_done = $is_reply_verified || (in_array($mail['status'], ['selesai', 'diarsipkan']) && $mail['perlu_balasan'] == 0);

// --- HANDLE FULFILLMENT (UPLOAD REPLY) ---
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fulfillment']) && !$has_reply) {
    $nomor_surat_keluar = $_POST['nomor_surat_keluar'];
    $tujuan = $_POST['tujuan'];
    $perihal = "Balasan: " . $mail['perihal'];
    $tanggal_surat = date('Y-m-d');
    
    // File Upload
    $file_name = null;
    if (isset($_FILES['file_reply']) && $_FILES['file_reply']['error'] === 0) {
        $ext = pathinfo($_FILES['file_reply']['name'], PATHINFO_EXTENSION);
        $file_name = "REPLY_" . time() . "_" . uniqid() . "." . $ext;
        $target_path = "../uploads/surat_keluar/" . $file_name;
        
        if (!is_dir("../uploads/surat_keluar/")) {
            mkdir("../uploads/surat_keluar/", 0777, true);
        }
        
        if (move_uploaded_file($_FILES['file_reply']['tmp_name'], $target_path)) {
            try {
                // Insert into surat_keluar with pending_approval status
                $stmt = $pdo->prepare("INSERT INTO surat_keluar (nomor_surat_keluar, tanggal_surat, perihal, id_surat_masuk, tujuan, file_path, uploaded_by, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval')");
                $stmt->execute([$nomor_surat_keluar, $tanggal_surat, $perihal, $id_surat, $tujuan, $file_name, $nip_staff]);
                
                // Notification Logic
                require_once '../shared/notification_helper.php';
                notifyAdminBidang($pdo, $mail['id_bidang'], "Staff mengunggah balasan baru untuk verifikasi: " . $mail['perihal'], "../admin_perbidang/surat_keluar.php");

                $success = "Draft balasan berhasil diunggah! Menunggu verifikasi dari Admin Bidang.";
                $has_reply = true;
            } catch (PDOException $e) {
                $error = "Kesalahan Database: " . $e->getMessage();
            }
        } else {
            $error = "Gagal mengunggah file.";
        }
    } else {
        $error = "File balasan wajib diunggah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyelesaian Tugas - Staff Operational</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/tindak_lanjut.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Keluar Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Tindak Lanjut & Balasan</h1>
                <p>Proses penyelesaian berkas tugas seksi Anda.</p>
            </div>
            <div class="header-actions" style="display: flex; align-items: center; gap: 1.5rem;">
                <div class="date-box-header" style="background: white; padding: 0.75rem 1.5rem; border-radius: 1.25rem; border: 1px solid var(--border); box-shadow: var(--shadow-md); display: flex; flex-direction: column; align-items: flex-end;">
                    <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Tanggal</div>
                    <div style="font-size: 0.9375rem; font-weight: 700; color: var(--primary);"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: white; padding: 0.5rem 1.25rem; border-radius: 1.25rem; border: 1px solid var(--border); box-shadow: var(--shadow-md);">
                    <div class="user-info" style="display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2;">
                        <span class="user-name" style="font-weight: 800; color: var(--primary-dark); font-size: 0.9rem;"><?= htmlspecialchars((string)($admin['nama'] ?? '')) ?></span>
                        <span class="user-role" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Staf <?= htmlspecialchars((string)($admin['nama_seksi'] ?? 'Seksi')) ?></span>
                    </div>
                    <div class="user-avatar" style="width: 38px; height: 38px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;"><?= strtoupper(substr((string)($admin['nama_seksi'] ?? ''), 0, 1) ?: 'S') ?></div>
                </div>
            </div>
        </header>

        <?php if ($success): ?><div style="padding: 1rem; background: #f0fdf4; color: #16a34a; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div style="padding: 1rem; background: #fff1f2; color: #e11d48; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

        <div class="fulfillment-grid">
            <!-- Left: Command Trace -->
            <div class="card-trace">
                <div class="trace-header">
                    <h2><?= htmlspecialchars($mail['perihal'] ?? '') ?></h2>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">STATUS: <b style="color: var(--primary);"><?= strtoupper($mail['status'] ?? '') ?></b></p>
                </div>

                <div class="trace-timeline">
                    <!-- Original Mail -->
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <span class="timeline-label">SURAT ASLI (PENGIRIM: <?= htmlspecialchars($mail['pengirim'] ?? '') ?>)</span>
                            <div class="timeline-body"><?= htmlspecialchars($mail['nomor_surat'] ?? '') ?></div>
                            <div style="margin-top: 0.5rem;"><a href="../<?= htmlspecialchars($mail['file_path'] ?? '') ?>" target="_blank" style="font-size: 0.75rem; color: var(--primary); font-weight: 800; text-decoration: none;">Download Surat Masuk &rarr;</a></div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <?php foreach ($instructions as $ins): ?>
                        <div class="timeline-item active">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-label">INSTRUKSI DARI: <?= strtoupper($ins['role'] ?? '') ?> (<?= htmlspecialchars($ins['nama'] ?? '') ?>)</span>
                                <div class="timeline-body"><?= nl2br(htmlspecialchars($ins['isi_disposisi'] ?? '')) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; font-weight: 800;"><?= date('d M Y H:i', strtotime($ins['tanggal_disposisi'] ?? 'now')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Fulfillment Form -->
            <div class="card-fulfillment">
                <?php if ($mail['perlu_balasan'] == 1 && !$has_reply): ?>
                    <div class="form-title">
                        <svg class="icon" viewBox="0 0 24 24" style="stroke: var(--primary);"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
                        <h3>Unggah Surat Balasan</h3>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nomor Surat Balasan</label>
                            <input type="text" name="nomor_surat_keluar" placeholder="Contoh: 004/DISDIK/IV/2026" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan / Penerima Balasan</label>
                            <input type="text" name="tujuan" value="<?= htmlspecialchars($mail['pengirim'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>File Surat Balasan (PDF)</label>
                            <input type="file" name="file_reply" accept=".pdf" required>
                        </div>
                        <div class="form-group">
                            <label>Keterangan Tambahan</label>
                            <textarea name="keterangan" placeholder="Catatan proses penyelesaian..."></textarea>
                        </div>
                        <button type="submit" name="submit_fulfillment" class="btn-finish">Kirim Untuk Verifikasi</button>
                    </form>
                <?php elseif ($has_reply && !$is_fully_done): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <svg class="icon" viewBox="0 0 24 24" style="width: 60px; height: 60px; color: #f59e0b; margin-bottom: 1.5rem;"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg>
                        <h3 style="font-weight: 800; color: #0f172a;">Menunggu Verifikasi</h3>
                        <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.75rem;">Balasan telah diunggah. Tugas akan ditandai selesai setelah disetujui oleh Admin Bidang.</p>
                        <a href="surat_masuk.php" class="btn-finish" style="margin-top: 2rem; display: block; text-decoration: none; background: #0f172a;">Kembali ke Daftar Tugas</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <svg class="icon" viewBox="0 0 24 24" style="width: 60px; height: 60px; color: #10b981; margin-bottom: 1.5rem;"><circle cx="12" cy="12" r="10"/><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <h3 style="font-weight: 900; color: #059669;">Tugas Tuntas Terverifikasi</h3>
                        <p style="font-size: 0.95rem; color: #64748b; margin-top: 0.75rem;">Surat balasan Anda telah disetujui oleh Admin dan secara resmi masuk ke dalam arsip sistem.</p>
                        <a href="surat_masuk.php" class="btn-finish" style="margin-top: 2rem; display: block; text-decoration: none; background: #059669;">Kembali ke Riwayat</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
