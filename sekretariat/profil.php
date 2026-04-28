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
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/profil.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
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
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Pengaturan Profil</h1><p style="font-size:0.9rem; color:var(--text-muted);">Kelola informasi personal dan keamanan akun Anda.</p></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['nama'] ?? 'User') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($user['nama'] ?? 'U'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($message): ?>
                <div class="alert-message alert-success"><svg class="icon alert-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message alert-danger"><svg class="icon alert-icon"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> <?= $error ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <div class="card-profile-info">
                    <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                        <div class="avatar-upload">
                            <img src="../uploads/profile/<?= !empty($user['foto']) ? $user['foto'] : 'default.png' ?>" class="avatar-preview" id="previewImg">
                            <label for="imageUpload" class="avatar-edit"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></label>
                            <input type='file' id="imageUpload" name="foto" accept=".png, .jpg, .jpeg" onchange="document.getElementById('photoForm').submit()" />
                            <input type="hidden" name="upload_photo" value="1">
                        </div>
                    </form>
                    <div class="profile-info-header">
                        <h2><?= htmlspecialchars($user['nama'] ?? '') ?></h2>
                        <span class="badge badge-role">Petugas Sekretariat</span>
                    </div>
                    <div class="info-stats">
                        <div class="stat-item"><span>NIP</span><span><?= htmlspecialchars($user['nip'] ?? '-') ?></span></div>
                        <div class="stat-item"><span>Unit Kerja</span><span><?= htmlspecialchars($user['nama_bidang'] ?? 'Sekretariat') ?></span></div>
                        <div class="stat-item"><span>Jabatan</span><span><?= htmlspecialchars($user['jabatan'] ?? 'Staff') ?></span></div>
                    </div>
                </div>

                <div class="card-profile-form">
                    <form action="" method="POST">
                        <div class="form-section-title"><svg class="icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> <h3>Data Personal</h3></div>
                        <div class="form-grid">
                            <div class="form-group full-width"><label>Nama Lengkap</label><input type="text" name="nama" value="<?= htmlspecialchars($user['nama'] ?? '') ?>" required></div>
                            <div class="form-group"><label>Alamat Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required></div>
                            <div class="form-group"><label>Nomor WhatsApp</label><input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>"></div>
                        </div>
                        <button type="submit" name="update_info" class="btn btn-primary" style="margin-top:1.5rem; height:48px; border-radius:1rem;"><svg class="icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline></svg> Update Profil</button>
                    </form>

                    <div style="height:1px; background:var(--border); margin:1rem 0;"></div>

                    <form action="" method="POST">
                        <div class="form-section-title"><svg class="icon"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> <h3>Keamanan Akun</h3></div>
                        <div class="form-grid">
                            <div class="form-group full-width"><label>Password Saat Ini</label><input type="password" name="old_password" required></div>
                            <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" required></div>
                            <div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="confirm_password" required></div>
                        </div>
                        <button type="submit" name="change_password" class="btn" style="background:var(--accent); color:#fff; margin-top:1.5rem; height:48px; border-radius:1rem; font-weight:800; border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem; padding:0 2rem;"><svg class="icon"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3L15.5 7.5z"></path></svg> Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>