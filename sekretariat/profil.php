<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$message = '';
$error = '';

// --- FETCH USER DATA ---
function fetchUserData($pdo, $nip)
{
    $stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi 
                          FROM users u 
                          LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                          LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                          WHERE u.nip = ?");
    $stmt->execute([$nip]);
    return $stmt->fetch();
}

$user = fetchUserData($pdo, $nip);

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $nama = $_POST['nama'] ?? '';
        $email = $_POST['email'] ?? '';
        $no_hp = $_POST['no_hp'] ?? '';

        try {
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE nip = ?");
            $stmt->execute([$nama, $email, $no_hp, $nip]);
            $message = "Informasi profil berhasil diperbarui.";
            $user = fetchUserData($pdo, $nip); // Refresh data
        } catch (PDOException $e) {
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }

    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (password_verify((string)$old_pass, (string)($user['password'] ?? ''))) {
            if ($new_pass === $confirm_pass) {
                $hashed_pass = password_hash((string)$new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nip = ?");
                $stmt->execute([$hashed_pass, $nip]);
                $message = "Password berhasil diubah.";
            } else {
                $error = "Konfirmasi password baru tidak cocok.";
            }
        } else {
            $error = "Password lama Anda salah.";
        }
    }

    if (isset($_POST['upload_photo']) || isset($_FILES['foto'])) {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = "profile_" . $nip . "_" . time() . "." . $ext;
            $target = "../uploads/profile/" . $filename;

            if (!is_dir("../uploads/profile/")) {
                mkdir("../uploads/profile/", 0777, true);
            }

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                // Delete old photo if not default
                if (($user['foto'] ?? '') !== 'default.png' && !empty($user['foto']) && file_exists("../uploads/profile/" . $user['foto'])) {
                    unlink("../uploads/profile/" . $user['foto']);
                }

                $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE nip = ?");
                $stmt->execute([$filename, $nip]);
                $message = "Foto profil berhasil diperbarui.";
                $user = fetchUserData($pdo, $nip);
            } else {
                $error = "Gagal mengunggah foto.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/theme.css?v=1.1">
    <link rel="stylesheet" href="../css/sekretariat/profil.css?v=1.1">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Manajemen Pengguna</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Pengaturan Profil</h1>
                <p>Kelola informasi personal dan keamanan akun Anda.</p>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['nama'] ?? 'User') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar">
                    <?php 
                        $foto_path = "../uploads/profile/" . $user['foto'];
                        if (!empty($user['foto']) && file_exists($foto_path)): 
                    ?>
                        <img src="<?= $foto_path ?>" alt="Avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <svg class="icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Sidebar Info Card -->
                <div class="card-profile-info">
                    <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                        <div class="avatar-upload">
                            <?php if (!empty($user['foto']) && file_exists($foto_path)): ?>
                                <img src="<?= $foto_path ?>" class="avatar-preview" id="previewImg">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <label for="imageUpload" class="avatar-edit">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </label>
                            <input type='file' id="imageUpload" name="foto" accept=".png, .jpg, .jpeg" onchange="document.getElementById('photoForm').submit()" />
                            <input type="hidden" name="upload_photo" value="1">
                        </div>
                    </form>
                    <div class="profile-info-header">
                        <h2><?= htmlspecialchars($user['nama'] ?? '') ?></h2>
                        <p>PETUGAS SEKRETARIAT</p>
                    </div>
                    <div class="info-stats">
                        <div class="stat-item">
                            <span>NIP PEGAWAI</span>
                            <span><?= htmlspecialchars($user['nip'] ?? '-') ?></span>
                        </div>
                        <div class="stat-item">
                            <span>UNIT KERJA</span>
                            <span><?= htmlspecialchars($user['nama_bidang'] ?? 'Sekretariat') ?></span>
                        </div>
                        <div class="stat-item no-border-bottom">
                            <span>JABATAN</span>
                            <span><?= htmlspecialchars($user['jabatan'] ?? 'Administrator') ?></span>
                        </div>
                    </div>
                    <div class="profile-dept-info">
                        Dinas Kearsipan dan Perpustakaan
                    </div>
                </div>

                <!-- Main Forms Card -->
                <div class="card-profile-form">
                    <form action="" method="POST">
                        <div class="form-section-title">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            INFORMASI PERSONAL
                        </div>
                        <input type="hidden" name="update_info" value="1">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Alamat Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nomor HP / WhatsApp</label>
                                <input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="08xxxxxxxx">
                            </div>
                        </div>
                        <button type="submit" class="btn-save">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Perubahan Profil
                        </button>
                    </form>

                    <form action="" method="POST" class="form-security-section">
                        <div class="form-section-title danger-section-header">
                            <svg class="icon danger-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            KEAMANAN AKUN
                        </div>
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>Password Saat Ini</label>
                            <input type="password" name="old_password" placeholder="Masukkan password lama..." required>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Password Baru</label>
                                <input type="password" name="new_password" placeholder="Minimal 8 karakter..." required>
                            </div>
                            <div class="form-group">
                                <label>Konfirmasi Password</label>
                                <input type="password" name="confirm_password" placeholder="Ulangi password baru..." required>
                            </div>
                        </div>
                        <button type="submit" class="btn-save btn-save-danger">
                            <svg class="icon" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Ganti Password Akun
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
