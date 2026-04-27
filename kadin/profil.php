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

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $filename = "kadin_" . $nip . "_" . time() . "." . pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $target = "../uploads/profile/" . $filename;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE nip = ?");
            $stmt->execute([$filename, $nip]);
            $message = "Foto pimpinan berhasil diperbarui.";
            $user = fetchUserData($pdo, $nip);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pimpinan - Kadis Panel</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/profil.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--accent);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>KADIS PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Logout Sesi</a>
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
                            <img src="../uploads/profile/<?= $user['foto'] ?>" class="avatar-img">
                            <label for="imgInp" class="avatar-edit"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg></label>
                            <input type="file" id="imgInp" name="foto" style="display:none;" onchange="this.form.submit()">
                        </div>
                    </form>
                    <h2 style="font-size: 1.5rem; font-weight: 800;"><?= htmlspecialchars($user['nama']) ?></h2>
                    <p style="color: var(--accent); font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Kepala Dinas</p>
                </div>

                <!-- Profile Info Form -->
                <div class="profile-card">
                    <form action="" method="POST">
                        <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group"><label>Email Instansi</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                            <div class="form-group"><label>No. HP / WhatsApp</label><input type="text" name="no_hp" value="<?= htmlspecialchars($user['no_hp']) ?>"></div>
                        </div>
                        <button type="submit" name="update_info" class="btn-save"><svg class="icon" style="stroke: var(--accent);"><polyline points="20 6 9 17 4 12"></polyline></svg> Update Profil</button>
                    </form>

                    <div style="height: 1px; background: var(--border); margin: 3rem 0;"></div>

                    <form action="" method="POST">
                        <h3 style="font-weight: 800; color: var(--primary); margin-bottom: 1.5rem;">Keamanan Sesi</h3>
                        <div class="form-group"><label>Password Lama</label><input type="password" name="old_password" required></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" required></div>
                            <div class="form-group"><label>Konfirmasi Password</label><input type="password" name="confirm_password" required></div>
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="background: var(--accent); color: var(--primary);"><svg class="icon"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3L15.5 7.5z"></path></svg> Simpan Password Baru</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
