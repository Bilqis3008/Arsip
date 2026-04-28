<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$success_msg = "";
$error_msg = "";

// --- HANDLE PASSWORD UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        if (strlen($new_pass) >= 8) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nip = ?");
            if ($stmt->execute([$hashed, $nip])) {
                $success_msg = "Password berhasil diperbarui!";
            } else {
                $error_msg = "Gagal mengubah password.";
            }
        } else {
            $error_msg = "Password minimal 8 karakter.";
        }
    } else {
        $error_msg = "Konfirmasi password tidak cocok.";
    }
}

// --- HANDLE PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama_baru = $_POST['nama'];
    $email_baru = $_POST['email'];
    $no_hp_baru = $_POST['no_hp'];

    $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE nip = ?");
    if ($stmt->execute([$nama_baru, $email_baru, $no_hp_baru, $nip])) {
        $success_msg = "Profil berhasil diperbarui!";
    } else {
        $error_msg = "Gagal memperbarui profil.";
    }
}

// Fetch Staff Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi 
                       FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip]);
$user_data = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Staff - Panel Operasional</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/profil.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--primary);" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Pengaturan Akun</h1>
                <p>Kelola profil dan keamanan akun Anda.</p>
            </div>
            <div class="header-actions" style="display: flex; align-items: center; gap: 1.5rem;">
                <div class="date-box-header" style="background: white; padding: 0.75rem 1.5rem; border-radius: 1.25rem; border: 1px solid var(--border); box-shadow: var(--shadow-md); display: flex; flex-direction: column; align-items: flex-end;">
                    <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Tanggal</div>
                    <div style="font-size: 0.9375rem; font-weight: 700; color: var(--primary);"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem; background: white; padding: 0.5rem 1.25rem; border-radius: 1.25rem; border: 1px solid var(--border); box-shadow: var(--shadow-md);">
                    <div class="user-info" style="display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2;">
                        <span class="user-name" style="font-weight: 800; color: var(--primary-dark); font-size: 0.9rem;"><?= htmlspecialchars((string)($user_data['nama'] ?? '')) ?></span>
                        <span class="user-role" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Staf <?= htmlspecialchars((string)($user_data['nama_seksi'] ?? 'Seksi')) ?></span>
                    </div>
                    <div class="user-avatar" style="width: 38px; height: 38px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;"><?= strtoupper(substr((string)($user_data['nama_seksi'] ?? ''), 0, 1) ?: 'S') ?></div>
                </div>
            </div>
        </header>

        <section class="profil-wrapper">
            <!-- Sidebar Details -->
            <div class="card-profile-header">
                <div class="avatar-staff"><?= strtoupper(substr((string)($user_data['nama'] ?? ''), 0, 1) ?: 'S') ?></div>
                <h3><?= htmlspecialchars($user_data['nama'] ?? '') ?></h3>
                <p>NIP: <?= htmlspecialchars($user_data['nip'] ?? '') ?></p>
            </div>

            <!-- Main Form -->
            <div class="card-profile-main">
                <?php if ($success_msg): ?><div style="padding: 1rem; background: #f0fdf4; color: #16a34a; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $success_msg ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div style="padding: 1rem; background: #fff1f2; color: #e11d48; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error_msg ?></div><?php endif; ?>

                <div class="p-section-title"><svg class="icon" style="color: var(--primary);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> INFORMASI PERSONAL</div>
                
                <form action="" method="POST" style="margin-bottom: 2rem;">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="p-form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" class="p-input" value="<?= htmlspecialchars($user_data['nama'] ?? '') ?>" required>
                    </div>
                    <div class="p-form-group">
                        <label>Email Dinas</label>
                        <input type="email" name="email" class="p-input" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                    </div>
                    <div class="p-form-group">
                        <label>Nomor HP / WhatsApp</label>
                        <input type="text" name="no_hp" class="p-input" value="<?= htmlspecialchars($user_data['no_hp'] ?? '') ?>" placeholder="08xxxxxxxx">
                    </div>
                    
                    <div class="detail-p-row"><span class="detail-p-label">NIP PEGAWAI</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nip'] ?? '') ?></span></div>
                    <div class="detail-p-row"><span class="detail-p-label">JABATAN / SEKSI</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nama_seksi'] ?? '') ?></span></div>
                    <div class="detail-p-row" style="border: none;"><span class="detail-p-label">UNIT KERJA (BIDANG)</span><span class="detail-p-val"><?= htmlspecialchars($user_data['nama_bidang'] ?? '') ?></span></div>
                    
                    <button type="submit" class="btn-update-profil" style="margin-top: 1rem; background: var(--primary);">Perbarui Data Personal</button>
                </form>

                <div class="p-section-title" style="margin-top: 3.5rem; border-top: 1px solid #e1eaf3; padding-top: 2rem;"><svg class="icon" style="color: var(--accent);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> PENGATURAN KEAMANAN</div>
                <form action="" method="POST">
                    <input type="hidden" name="update_password" value="1">
                    <div class="p-form-group">
                        <label>Password Baru</label>
                        <input type="password" name="new_password" class="p-input" placeholder="Masukkan password minimal 8 karakter..." required>
                    </div>
                    <div class="p-form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="p-input" placeholder="Ulangi password baru Anda..." required>
                    </div>
                    <button type="submit" class="btn-update-profil">Perbarui Kode Keamanan</button>
                </form>
            </div>
        </section>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
