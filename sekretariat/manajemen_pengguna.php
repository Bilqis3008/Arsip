<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$success_msg = "";
$error_msg = "";

// --- HANDLE FORM SUBMISSION (CREATE/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nip = $_POST['nip'];
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $no_hp = $_POST['no_hp'];
    $jabatan = $_POST['jabatan'];
    $role = $_POST['role'];
    $id_bidang = !empty($_POST['id_bidang']) ? $_POST['id_bidang'] : null;
    $id_seksi = !empty($_POST['id_seksi']) ? $_POST['id_seksi'] : null;

    if ($action === 'save_user') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, password, no_hp, jabatan, role, id_bidang, id_seksi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
            $stmt->execute([$nip, $nama, $email, $password, $no_hp, $jabatan, $role, $id_bidang, $id_seksi]);
            $success_msg = "Pengguna baru berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error_msg = "Gagal menambah pengguna: " . $e->getMessage();
        }
    } elseif ($action === 'update_user') {
        try {
            $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ? WHERE nip = ?";
            $params = [$nama, $email, $no_hp, $jabatan, $role, $id_bidang, $id_seksi, $nip];

            // Update password if provided
            if (!empty($_POST['password'])) {
                $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ?, password = ? WHERE nip = ?";
                $params = [$nama, $email, $no_hp, $jabatan, $role, $id_bidang, $id_seksi, password_hash($_POST['password'], PASSWORD_DEFAULT), $nip];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_msg = "Data pengguna berhasil diperbarui!";
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui pengguna: " . $e->getMessage();
        }
    }
}

// --- HANDLE STATUS TOGGLE ---
if (isset($_GET['toggle_status']) && isset($_GET['nip'])) {
    $nip = $_GET['nip'];
    $current_status = $_GET['toggle_status'];
    $new_status = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';

    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE nip = ?");
        $stmt->execute([$new_status, $nip]);
        $success_msg = "Status akun pengguna berhasil diubah menjadi " . $new_status . "!";
    } catch (PDOException $e) {
        $error_msg = "Gagal mengubah status: " . $e->getMessage();
    }
}

// --- FETCH DATA ---
// Fetch All Users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$query = "SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
          LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
          LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
          WHERE (u.nama LIKE ? OR u.nip LIKE ? OR u.email LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY u.nama ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch Bidang list
$bidang_list = $pdo->query("SELECT * FROM bidang ORDER BY nama_bidang ASC")->fetchAll();

// Fetch Seksi list (for JS mapping)
$seksi_list = $pdo->query("SELECT * FROM seksi ORDER BY nama_seksi ASC")->fetchAll();

// Fetch Admin Data for profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

// --- PRE-FILL EDIT FORM ---
$edit_user = null;
if (isset($_GET['edit_nip'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
    $stmt->execute([$_GET['edit_nip']]);
    $edit_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/manajemen_pengguna.css">
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px;">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <h2>ARSIP DIGITAL</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>

            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                Surat Masuk
            </a>

            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manajemen Pengguna
            </a>
            <a href="monitoring_surat.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path>
                    <line x1="12" y1="14" x2="12" y2="19"></line>
                </svg>
                Monitoring Surat
            </a>

            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                Laporan
            </a>
            
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg> Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Keluar Sistem
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Manajemen Pengguna</h1>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Alerts -->
            <?php if ($success_msg): ?>
                <div
                    style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--success); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg> <?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div
                    style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--danger); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="module-tabs">
                <button class="tab-btn active" onclick="switchTab('daftar')"><svg class="icon"
                        style="margin-right: 0.5rem;">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg> Daftar Pengguna</button>
                <button class="tab-btn" onclick="switchTab('tambah')"><svg class="icon" style="margin-right: 0.5rem;">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg> Tambah Baru</button>
            </div>

            <!-- Section: Daftar -->
            <section id="section-daftar" class="module-section active">
                <div class="card">
                    <div class="table-controls">
                        <form method="GET" class="search-box">
                            <input type="text" name="search" placeholder="Cari NIP, nama, atau email..."
                                value="<?= htmlspecialchars($search) ?>">
                        </form>
                        <form method="GET" class="filter-group">
                            <select name="role" onchange="this.form.submit()">
                                <option value="">Semua Role</option>
                                <option value="kepala_dinas" <?= $role_filter === 'kepala_dinas' ? 'selected' : '' ?>>
                                    Kepala Dinas</option>
                                <option value="admin_bidang" <?= $role_filter === 'admin_bidang' ? 'selected' : '' ?>>Admin
                                    Bidang</option>
                                <option value="bagian_perencanaan" <?= $role_filter === 'bagian_perencanaan' ? 'selected' : '' ?>>Perencanaan</option>
                                <option value="bagian_keuangan" <?= $role_filter === 'bagian_keuangan' ? 'selected' : '' ?>>Keuangan</option>
                                <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            </select>
                        </form>
                    </div>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Role & Jabatan</th>
                                    <th>Unit Kerja</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info-cell">
                                                <div class="user-avatar"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
                                                <div>
                                                    <div style="font-weight: 700;"><?= htmlspecialchars($u['nama']) ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">NIP:
                                                        <?= htmlspecialchars($u['nip']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge badge-role"><?= ucwords(str_replace('_', ' ', $u['role'])) ?>
                                            </div>
                                            <div style="font-size: 0.8rem; margin-top: 0.25rem;">
                                                <?= htmlspecialchars($u['jabatan']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; font-weight: 600;">
                                                <?= htmlspecialchars($u['nama_bidang'] ?: '-') ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?= htmlspecialchars($u['nama_seksi'] ?: '-') ?></div>
                                        </td>
                                        <td><span
                                                class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span>
                                        </td>
                                        <td class="action-btns">
                                            <a href="?edit_nip=<?= $u['nip'] ?>#tambah" class="action-btn btn-edit"
                                                title="Edit"><svg class="icon" style="width: 16px; height: 16px;">
                                                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z">
                                                    </path>
                                                </svg></a>
                                            <a href="?toggle_status=<?= $u['status'] ?>&nip=<?= $u['nip'] ?>"
                                                class="action-btn btn-toggle"
                                                title="<?= $u['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                onclick="return confirm('Apakah Anda yakin ingin <?= $u['status'] === 'aktif' ? 'menonaktifkan' : 'mengaktifkan' ?> akun ini?')"><svg
                                                    class="icon"
                                                    style="width: 16px; height: 16px; stroke: <?= $u['status'] === 'aktif' ? 'var(--danger)' : 'var(--success)' ?>;">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                </svg></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: Tambah/Edit -->
            <section id="section-tambah" class="module-section">
                <div class="card">
                    <div class="card-header">
                        <h2><?= $edit_user ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' ?></h2>
                        <p><?= $edit_user ? 'Perbarui informasi akun pengguna yang sudah terdaftar.' : 'Buat akun baru untuk pegawai sesuai dengan role dan unit kerja.' ?>
                        </p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $edit_user ? 'update_user' : 'save_user' ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>NIP Pegawai <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="nip" required placeholder="Contoh: 19880101..."
                                    value="<?= $edit_user ? htmlspecialchars($edit_user['nip']) : '' ?>" <?= $edit_user ? 'readonly' : '' ?>>
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap <span style="color: var(--danger);">*</span></label>
                                <input type="text" name="nama" required placeholder="Nama serta gelar (jika ada)"
                                    value="<?= $edit_user ? htmlspecialchars($edit_user['nama']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Institusi <span style="color: var(--danger);">*</span></label>
                                <input type="email" name="email" required placeholder="email@kemendikbud.go.id"
                                    value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Nomor HP/WhatsApp</label>
                                <input type="text" name="no_hp" placeholder="08xxxxxx"
                                    value="<?= $edit_user ? htmlspecialchars($edit_user['no_hp']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Role Sistem <span style="color: var(--danger);">*</span></label>
                                <select name="role" required>
                                    <option value="kepala_dinas" <?= ($edit_user && $edit_user['role'] === 'kepala_dinas') ? 'selected' : '' ?>>Kepala Dinas</option>
                                    <option value="admin_bidang" <?= ($edit_user && $edit_user['role'] === 'admin_bidang') ? 'selected' : '' ?>>Admin Bidang</option>
                                    <!-- <option value="bagian_perencanaan" <?= ($edit_user && $edit_user['role'] === 'bagian_perencanaan') ? 'selected' : '' ?>>Bagian
                                        Perencanaan</option>
                                    <option value="bagian_keuangan" <?= ($edit_user && $edit_user['role'] === 'bagian_keuangan') ? 'selected' : '' ?>>Bagian Keuangan
                                    </option> -->
                                    <option value="staff" <?= ($edit_user && $edit_user['role'] === 'staff') ? 'selected' : '' ?>>Staff Pelaksana</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Jabatan</label>
                                <input type="text" name="jabatan" placeholder="Contoh: Analis Kebijakan Ahli Muda"
                                    value="<?= $edit_user ? htmlspecialchars($edit_user['jabatan']) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Bidang / Bagian</label>
                                <select name="id_bidang" id="select-bidang">
                                    <option value="">-- Pilih Bidang --</option>
                                    <?php foreach ($bidang_list as $b): ?>
                                        <option value="<?= $b['id_bidang'] ?>" <?= ($edit_user && $edit_user['id_bidang'] == $b['id_bidang']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['nama_bidang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="container-seksi">
                                <label>Seksi / Sub Bagian</label>
                                <select name="id_seksi" id="select-seksi">
                                    <option value="">-- Pilih Seksi --</option>
                                    <!-- Options loaded via JS -->
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label><?= $edit_user ? 'Ganti Password (Kosongkan jika tidak diubah)' : 'Password Akun *' ?></label>
                                <input type="password" name="password" <?= $edit_user ? '' : 'required' ?>
                                    placeholder="Minimal 8 karakter">
                            </div>
                        </div>
                        <div class="form-actions">
                            <?php if ($edit_user): ?>
                                <a href="manajemen_pengguna.php" class="btn btn-ghost">Batal Edit</a>
                            <?php else: ?>
                                <button type="reset" class="btn btn-ghost">Reset</button>
                            <?php endif; ?>
                            <button type="submit"
                                class="btn btn-primary"><?= $edit_user ? 'Perbarui Data' : 'Buat Akun Pengguna' ?></button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const seksiData = <?= json_encode($seksi_list) ?>;
        const currentSeksi = <?= json_encode($edit_user ? $edit_user['id_seksi'] : null) ?>;

        function updateSeksi(bidangId) {
            const seksiSelect = document.getElementById('select-seksi');
            const seksiContainer = document.getElementById('container-seksi');
            seksiSelect.innerHTML = '<option value="">-- Pilih Seksi --</option>';

            const filtered = seksiData.filter(s => s.id_bidang == bidangId);

            if (filtered.length > 0) {
                seksiContainer.style.display = 'block';
                filtered.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id_seksi;
                    opt.textContent = s.nama_seksi;
                    if (s.id_seksi == currentSeksi) opt.selected = true;
                    seksiSelect.appendChild(opt);
                });
            } else {
                seksiContainer.style.display = 'none';
                seksiSelect.value = "";
            }
        }

        document.getElementById('select-bidang').addEventListener('change', (e) => updateSeksi(e.target.value));

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const btnText = btn.innerText.toLowerCase();
                if (btnText.includes('daftar') && tabId === 'daftar') btn.classList.add('active');
                if (btnText.includes('tambah') && tabId === 'tambah') btn.classList.add('active');
            });
            document.querySelectorAll('.module-section').forEach(sec => sec.classList.remove('active'));
            const targetSec = document.getElementById('section-' + tabId);
            if (targetSec) targetSec.classList.add('active');
        }

        // Init Seksi if Edit or if Bidang is somehow selected
        const initialBidang = document.getElementById('select-bidang').value;
        if (initialBidang) updateSeksi(initialBidang);

        // Check hash
        if (window.location.hash === '#tambah') switchTab('tambah');
    </script>
</body>

</html>