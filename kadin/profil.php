<?php
session_start();
require_once '../config/db.php';

// Auth Check for Kepala Dinas
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'kepala_dinas') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$message = '';
$error = '';

// --- FETCH USER DATA ---
function fetchUserData($pdo, $nip) {
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
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $no_hp = $_POST['no_hp'];

        try {
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE nip = ?");
            $stmt->execute([$nama, $email, $no_hp, $nip]);
            $message = "Profil pimpinan berhasil diperbarui.";
            $user = fetchUserData($pdo, $nip);
        } catch (PDOException $e) { $error = "Kesalahan updating: " . $e->getMessage(); }
    }

    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        if (password_verify($old_pass, $user['password'])) {
            if ($new_pass === $_POST['confirm_password']) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nip = ?");
                $stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT), $nip]);
                $message = "Password pimpinan berhasil diubah.";
            } else { $error = "Konfirmasi password tidak cocok."; }
        } else { $error = "Password lama salah."; }
    }

    if ((isset($_POST['upload_photo']) || isset($_FILES['foto'])) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $filename = "kadin_" . $nip . "_" . time() . "." . $ext;
        $target = "../uploads/profile/" . $filename;

        if (!is_dir("../uploads/profile/")) {
            mkdir("../uploads/profile/", 0777, true);
        }

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            // Delete old photo if not default
            if (!empty($user['foto']) && $user['foto'] !== 'default.png' && file_exists("../uploads/profile/" . $user['foto'])) {
                unlink("../uploads/profile/" . $user['foto']);
            }

            $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE nip = ?");
            $stmt->execute([$filename, $nip]);
            $message = "Foto pimpinan berhasil diperbarui.";
            $user = fetchUserData($pdo, $nip);
        } else {
            $error = "Gagal mengunggah foto.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pimpinan - Kepala Dinas</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/profil.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>KEPALA DINAS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Manajemen Akun Pimpinan</h1></div>
        </header>

        <div class="content-body">
            <?php if ($message): ?><div style="padding: 1rem; background: #dcfce7; color: #15803d; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div style="padding: 1rem; background: #fee2e2; color: #b91c1c; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

            <div class="profile-grid">
                <!-- Avatar Card -->
                <div class="card-avatar">
                    <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                        <div class="avatar-wrapper">
                            <?php 
                                $foto_path = "../uploads/profile/" . ($user['foto'] ?: 'default.png');
                                if (!empty($user['foto']) && file_exists($foto_path)): 
                            ?>
                                <img src="<?= $foto_path ?>" class="avatar-img" id="previewImg">
                            <?php else: ?>
                                <div class="avatar-img-placeholder">
                                    <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <label for="imgInp" class="avatar-edit"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></label>
                            <input type="file" id="imgInp" name="foto" style="display:none;" onchange="this.form.submit()">
                            <input type="hidden" name="upload_photo" value="1">
                        </div>
                    </form>
                    <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 1rem; color: var(--primary);"><?= htmlspecialchars($user['nama']) ?></h2>
                    <p style="color: var(--accent); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem;">Kepala Dinas</p>
                    <?php if(!empty($user['nama_bidang'])): ?>
                        <p style="color: var(--text-muted); font-weight: 600; font-size: 0.75rem; margin-top: 0.5rem;"><?= htmlspecialchars($user['nama_bidang']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Profile Info Form -->
                <div class="profile-card">
                    <div class="section-title"><svg class="icon" viewBox="0 0 24 24" style="color: var(--primary);"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> INFORMASI PERSONAL</div>
                    
                    <form action="" method="POST">
                        <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group"><label>Email Instansi</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                            <div class="form-group"><label>No. HP / WhatsApp</label><input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp']) ?>" placeholder="08xxxxxxxx"></div>
                        </div>
                        
                        <div class="detail-info-row"><span class="detail-label">NIP PEGAWAI</span><span class="detail-val"><?= htmlspecialchars($user['nip']) ?></span></div>
                        <div class="detail-info-row" style="border: none;"><span class="detail-label">JABATAN STRUKTURAL</span><span class="detail-val">Kepala Dinas</span></div>

                        <button type="submit" name="update_info" class="btn-save"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Update Data Personal</button>
                    </form>

                    <div style="height: 1px; background: var(--border); margin: 3.5rem 0;"></div>

                    <form action="" method="POST">
                        <div class="section-title"><svg class="icon" viewBox="0 0 24 24" style="color: var(--accent);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> PENGATURAN KEAMANAN</div>
                        <div class="form-group"><label>Password Lama</label><input type="password" name="old_password" placeholder="Masukkan password saat ini..." required></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" placeholder="Minimal 8 karakter..." required></div>
                            <div class="form-group"><label>Konfirmasi Password</label><input type="password" name="confirm_password" placeholder="Ulangi password baru..." required></div>
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="background: var(--accent); color: var(--primary);"><svg class="icon" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Perbarui Kata Sandi</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
