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

// --- HANDLE PHOTO UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['upload_photo']) || isset($_FILES['foto']))) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $filename = "staff_" . $nip . "_" . time() . "." . $ext;
        $target = "../uploads/profile/" . $filename;

        if (!is_dir("../uploads/profile/")) {
            mkdir("../uploads/profile/", 0777, true);
        }

        // Fetch current photo to delete it
        $stmt = $pdo->prepare("SELECT foto FROM users WHERE nip = ?");
        $stmt->execute([$nip]);
        $current_photo = $stmt->fetchColumn();

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            // Delete old photo if not default
            if ($current_photo && $current_photo !== 'default.png' && file_exists("../uploads/profile/" . $current_photo)) {
                unlink("../uploads/profile/" . $current_photo);
            }

            $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE nip = ?");
            $stmt->execute([$filename, $nip]);
            $success_msg = "Foto profil berhasil diperbarui.";
        } else {
            $error_msg = "Gagal mengunggah foto.";
        }
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
    <link rel="stylesheet" href="../css/theme.css?v=1.1">
    <link rel="stylesheet" href="../css/staff/profil.css?v=1.1">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Profil Saya</h1>
                <p>Kelola informasi personal dan keamanan akun Anda</p>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user_data['nama']) ?></span>
                    <span class="user-role">Staf <?= htmlspecialchars($user_data['nama_seksi']) ?></span>
                </div>
                <div class="user-avatar">
                    <?php 
                        $foto_path = "../uploads/profile/" . $user_data['foto'];
                        if (!empty($user_data['foto']) && file_exists($foto_path)): 
                    ?>
                        <img src="<?= $foto_path ?>" alt="Avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user_data['nama'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <svg class="icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= $success_msg ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <section class="profil-wrapper">
                <!-- Sidebar Details -->
                <div class="card-profile-header">
                    <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                        <div class="avatar-upload">
                            <?php if (!empty($user_data['foto']) && file_exists($foto_path)): ?>
                                <img src="<?= $foto_path ?>" class="avatar-preview" id="previewImg">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($user_data['nama'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <label for="imageUpload" class="avatar-edit">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </label>
                            <input type='file' id="imageUpload" name="foto" accept=".png, .jpg, .jpeg" onchange="document.getElementById('photoForm').submit()" />
                            <input type="hidden" name="upload_photo" value="1">
                        </div>
                    </form>
                    <h3><?= htmlspecialchars($user_data['nama'] ?? '') ?></h3>
                    <p>STAF <?= htmlspecialchars(strtoupper($user_data['nama_seksi'] ?? '')) ?></p>
                    <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">
                        Dinas Kearsipan dan Perpustakaan
                    </div>
                </div>

                <!-- Main Form -->
                <div class="card-profile-main">
                    <form action="" method="POST">
                        <div class="p-section-title">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            INFORMASI PERSONAL
                        </div>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="p-form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" class="p-input" value="<?= htmlspecialchars($user_data['nama'] ?? '') ?>" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="p-form-group">
                                <label>Email Dinas</label>
                                <input type="email" name="email" class="p-input" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                            </div>
                            <div class="p-form-group">
                                <label>Nomor HP / WhatsApp</label>
                                <input type="text" name="no_hp" class="p-input" value="<?= htmlspecialchars($user_data['no_hp'] ?? '') ?>" placeholder="08xxxxxxxx">
                            </div>
                        </div>
                        
                        <div class="detail-p-row">
                            <span class="detail-p-label">NIP PEGAWAI</span>
                            <span class="detail-p-val"><?= htmlspecialchars($user_data['nip'] ?? '') ?></span>
                        </div>
                        <div class="detail-p-row">
                            <span class="detail-p-label">JABATAN / SEKSI</span>
                            <span class="detail-p-val"><?= htmlspecialchars($user_data['nama_seksi'] ?? '') ?></span>
                        </div>
                        <div class="detail-p-row" style="border: none;">
                            <span class="detail-p-label">UNIT KERJA (BIDANG)</span>
                            <span class="detail-p-val"><?= htmlspecialchars($user_data['nama_bidang'] ?? '') ?></span>
                        </div>
                        
                        <button type="submit" class="btn-update-profil" style="background: var(--primary);">Perbarui Data Personal</button>
                    </form>

                    <form action="" method="POST" style="margin-top: 3.5rem;">
                        <div class="p-section-title" style="border-bottom-color: #fee2e2;">
                            <svg class="icon" viewBox="0 0 24 24" style="color: var(--danger);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            PENGATURAN KEAMANAN
                        </div>
                        <input type="hidden" name="update_password" value="1">
                        <div class="p-form-group">
                            <label>Password Baru</label>
                            <input type="password" name="new_password" class="p-input" placeholder="Minimal 8 karakter..." required>
                        </div>
                        <div class="p-form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="p-input" placeholder="Ulangi password baru Anda..." required>
                        </div>
                        <button type="submit" class="btn-update-profil" style="background: var(--danger);">Perbarui Kata Sandi</button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
