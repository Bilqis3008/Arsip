<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$id_seksi = $admin['id_seksi'];
$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// Fetch mappings of Admin Bidang by id_bidang
$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}

$search = $_GET['search'] ?? '';

// --- MAIL MASUK (Unfinished for this Seksi) ---
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
      LEFT JOIN bidang b ON sm.id_bidang = b.id_bidang
      LEFT JOIN seksi s ON sm.id_seksi = s.id_seksi
      LEFT JOIN users u_tujuan ON d.nip_tujuan = u_tujuan.nip 
      WHERE sm.id_seksi = ? AND sm.status NOT IN ('selesai', 'diarsipkan') AND sm.id_surat_masuk NOT IN (
          SELECT id_surat_masuk FROM surat_keluar WHERE status = 'diarsipkan' AND id_surat_masuk IS NOT NULL
      )";

$params_m = [$id_seksi];
if ($search) {
    $query_m .= " AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)";
    array_push($params_m, "%$search%", "%$search%", "%$search%");
}
$stmt_m = $pdo->prepare($query_m);
$stmt_m->execute($params_m);
$mails_m = $stmt_m->fetchAll();
foreach ($mails_m as &$m) { $m['tipe'] = 'masuk'; }
unset($m);

// --- MAIL KELUAR (Drafted by this Seksi) ---
$query_k = "SELECT sk.*, u.nama as pengirim_user, u.id_bidang, s.nama_seksi, b.nama_bidang 
      FROM surat_keluar sk 
      LEFT JOIN users u ON sk.uploaded_by = u.nip 
      LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
      LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
      WHERE sk.status != 'diarsipkan' AND u.id_seksi = ?";

$params_k = [$id_seksi];
if ($search) {
    $query_k .= " AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ? OR sk.tujuan LIKE ?)";
    array_push($params_k, "%$search%", "%$search%", "%$search%");
}
$stmt_k = $pdo->prepare($query_k);
$stmt_k->execute($params_k);
$mails_k = $stmt_k->fetchAll();
foreach ($mails_k as &$k) { $k['tipe'] = 'keluar'; }
unset($k);

// Merge & Sort newest first
$mails = array_merge($mails_m, $mails_k);
usort($mails, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Alur Surat - Staff Panel</title>
    <link class="page-css" rel="stylesheet" href="../css/staff/home.css">
    <link class="page-css" rel="stylesheet" href="../css/staff/monitoring.css">
    <link class="page-css" rel="stylesheet" href="../css/notifications.css">
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
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Monitoring Alur Surat</h1>
                <p>Pantau berkas yang sedang diproses oleh seksi Anda.</p>
            </div>
            <div class="header-actions">
                <div class="date-box-header">
                    <div class="date-box-label">Tanggal</div>
                    <div class="date-box-value"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile-header">
                    <div class="user-info-header">
                        <span class="user-name-header"><?= htmlspecialchars((string)($admin['nama'] ?? '')) ?></span>
                        <span class="user-role-header">Staf <?= htmlspecialchars((string)($admin['nama_seksi'] ?? 'Seksi')) ?></span>
                    </div>
                    <div class="user-avatar-header"><?= strtoupper(substr((string)($admin['nama_seksi'] ?? ''), 0, 1) ?: 'S') ?></div>
                </div>
            </div>
        </header>

        <div class="content-body content-body-monitoring">
            <!-- Monitoring Card -->
            <div class="card">
                <div class="table-controls">
                    <form method="GET" class="search-box">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" name="search" placeholder="Cari perihal, nomor surat..." value="<?= htmlspecialchars((string)($search ?? '')) ?>">
                    </form>
                </div>

                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Info Surat & Tipe</th>
                                <th>Tahap Posisi Saat Ini</th>
                                <th>Aksi Tracker</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mails)): ?>
                                <tr><td colspan="3" class="empty-state-monitoring">Tidak ada berkas yang sedang diproses oleh seksi ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td>
                                        <div class="info-cell">
                                            <span class="type-badge <?= $m['tipe'] == 'masuk' ? 'type-masuk' : 'type-keluar' ?>">Surat <?= $m['tipe'] ?></span><br>
                                            <b class="bold-info-text"><?= htmlspecialchars($m['perihal'] ?? '') ?></b>
                                            <span>No: <?= htmlspecialchars($m['tipe'] === 'masuk' ? ($m['nomor_surat'] ?? '') : ($m['nomor_surat_keluar'] ?? '')) ?></span>
                                            <span><?= $m['tipe'] === 'masuk' ? 'Pengirim' : 'Tujuan' ?>: <?= htmlspecialchars($m['tipe'] === 'masuk' ? ($m['pengirim'] ?? '') : ($m['tujuan'] ?? '')) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($m['tipe'] === 'masuk'): ?>
                                            <div class="step-indicator-custom warning"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Tanggungan Tindak Lanjut Seksi</div>
                                            <div class="step-subtext-custom">Disahkan ke: <?= htmlspecialchars((string)($m['nama_seksi'] ?? 'Seksi Anda')) ?></div>
                                        <?php else: ?>
                                            <?php if ($m['status'] === 'draft'): ?>
                                                <div class="step-indicator-custom danger-orange"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Draft Awal (Seksi Anda)</div>
                                            <?php elseif ($m['status'] === 'pending_approval'): ?>
                                                <div class="step-indicator-custom warning"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Menunggu Validasi Verifikator/Admin</div>
                                            <?php elseif ($m['status'] === 'disetujui'): ?>
                                                <div class="step-indicator-custom success"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Disetujui (Tunggu Distribusi)</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-tracker-compact" onclick="showTracker('<?= $m['tipe'] ?>', <?= $m['tipe'] === 'masuk' ? $m['id_surat_masuk'] : $m['id_surat_keluar'] ?>)">
                                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Tracker
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

    <!-- Tracking Modal -->
    <div class="modal-overlay" id="tracker-modal" onclick="closeTracker()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeTracker()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 class="modal-title-custom">Live Tracking Alur Surat</h3>
            <p id="tracker-subtitle" class="modal-desc-custom"></p>
            
            <div id="tracker-mail-info" class="tracker-info-box"></div>
 
            <div id="tracker-details">
                <div class="timeline" id="timeline-box"></div>
            </div>
        </div>
    </div>

    <script>
        const suratMasukData = <?= json_encode($mails_m) ?>;
        const suratKeluarData = <?= json_encode($mails_k) ?>;
        const namaKadin = <?= json_encode($kadin) ?>;
        const adminBidangDict = <?= json_encode($admin_bidang_list) ?>;

        function showTracker(tipe, id) {
            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';

            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';

            if (tipe === 'masuk') {
                const mail = suratMasukData.find(m => m.id_surat_masuk == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Masuk #${mail.nomor_surat}`;

                const tgl = new Date(mail.tanggal_terima).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
                infoBox.innerHTML = `
                    <div class="info-grid-2">
                        <div class="grid-col-span-2">
                            <span class="meta-label-bold">Perihal</span>
                            <strong class="meta-value-bold">${mail.perihal}</strong>
                        </div>
                        <div>
                            <span class="meta-label-bold">Pengirim</span>
                            <strong class="meta-value-text">${mail.pengirim}</strong>
                        </div>
                        <div>
                            <span class="meta-label-bold">Tanggal Terima</span>
                            <strong class="meta-value-text">${tgl}</strong>
                        </div>
                    </div>
                `;

                const sekreName = mail.nama_sekretariat || 'Staf Sekretariat';
                addTimelineItem(`${sekreName} (Sekretariat)`, 'Resepsionis/Sekretariat mencatat agenda baru.', mail.created_at, 'done');

                const kadinFull = `${namaKadin} (Kepala Dinas)`;
                addTimelineItem(kadinFull, 'Memberikan arah dan disposisi kepada unit bersangkutan.', mail.tanggal_disposisi, 'done');

                const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
                const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Telah dikonfirmasi/Tindak Lanjut Admin Bidang.`, null, 'done');

                const seksiTargetName = mail.nama_seksi || 'Staf Sub-Seksi';
                addTimelineItem(`${seksiTargetName}`, `Sedang ditindaklanjuti/digarap pembalasannya secara internal dalam meja Anda.`, null, 'active');

            } else { // 'keluar'
                const mail = suratKeluarData.find(m => m.id_surat_keluar == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Keluar #${mail.nomor_surat_keluar}`;

                const tgl = new Date(mail.tanggal_surat).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
                infoBox.innerHTML = `
                    <div class="info-grid-2">
                        <div class="grid-col-span-2">
                            <span class="meta-label-bold">Perihal</span>
                            <strong class="meta-value-bold">${mail.perihal}</strong>
                        </div>
                        <div>
                            <span class="meta-label-bold">Tujuan</span>
                            <strong class="meta-value-text">${mail.tujuan}</strong>
                        </div>
                        <div>
                            <span class="meta-label-bold">Tanggal Surat</span>
                            <strong class="meta-value-text">${tgl}</strong>
                        </div>
                    </div>
                `;

                const senderAdmin = mail.pengirim_user || 'Staf Penulis';
                const senderUnit = mail.nama_seksi || mail.nama_bidang || '';
                const senderFull = senderUnit ? `${senderAdmin} (Staf ${senderUnit})` : senderAdmin;
                
                const adminReviewer = adminBidangDict[mail.id_bidang] || 'Admin Perbidang';
                const reviewerUnit = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                const reviewerFull = `${adminReviewer} ${reviewerUnit}`;

                addTimelineItem(senderFull, 'Staf pengusul membuat draft surat.', mail.created_at, 'done');

                if (mail.status === 'draft') {
                    addTimelineItem('Penyempurnaan Draft', 'Masih berada di meja Anda dan belum disubmit ke verifikator.', null, 'active');
                } else if (mail.status === 'pending_approval') {
                    addTimelineItem(reviewerFull, 'Menunggu persetujuan / verifikasi draft surat dari Atasan.', null, 'active');
                } else if (mail.status === 'disetujui') {
                    addTimelineItem(reviewerFull, 'Telah di tinjau dan disetujui (Verifikasi passed).', null, 'done');
                    addTimelineItem('Sekretariat / Distribusi', 'Menunggu finalisasi arsip dan pemberian stempel/nomor rilis dari Sekretariat.', null, 'active');
                }
            }

            document.getElementById('tracker-modal').style.display = 'flex';
        }

        function addTimelineItem(title, desc, time, type) {
            const box = document.getElementById('timeline-box');
            const item = document.createElement('div');
            item.className = 'timeline-item ' + type;
            item.innerHTML = `
                <div class="timeline-content">
                    <h4>${title}</h4>
                    <p>${desc}</p>
                    ${time ? `<div class="timeline-time">${new Date(time).toLocaleString('id-ID')}</div>` : ''}
                </div>
            `;
            box.appendChild(item);
        }

        function closeTracker() {
            document.getElementById('tracker-modal').style.display = 'none';
        }
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
