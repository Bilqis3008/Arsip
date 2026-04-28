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
    $nip = $_POST['nip'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $asal_instansi = $_POST['asal_instansi'] ?? null;
    $jabatan = $_POST['jabatan'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $id_bidang = !empty($_POST['id_bidang']) ? $_POST['id_bidang'] : null;
    $id_seksi = !empty($_POST['id_seksi']) ? $_POST['id_seksi'] : null;

    if ($action === 'save_user') {
        $password = password_hash((string)($_POST['password'] ?? '12345678'), PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, password, no_hp, asal_instansi, jabatan, role, id_bidang, id_seksi, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
            $stmt->execute([$nip, $nama, $email, $password, $no_hp, $asal_instansi, $jabatan, $role, $id_bidang, $id_seksi]);
            header("Location: manajemen_pengguna.php?status=created");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Gagal menambah pengguna: " . $e->getMessage();
        }
    } elseif ($action === 'update_user') {
        try {
            $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, asal_instansi = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ? WHERE nip = ?";
            $params = [$nama, $email, $no_hp, $asal_instansi, $jabatan, $role, $id_bidang, $id_seksi, $nip];

            if (!empty($_POST['password'])) {
                $sql = "UPDATE users SET nama = ?, email = ?, no_hp = ?, asal_instansi = ?, jabatan = ?, role = ?, id_bidang = ?, id_seksi = ?, password = ? WHERE nip = ?";
                $params = [$nama, $email, $no_hp, $asal_instansi, $jabatan, $role, $id_bidang, $id_seksi, password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $nip];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            header("Location: manajemen_pengguna.php?status=updated");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui pengguna: " . $e->getMessage();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_nip'])) {
    $nip_to_delete = $_GET['delete_nip'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE nip = ?");
        $stmt->execute([$nip_to_delete]);
        header("Location: manajemen_pengguna.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Gagal menghapus pengguna: " . $e->getMessage();
    }
}

// --- MESSAGES ---
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'deleted') $success_msg = "Akun pengguna berhasil dihapus!";
    if ($_GET['status'] === 'created') $success_msg = "Pengguna baru berhasil ditambahkan!";
    if ($_GET['status'] === 'updated') $success_msg = "Data pengguna berhasil diperbarui!";
}

// --- FETCH DATA ---
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

$usersByRole = [];
foreach ($users as $u) {
    $usersByRole[$u['role']][] = $u;
}

$bidang_list = $pdo->query("SELECT * FROM bidang ORDER BY nama_bidang ASC")->fetchAll();
$seksi_list = $pdo->query("SELECT * FROM seksi ORDER BY nama_seksi ASC")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
    <link rel="stylesheet" href="../css/sekretariat/manajemen_pengguna.css">
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
            <a href="manajemen_pengguna.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Manajemen Pengguna</h1></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($success_msg): ?>
                <div class="alert-message alert-success"><svg class="icon alert-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> <?= $success_msg ?></div>
            <?php endif; ?>

            <div class="module-tabs">
                <button class="tab-btn active" onclick="switchTab('daftar')"><svg class="icon"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><circle cx="3" cy="6" r="1"></circle><circle cx="3" cy="12" r="1"></circle><circle cx="3" cy="18" r="1"></circle></svg> Daftar Pengguna</button>
                <button class="tab-btn" onclick="switchTab('tambah')"><svg class="icon"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg> Tambah Baru</button>
            </div>

            <section id="section-daftar" class="module-section active">
                <div class="table-controls" style="margin-bottom:2rem;">
                    <form method="GET" class="search-box" style="max-width:400px; position:relative;">
                        <svg class="icon" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Cari NIP, nama, atau email..." value="<?= htmlspecialchars((string)$search) ?>" style="padding-left:3rem;">
                    </form>
                    <form method="GET" class="filter-group">
                        <select name="role" onchange="this.form.submit()" style="padding:0.8rem 1.2rem; border-radius:1rem; border:1px solid var(--border); font-weight:700;">
                            <option value="">Semua Role</option>
                            <option value="sekretariat" <?= $role_filter === 'sekretariat' ? 'selected' : '' ?>>Sekretariat</option>
                            <option value="admin_bidang" <?= $role_filter === 'admin_bidang' ? 'selected' : '' ?>>Admin Bidang</option>
                            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User Umum</option>
                        </select>
                    </form>
                </div>

                <?php foreach ($usersByRole as $roleKey => $roleUsers): ?>
                    <div class="role-group">
                        <div class="role-header">
                            <div class="role-title-wrapper">
                                <div class="role-icon"><svg class="icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></div>
                                <h3><?= ucwords(str_replace('_', ' ', (string)$roleKey)) ?></h3>
                            </div>
                            <span class="badge badge-role"><?= count($roleUsers) ?> Personel</span>
                        </div>
                        <div class="data-table-container">
                            <table class="data-table">
                                <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Unit Kerja</th><th style="text-align:right;">Aksi</th></tr></thead>
                                <tbody>
                                    <?php foreach ($roleUsers as $u): ?>
                                        <tr>
                                            <td><span class="nip-cell"><?= htmlspecialchars($u['nip'] ?? '') ?></span></td>
                                            <td><div class="user-info-cell"><div class="user-avatar"><?= strtoupper(substr((string)$u['nama'], 0, 1)) ?></div><div style="font-weight:700;"><?= htmlspecialchars($u['nama'] ?? '') ?></div></div></td>
                                            <td><div class="jabatan-text"><?= htmlspecialchars($u['jabatan'] ?? '-') ?></div></td>
                                            <td><div class="bidang-text"><?= htmlspecialchars($u['nama_bidang'] ?? ($u['asal_instansi'] ?? 'Sekretariat')) ?></div></td>
                                            <td class="action-btns">
                                                <button onclick='viewDetail(<?= htmlspecialchars(json_encode($u)) ?>)' class="action-btn btn-view" title="Detail"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                                                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)' class="action-btn btn-edit" title="Edit"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                                                <button onclick="confirmDelete('<?= $u['nip'] ?>')" class="action-btn btn-delete" title="Hapus"><svg class="icon"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section id="section-tambah" class="module-section">
                <div class="card">
                    <div class="card-header"><h2>Registrasi Pengguna Baru</h2><p>Daftarkan akun personel atau user umum baru ke dalam sistem.</p></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_user">
                        <div class="form-grid">
                            <div class="form-group"><label>NIP Pegawai *</label><input type="text" name="nip" required placeholder="Contoh: 19880101..."></div>
                            <div class="form-group"><label>Nama Lengkap *</label><input type="text" name="nama" required></div>
                            <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                            <div class="form-group"><label>No HP</label><input type="text" name="no_hp"></div>
                            <div class="form-group"><label>Role *</label><select name="role" id="add-role" required onchange="toggleFieldsVisibility(this.value, 'add')"><option value="admin_bidang">Admin Bidang</option><option value="staff">Staff</option><option value="user">User Umum</option><option value="sekretariat">Sekretariat</option></select></div>
                            <div class="form-group"><label>Jabatan</label><input type="text" name="jabatan"></div>
                            <div class="form-group d-none" id="add-container-bidang"><label>Bidang</label><select name="id_bidang" id="add-select-bidang" onchange="updateSeksiOptions(this.value, 'add')"><option value="">-- Pilih Bidang --</option><?php foreach ($bidang_list as $b): ?><option value="<?= $b['id_bidang'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group d-none" id="add-container-seksi"><label>Seksi</label><select name="id_seksi" id="add-select-seksi"><option value="">-- Pilih Seksi --</option></select></div>
                            <div class="form-group d-none" id="add-container-instansi"><label>Asal Instansi</label><input type="text" name="asal_instansi"></div>
                            <div class="form-group full-width"><label>Password Akun *</label><input type="password" name="password" required></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary">Simpan Pengguna</button></div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <div id="detailModal" class="modal-overlay">
        <div class="modal-card w-750">
            <div class="modal-header"><h3>Profil Pengguna</h3><button onclick="closeModal()" class="btn-close">✕</button></div>
            <div id="modalContent" class="modal-body"></div>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-card w-650">
            <div class="modal-header"><h3>Edit Pengguna</h3><button onclick="closeEditModal()" class="btn-close">✕</button></div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user"><input type="hidden" name="nip" id="edit_nip">
                <div class="form-grid">
                    <div class="form-group"><label>Nama</label><input type="text" name="nama" id="edit_nama" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                    <div class="form-group"><label>No HP</label><input type="text" name="no_hp" id="edit_no_hp"></div>
                    <div class="form-group"><label>Jabatan</label><input type="text" name="jabatan" id="edit_jabatan"></div>
                    <div class="form-group"><label>Role</label><select name="role" id="edit_role" required onchange="toggleFieldsVisibility(this.value, 'edit')"><option value="admin_bidang">Admin Bidang</option><option value="staff">Staff</option><option value="user">User Umum</option><option value="sekretariat">Sekretariat</option></select></div>
                    <div class="form-group" id="edit_container_bidang"><label>Bidang</label><select name="id_bidang" id="edit_id_bidang" onchange="updateSeksiOptions(this.value, 'edit')"><option value="">-- Pilih --</option><?php foreach ($bidang_list as $b): ?><option value="<?= $b['id_bidang'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group" id="edit_container_seksi"><label>Seksi</label><select name="id_seksi" id="edit_id_seksi"></select></div>
                    <div class="form-group" id="edit_container_instansi"><label>Instansi</label><input type="text" name="asal_instansi" id="edit_asal_instansi"></div>
                    <div class="form-group full-width"><label>Ganti Password (Opsional)</label><input type="password" name="password"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Update Akun</button></div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-card w-400">
            <div class="delete-icon-wrapper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/></svg></div>
            <h3 class="delete-text">Hapus Akun?</h3><p class="delete-subtext">Data pengguna akan dihapus permanen dari sistem.</p>
            <div class="modal-footer center"><button onclick="closeDeleteModal()" class="btn">Batal</button><a href="#" id="confirmDeleteBtn" class="btn btn-danger">Hapus Sekarang</a></div>
        </div>
    </div>

    <script>
        const seksiData = <?= json_encode($seksi_list) ?>;
        function switchTab(id) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.onclick.toString().includes(id)));
            document.querySelectorAll('.module-section').forEach(s => s.classList.toggle('active', s.id === 'section-' + id));
        }
        function toggleFieldsVisibility(r, m) {
            const b = document.getElementById(m + (m==='edit'?'_container_bidang':'-container-bidang'));
            const s = document.getElementById(m + (m==='edit'?'_container_seksi':'-container-seksi'));
            const i = document.getElementById(m + (m==='edit'?'_container_instansi':'-container-instansi'));
            if(r==='user') { b.classList.add('d-none'); s.classList.add('d-none'); i.classList.remove('d-none'); }
            else if(r==='sekretariat') { b.classList.add('d-none'); s.classList.add('d-none'); i.classList.add('d-none'); }
            else if(r==='admin_bidang') { b.classList.remove('d-none'); s.classList.add('d-none'); i.classList.add('d-none'); }
            else { b.classList.remove('d-none'); s.classList.remove('d-none'); i.classList.add('d-none'); }
        }
        function updateSeksiOptions(bid, mode, sel) {
            const s = document.getElementById(mode === 'edit' ? 'edit_id_seksi' : 'add-select-seksi');
            s.innerHTML = '<option value="">-- Pilih Seksi --</option>';
            seksiData.filter(x => x.id_bidang == bid).forEach(x => {
                const o = document.createElement('option'); o.value = x.id_seksi; o.textContent = x.nama_seksi;
                if(sel && x.id_seksi == sel) o.selected = true; s.appendChild(o);
            });
        }
        function viewDetail(u) {
            const c = document.getElementById('modalContent');
            c.innerHTML = `<div class="detail-user-header"><div class="detail-avatar">${u.nama.charAt(0)}</div><div><div class="detail-user-name">${u.nama}</div><div class="detail-user-email">${u.email || '-'}</div></div></div><div class="detail-info-grid"><div class="detail-item"><div class="detail-label">NIP</div><div class="detail-value">${u.nip}</div></div><div class="detail-item"><div class="detail-label">HP</div><div class="detail-value">${u.no_hp || '-'}</div></div><div class="detail-item"><div class="detail-label">Role</div><div class="badge badge-role">${u.role}</div></div><div class="detail-item"><div class="detail-label">Unit</div><div class="detail-value">${u.nama_bidang || u.asal_instansi || 'Sekretariat'}</div></div></div>`;
            document.getElementById('detailModal').classList.add('active');
        }
        function openEditModal(u) {
            document.getElementById('edit_nip').value = u.nip; document.getElementById('edit_nama').value = u.nama;
            document.getElementById('edit_email').value = u.email; document.getElementById('edit_no_hp').value = u.no_hp;
            document.getElementById('edit_jabatan').value = u.jabatan; document.getElementById('edit_role').value = u.role;
            document.getElementById('edit_asal_instansi').value = u.asal_instansi; document.getElementById('edit_id_bidang').value = u.id_bidang;
            toggleFieldsVisibility(u.role, 'edit'); updateSeksiOptions(u.id_bidang, 'edit', u.id_seksi);
            document.getElementById('editModal').classList.add('active');
        }
        function confirmDelete(n) { document.getElementById('confirmDeleteBtn').href = '?delete_nip=' + n; document.getElementById('deleteModal').classList.add('active'); }
        function closeModal() { document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active')); }
        function closeEditModal() { closeModal(); } function closeDeleteModal() { closeModal(); }
        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) closeModal(); };
        toggleFieldsVisibility(document.getElementById('add-role').value, 'add');
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>