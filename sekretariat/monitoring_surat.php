<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// Fetch mappings of Admin Bidang by id_bidang
$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}

$search = $_GET['search'] ?? '';

// --- MAIL MASUK (Unfinished) ---
$query_m = "SELECT sm.*, d.tanggal_disposisi, d.status_disposisi, b.nama_bidang, s.nama_seksi, u_in.nama as nama_sekretariat, u_tujuan.nama as nama_admin_bidang 
      FROM surat_masuk sm
      LEFT JOIN users u_in ON sm.input_by = u_in.nip 
      LEFT JOIN (
          SELECT d1.* FROM disposisi d1
          INNER JOIN (
              SELECT id_surat_masuk, MAX(id_disposisi) as max_id 
              FROM disposisi 
              GROUP BY id_surat_masuk
          ) d2 ON d1.id_disposisi = d2.max_id
      ) d ON sm.id_surat_masuk = d.id_surat_masuk
      LEFT JOIN bidang b ON d.id_bidang = b.id_bidang
      LEFT JOIN seksi s ON d.id_seksi = s.id_seksi
      LEFT JOIN users u_tujuan ON d.nip_tujuan = u_tujuan.nip 
      WHERE sm.status NOT IN ('selesai', 'diarsipkan')";

$params_m = [];
if ($search) {
    $query_m .= " AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)";
    $params_m = ["%$search%", "%$search%", "%$search%"];
}
$stmt_m = $pdo->prepare($query_m);
$stmt_m->execute($params_m);
$mails_m = $stmt_m->fetchAll();
foreach ($mails_m as &$m) { $m['tipe'] = 'masuk'; }
unset($m);

// --- MAIL KELUAR (Unfinished) ---
$query_k = "SELECT sk.*, u.nama as pengirim_user, u.id_bidang, s.nama_seksi, b.nama_bidang 
      FROM surat_keluar sk 
      LEFT JOIN users u ON sk.uploaded_by = u.nip 
      LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
      LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
      WHERE sk.status != 'diarsipkan'";

$params_k = [];
if ($search) {
    $query_k .= " AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ? OR sk.tujuan LIKE ?)";
    $params_k = ["%$search%", "%$search%", "%$search%"];
}
$stmt_k = $pdo->prepare($query_k);
$stmt_k->execute($params_k);
$mails_k = $stmt_k->fetchAll();
foreach ($mails_k as &$k) { $k['tipe'] = 'keluar'; }
unset($k);

// Merge & Sort newest first
$mails = array_merge($mails_m, $mails_k);
usort($mails, function($a, $b) {
    return strtotime((string)($b['created_at'] ?? 'now')) <=> strtotime((string)($a['created_at'] ?? 'now'));
});

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Surat - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
    <link rel="stylesheet" href="../css/sekretariat/monitoring_surat.css">
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
            <a href="monitoring_surat.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Monitoring Berkas</h1><p style="font-size:0.9rem; color:var(--text-muted);">Pantau posisi berkas surat yang sedang dalam proses alur kerja.</p></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <div class="table-controls" style="margin-bottom:2rem;">
                <form method="GET" class="search-box" style="max-width:400px; position:relative;">
                    <svg class="icon" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal atau nomor surat..." value="<?= htmlspecialchars((string)$search) ?>" style="padding-left:3rem; width:100%; padding-top:0.8rem; padding-bottom:0.8rem; border-radius:1rem; border:1px solid var(--border);">
                </form>
            </div>

            <div class="card">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Info & Tipe Berkas</th><th>Posisi Saat Ini</th><th style="text-align:center;">Tracker</th></tr></thead>
                        <tbody>
                            <?php if (empty($mails)): ?>
                                <tr><td colspan="3" style="text-align: center; padding: 5rem; color: var(--text-muted);">Tidak ada berkas yang sedang aktif diproses.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                    <tr>
                                        <td>
                                            <div class="info-cell">
                                                <span class="type-badge <?= $m['tipe'] == 'masuk' ? 'type-masuk' : 'type-keluar' ?>">Surat <?= ucfirst($m['tipe']) ?></span>
                                                <div style="font-weight: 800; color: var(--text-main); margin-top:0.5rem;"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top:2px;">No: <?= htmlspecialchars($m['tipe'] === 'masuk' ? ($m['nomor_surat'] ?? '') : ($m['nomor_surat_keluar'] ?? '')) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($m['tipe'] === 'masuk'): ?>
                                                <?php if (($m['status'] ?? '') === 'tercatat'): ?>
                                                    <div style="font-weight:700; color:var(--warning); display:flex; align-items:center; gap:0.5rem;"><div class="step-indicator current"></div> Menunggu Disposisi (Kadin)</div>
                                                <?php else: ?>
                                                    <div style="font-weight:700; color:var(--primary); display:flex; align-items:center; gap:0.5rem;"><div class="step-indicator active"></div> Proses Tindak Lanjut Bidang</div>
                                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-left:1.5rem;"><?= htmlspecialchars($m['nama_bidang'] ?? 'Unit Kerja') ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div style="font-weight:700; color:var(--primary); display:flex; align-items:center; gap:0.5rem;">
                                                    <div class="step-indicator current"></div> 
                                                    <?= ($m['status'] ?? '') === 'draft' ? 'Draft Awal (Staff)' : (($m['status'] ?? '') === 'pending_approval' ? 'Verifikasi Bidang' : 'Menunggu Arsip') ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <button onclick="showTracker('<?= $m['tipe'] ?>', <?= $m['tipe'] === 'masuk' ? ($m['id_surat_masuk'] ?? 0) : ($m['id_surat_keluar'] ?? 0) ?>)" class="btn" style="background:rgba(99, 102, 241, 0.1); color:var(--primary); border:none; padding:0.6rem 1.2rem; border-radius:0.8rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem;">
                                                <svg class="icon" style="width:16px; height:16px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> View Tracker
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="tracker-modal" class="modal-overlay">
        <div class="modal-content">
            <button onclick="closeTracker()" class="modal-close">✕</button>
            <h3 style="font-weight:800; font-size:1.5rem; margin-bottom:0.5rem;">Tracking Progress</h3>
            <p id="tracker-subtitle" style="color:var(--text-muted); font-size:0.9rem; margin-bottom:2rem;"></p>
            <div class="timeline" id="timeline-box"></div>
        </div>
    </div>

    <script>
        const smData = <?= json_encode($mails_m) ?>;
        const skData = <?= json_encode($mails_k) ?>;
        const kName = <?= json_encode($kadin) ?>;
        const abDict = <?= json_encode($admin_bidang_list) ?>;

        function showTracker(t, id) {
            const b = document.getElementById('timeline-box'); b.innerHTML = '';
            if (t === 'masuk') {
                const m = smData.find(x => x.id_surat_masuk == id);
                document.getElementById('tracker-subtitle').textContent = m.perihal;
                addI(m.nama_sekretariat || 'Sekretariat', 'Pencatatan agenda masuk.', m.created_at, 'done');
                if (m.status === 'tercatat') addI(kName + ' (Kadin)', 'Menunggu Disposisi', null, 'active');
                else {
                    addI(kName + ' (Kadin)', 'Telah didisposisikan.', m.tanggal_disposisi, 'done');
                    addI(m.nama_admin_bidang || 'Admin Bidang', 'Sedang dalam tindak lanjut unit.', null, 'active');
                }
            } else {
                const k = skData.find(x => x.id_surat_keluar == id);
                document.getElementById('tracker-subtitle').textContent = k.perihal;
                addI(k.pengirim_user || 'Staff', 'Pembuatan draf surat.', k.created_at, 'done');
                if (k.status === 'draft' || k.status === 'pending_approval') addI(abDict[k.id_bidang] || 'Admin Bidang', 'Menunggu verifikasi draf.', null, 'active');
                else if (k.status === 'disetujui') {
                    addI(abDict[k.id_bidang] || 'Admin Bidang', 'Draf telah disetujui.', null, 'done');
                    addI('Sekretariat', 'Menunggu pengarsipan final.', null, 'active');
                }
            }
            document.getElementById('tracker-modal').style.display = 'flex';
        }
        function addI(ti, de, tm, ty) {
            const i = document.createElement('div'); i.className = 'timeline-item ' + ty;
            i.innerHTML = `<div class="timeline-content"><h4>${ti}</h4><p>${de}</p>${tm ? `<div class="timeline-time">${new Date(tm).toLocaleString('id-ID')}</div>` : ''}</div>`;
            document.getElementById('timeline-box').appendChild(i);
        }
        function closeTracker() { document.getElementById('tracker-modal').style.display = 'none'; }
        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) closeTracker(); };
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
