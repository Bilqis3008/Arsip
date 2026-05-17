<?php
session_start();
require_once '../config/db.php';

// Auth Check for Kepala Dinas
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'kepala_dinas') {
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
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Surat - Kepala Dinas</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/monitoring_surat.css">
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
            <a href="home.php" class="menu-item "><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Pemantauan Alur Surat Aktif</h1></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Kepala Dinas</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body content-body-padding">
            <!-- Monitoring Card -->
            <div class="card">
                <div class="table-controls">
                    <form method="GET" class="search-box">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search" placeholder="Cari perihal, nomor surat..." value="<?= htmlspecialchars($search) ?>">
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
                                <tr><td colspan="3" class="empty-table-cell">Tidak ada berkas yang sedang diproses.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td>
                                        <div class="info-cell">
                                            <span class="type-badge <?= $m['tipe'] == 'masuk' ? 'type-masuk' : 'type-keluar' ?>">Surat <?= $m['tipe'] ?></span><br>
                                            <b class="mail-perihal"><?= htmlspecialchars($m['perihal']) ?></b>
                                            <span>No: <?= htmlspecialchars($m['tipe'] === 'masuk' ? $m['nomor_surat'] : $m['nomor_surat_keluar']) ?></span>
                                            <span><?= $m['tipe'] === 'masuk' ? 'Pengirim' : 'Tujuan' ?>: <?= htmlspecialchars($m['tipe'] === 'masuk' ? $m['pengirim'] : $m['tujuan']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($m['tipe'] === 'masuk'): ?>
                                            <?php if ($m['status'] === 'tercatat'): ?>
                                                <div class="status-warning"><svg class="icon status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Menunggu Disposisi (Meja Anda)</div>
                                            <?php elseif ($m['status'] === 'didispokan' || $m['status'] === 'diteruskan'): ?>
                                                <div class="status-info"><svg class="icon status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Proses Tindak Lanjut Bidang</div>
                                                <div class="status-sub">Target: <?= htmlspecialchars($m['nama_bidang'] ?: 'Bidang / Bagian') ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($m['status'] === 'draft'): ?>
                                                <div class="status-warning"><svg class="icon status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Draft Awal (Staf Seksi)</div>
                                            <?php elseif ($m['status'] === 'pending_approval'): ?>
                                                <div class="status-orange"><svg class="icon status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Menunggu Validasi Perbidang</div>
                                            <?php elseif ($m['status'] === 'disetujui'): ?>
                                                <div class="status-success"><svg class="icon status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg> Disetujui (Distribusi / Tunggu Arsip)</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-tracker" onclick="showTracker('<?= $m['tipe'] ?>', <?= $m['tipe'] === 'masuk' ? $m['id_surat_masuk'] : $m['id_surat_keluar'] ?>)">
                                            <svg class="icon btn-tracker-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Tracker
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
                <svg class="modal-close-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 class="modal-title">Live Tracking Alur Surat</h3>
            <p id="tracker-subtitle" class="modal-subtitle"></p>
            
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

            if (tipe === 'masuk') {
                const mail = suratMasukData.find(m => m.id_surat_masuk == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Masuk #${mail.nomor_surat}`;

                const sekreName = mail.nama_sekretariat || 'Staf Sekretariat';
                addTimelineItem(`${sekreName} (Sekretariat)`, 'Resepsionis/Sekretariat mencatat agenda baru.', mail.created_at, 'done');

                const kadinFull = `${namaKadin} (Kepala Dinas)`;
                if (mail.status === 'tercatat') {
                    addTimelineItem(kadinFull, 'Menunggu Keputusan / Disposisi', null, 'active');
                } else {
                    addTimelineItem(kadinFull, 'Memberikan arah dan disposisi kepada unit bersangkutan.', mail.tanggal_disposisi, 'done');
                }

                if (mail.status === 'didispokan' || mail.status === 'diteruskan') {
                    const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
                    const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                    addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Sedang ditindaklanjuti pada seksi/bidang.`, null, 'active');
                }

            } else { // 'keluar'
                const mail = suratKeluarData.find(m => m.id_surat_keluar == id);
                document.getElementById('tracker-subtitle').textContent = `Surat Keluar #${mail.nomor_surat_keluar}`;

                const senderAdmin = mail.pengirim_user || 'Staf Penulis';
                const senderUnit = mail.nama_seksi || mail.nama_bidang || '';
                const senderFull = senderUnit ? `${senderAdmin} (Staf ${senderUnit})` : senderAdmin;
                
                const adminReviewer = adminBidangDict[mail.id_bidang] || 'Admin Perbidang';
                const reviewerUnit = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
                const reviewerFull = `${adminReviewer} ${reviewerUnit}`;

                addTimelineItem(senderFull, 'Staf pengusul membuat draft surat.', mail.created_at, 'done');

                if (mail.status === 'draft') {
                    addTimelineItem(reviewerFull, 'Menunggu persetujuan / verifikasi draft surat.', null, 'active');
                } else if (mail.status === 'pending_approval') {
                    addTimelineItem(reviewerFull, 'Menunggu persetujuan / verifikasi draft surat.', null, 'active');
                } else if (mail.status === 'disetujui') {
                    addTimelineItem(reviewerFull, 'Telah di tinjau dan disetujui (Verifikasi passed).', null, 'done');
                    addTimelineItem('Sekretariat / Distribusi', 'Menunggu finalisasi arsip dan pemberian stempel/nomor rilis.', null, 'active');
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
