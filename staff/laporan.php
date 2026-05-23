<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];

// --- FETCH ADMIN DATA ---
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$id_seksi = $admin['id_seksi'];
$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// --- HANDLE FILTERS ---
$jenis_laporan = $_GET['jenis_laporan'] ?? 'total_surat';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-t');

// --- FETCH DATA (ONLY FINISHED/DIARSIPKAN STATUS) ---
$report_masuk = [];
$report_keluar = [];

// For staff: surat_masuk where id_seksi = ? AND status = 'selesai'
if ($jenis_laporan === 'surat_masuk' || $jenis_laporan === 'total_surat') {
    $stmt_m = $pdo->prepare("SELECT sm.*, d.tanggal_disposisi, d.status_disposisi, b.nama_bidang, s.nama_seksi, u_in.nama as nama_sekretariat, u_tujuan.nama as nama_admin_bidang,
                            sk.status as reply_status, sk.nomor_surat_keluar as reply_no, sk.id_surat_keluar, sk.file_path as reply_file, u_reply.nama as nama_staf_reply
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
          LEFT JOIN (
              SELECT sk1.* FROM surat_keluar sk1
              INNER JOIN (
                  SELECT id_surat_masuk, MAX(id_surat_keluar) as max_id_sk
                  FROM surat_keluar WHERE id_surat_masuk IS NOT NULL
                  GROUP BY id_surat_masuk
              ) sk2 ON sk1.id_surat_keluar = sk2.max_id_sk
          ) sk ON sm.id_surat_masuk = sk.id_surat_masuk
          LEFT JOIN users u_reply ON sk.uploaded_by = u_reply.nip
          WHERE DATE(sm.tanggal_terima) BETWEEN ? AND ? 
          AND sm.id_seksi = ? 
          AND sm.status IN ('selesai', 'diarsipkan') 
          ORDER BY sm.created_at DESC");
    $stmt_m->execute([$date_start, $date_end, $id_seksi]);
    $report_masuk = $stmt_m->fetchAll();
}

// For staff: surat_keluar where id_seksi = ? AND status = 'diarsipkan'
if ($jenis_laporan === 'surat_keluar' || $jenis_laporan === 'total_surat') {
    $stmt_k = $pdo->prepare("SELECT sk.*, u.nama as pengirim, u.id_bidang, s.nama_seksi, b.nama_bidang 
          FROM surat_keluar sk 
          LEFT JOIN users u ON sk.uploaded_by = u.nip 
          LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
          LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
          WHERE DATE(sk.tanggal_surat) BETWEEN ? AND ? 
          AND u.id_seksi = ? 
          AND sk.status = 'diarsipkan' 
          ORDER BY sk.created_at DESC");
    $stmt_k->execute([$date_start, $date_end, $id_seksi]);
    $report_keluar = $stmt_k->fetchAll();
}

// --- TOTALS ---
$total_masuk_period = count($report_masuk);
$total_keluar_period = count($report_keluar);
$total_surat_period = $total_masuk_period + $total_keluar_period;

// Fetch mappings of Admin Bidang by id_bidang
$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Staff Panel</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/laporan.css">
    <link rel="stylesheet" href="../css/notifications.css">
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
            <a href="monitoring.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
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
                <h1>Laporan Arsip</h1>
                <p>Data surat yang telah diselesaikan dan diarsipkan.</p>
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

        <div class="content-body">
            
            <form method="GET" id="reportForm">
                <div class="filter-card filter-card-select">
                    <div class="form-group form-group-custom">
                        <label class="label-select-custom">Pilih Jenis Laporan Status Selesai / Arsip</label>
                        <select name="jenis_laporan" onchange="document.getElementById('reportForm').submit()" class="select-custom">
                            <option value="surat_masuk" <?= $jenis_laporan === 'surat_masuk' ? 'selected' : '' ?>>Surat Masuk Terselesaikan</option>
                            <option value="surat_keluar" <?= $jenis_laporan === 'surat_keluar' ? 'selected' : '' ?>>Surat Keluar Diarsipkan</option>
                            <option value="total_surat" <?= $jenis_laporan === 'total_surat' ? 'selected' : '' ?>>Laporan Keseluruhan Arsip Total</option>
                        </select>
                    </div>
                </div>

                <div class="filter-card filter-card-inputs">
                    <div class="form-group form-group-custom">
                        <label class="label-input-custom">Dari Tanggal</label>
                        <input type="date" name="date_start" value="<?= $date_start ?>" class="input-date-custom">
                    </div>
                    <div class="form-group form-group-custom">
                        <label class="label-input-custom">Sampai Tanggal</label>
                        <input type="date" name="date_end" value="<?= $date_end ?>" class="input-date-custom">
                    </div>
                    <button type="submit" class="btn btn-primary btn-filter-custom">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        Terapkan Filter
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success btn-print-custom">
                        <svg class="icon" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Cetak Laporan
                    </button>
                </div>
            </form>

            <?php 
                function renderSuratMasukTable($report_masuk) {
            ?>
                <table class="data-table table-custom">
                    <thead class="thead-custom">
                        <tr>
                            <th class="th-td-custom">Nomor Surat</th>
                            <th class="th-td-custom">Pengirim</th>
                            <th class="th-td-custom">Tanggal Terima</th>
                            <th class="th-td-custom">Perihal</th>
                            <th class="th-action-custom">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_masuk)): ?>
                            <tr><td colspan="5" class="td-empty-custom">Data tidak ditemukan pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($report_masuk as $p): ?>
                                <tr class="tr-custom">
                                    <td class="th-td-custom"><strong><?= htmlspecialchars($p['nomor_surat'] ?? '') ?></strong><br><small style="color: #64748b;"><?= htmlspecialchars($p['nomor_agenda'] ?? '') ?></small></td>
                                    <td class="th-td-custom"><?= htmlspecialchars($p['pengirim'] ?? '') ?></td>
                                    <td class="th-td-custom"><?= date('d M Y', strtotime($p['tanggal_terima'] ?? 'now')) ?></td>
                                    <td class="td-perihal-custom">
                                        <?= htmlspecialchars($p['perihal'] ?? '') ?>
                                        <?php if (!empty($p['reply_no'])): ?>
                                            <div class="reply-info-custom">
                                                <svg viewBox="0 0 24 24" class="reply-icon-custom"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                Balasan: <?= htmlspecialchars($p['reply_no'] ?? '') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-cell th-td-custom">
                                        <button class="action-btn action-btn-info" onclick="showTrackerMasuk(<?= (int)($p['id_surat_masuk'] ?? 0) ?>)" title="Tracking & Detail">
                                            <svg viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <?php if (!empty($p['file_path'])): ?>
                                            <a href="../<?= htmlspecialchars((string)$p['file_path']) ?>" target="_blank" class="action-btn action-btn-download" title="Lihat/Download Surat Masuk">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                        <?php else: ?>
                                            <button class="action-btn action-btn-disabled" title="Dokumen Tidak Tersedia" disabled>
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($p['id_surat_keluar'])): ?>
                                            <button class="action-btn" style="background:#10b981; color:white; border:none;" onclick="showTrackerKeluar(<?= (int)$p['id_surat_keluar'] ?>)" title="Detail Balasan (Surat Keluar)">
                                                <svg viewBox="0 0 24 24" style="width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:2;"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php } ?>

            <?php 
                function renderSuratKeluarTable($report_keluar) {
            ?>
                <table class="data-table table-custom">
                    <thead class="thead-custom">
                        <tr>
                            <th class="th-td-custom">Nomor Surat</th>
                            <th class="th-td-custom">Tujuan</th>
                            <th class="th-td-custom">Tanggal Surat</th>
                            <th class="th-td-custom">Perihal</th>
                            <th class="th-action-custom">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_keluar)): ?>
                            <tr><td colspan="5" class="td-empty-custom">Data tidak ditemukan pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($report_keluar as $k): ?>
                                <tr class="tr-custom">
                                    <td class="th-td-custom"><strong><?= htmlspecialchars($k['nomor_surat_keluar'] ?? '') ?></strong></td>
                                    <td class="th-td-custom"><?= htmlspecialchars($k['tujuan'] ?? '') ?></td>
                                    <td class="th-td-custom"><?= date('d M Y', strtotime($k['tanggal_surat'] ?? 'now')) ?></td>
                                    <td class="td-perihal-custom"><?= htmlspecialchars($k['perihal'] ?? '') ?></td>
                                    <td class="action-cell th-td-custom">
                                        <button class="action-btn action-btn-info" onclick="showTrackerKeluar(<?= (int)($k['id_surat_keluar'] ?? 0) ?>)" title="Tracking & Detail">
                                            <svg viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <?php if (!empty($k['file_path'])): ?>
                                            <a href="../uploads/surat_keluar/<?= htmlspecialchars((string)$k['file_path']) ?>" target="_blank" class="action-btn action-btn-download" title="Lihat/Download Dokumen">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                        <?php else: ?>
                                            <button class="action-btn action-btn-disabled" title="Dokumen Tidak Tersedia" disabled>
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php } ?>

            <?php if ($jenis_laporan === 'surat_masuk'): ?>
            <section class="module-section active">
                <div class="card card-table-wrap">
                    <?php renderSuratMasukTable($report_masuk); ?>
                </div>
            </section>

            <?php elseif ($jenis_laporan === 'surat_keluar'): ?>
            <section class="module-section active">
                <div class="card card-table-wrap">
                    <?php renderSuratKeluarTable($report_keluar); ?>
                </div>
            </section>

            <?php elseif ($jenis_laporan === 'total_surat'): ?>
            <section class="module-section active">
                <h3 class="section-title section-title-custom">Laporan Periode Aktif Arsip <span class="period-subtitle-custom">(<?= date('d M Y', strtotime($date_start)) ?> - <?= date('d M Y', strtotime($date_end)) ?>)</span></h3>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon icon-masuk">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Surat Masuk Terselesaikan</h4>
                            <div class="value"><?= $total_masuk_period ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon icon-keluar">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Surat Keluar Diarsipkan</h4>
                            <div class="value"><?= $total_keluar_period ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon icon-total">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Total Keseluruhan Arsip</h4>
                            <div class="value"><?= $total_surat_period ?></div>
                        </div>
                    </div>
                </div>

                <div class="margin-top-large"></div>
                <h3 class="section-title">Daftar Surat Masuk <span class="period-subtitle-custom">(Terselesaikan / Diarsipkan)</span></h3>
                <div class="card card-table-wrap">
                    <?php renderSuratMasukTable($report_masuk); ?>
                </div>

                <div class="margin-top-large"></div>
                <h3 class="section-title">Daftar Surat Keluar <span class="period-subtitle-custom">(Telah Diarsipkan)</span></h3>
                <div class="card card-table-wrap">
                    <?php renderSuratKeluarTable($report_keluar); ?>
                </div>

            </section>
            <?php endif; ?>
            
        </div>
    </main>

    <!-- Tracking Modal -->
    <div class="modal-overlay" id="tracker-modal" onclick="closeTracker()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeTracker()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 class="section-title-modal">Live Tracking Alur Surat</h3>
            <p id="tracker-subtitle" class="tracker-subtitle-text"></p>
            
            <div id="tracker-mail-info" class="tracker-info-box" style="display: none;"></div>

            <div id="tracker-details">
                <div class="timeline" id="timeline-box"></div>
            </div>
        </div>
    </div>

    <script>
        const suratMasukData = <?= json_encode($report_masuk) ?>;
        const suratKeluarData = <?= json_encode($report_keluar) ?>;
        const namaKadin = <?= json_encode($kadin) ?>;
        const adminBidangDict = <?= json_encode($admin_bidang_list) ?>;

        function showTrackerMasuk(id) {
            const mail = suratMasukData.find(m => m.id_surat_masuk == id);
            
            document.getElementById('tracker-subtitle').textContent = `Surat Masuk #${mail.nomor_surat}`;

            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';
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

            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';

            const sekreName = mail.nama_sekretariat || 'Staf Sekretariat';
            addTimelineItem(`${sekreName} (Sekretariat)`, 'Resepsionis/Sekretariat mencatat agenda baru.', mail.created_at, 'done');

            const kadinFull = `${namaKadin} (Kepala Dinas)`;
            addTimelineItem(kadinFull, 'Memberikan arah dan disposisi kepada unit bersangkutan.', mail.tanggal_disposisi, 'done');

            const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
            const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
            addTimelineItem(`${adminBidangName} ${deskripsiBidang}`.trim(), `Telah ditindaklanjuti and diselesaikan pada seksi/bidang.`, null, 'done');

            if (mail.reply_status) {
                const staffName = mail.nama_staf_reply || 'Staf Sub-Seksi';
                const seksiTitle = mail.nama_seksi ? `(Staf ${mail.nama_seksi})` : '(Staf Seksi)';
                addTimelineItem(`${staffName} ${seksiTitle}`, `Telah membuat tindak lanjut balasan (${mail.reply_no}).`, null, 'done');
                addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Telah memverifikasi dan menyetujui balasan.`, null, 'done');
                addTimelineItem('Finalisasi', 'Surat masuk tuntas dan balasan telah diterbitkan/diarsipkan.', null, 'done');
            } else {
                const divisiTarget = mail.nama_seksi ? (mail.nama_seksi + ' - ' + mail.nama_bidang) : (mail.nama_bidang || 'Seksi / Bidang Terkait');
                addTimelineItem('Arsip Digital', `Surat telah disimpan dalam database arsip pada ${divisiTarget}.`, null, 'done');
            }

            document.getElementById('tracker-modal').style.display = 'flex';
        }

        function showTrackerKeluar(id) {
            const mail = suratKeluarData.find(m => m.id_surat_keluar == id);
            
            document.getElementById('tracker-subtitle').textContent = `Surat Keluar #${mail.nomor_surat_keluar}`;

            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';
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

            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';

            const senderAdmin = mail.pengirim || 'Staf Penulis';
            const senderUnit = mail.nama_seksi || mail.nama_bidang || '';
            const senderFull = senderUnit ? `${senderAdmin} (Staf ${senderUnit})` : senderAdmin;
            
            const adminReviewer = adminBidangDict[mail.id_bidang] || 'Admin Perbidang';
            const reviewerUnit = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
            const reviewerFull = `${adminReviewer} ${reviewerUnit}`.trim();

            addTimelineItem(senderFull, 'Staf pengusul membuat draft surat.', mail.created_at, 'done');

            addTimelineItem(reviewerFull, 'Telah di tinjau dan disetujui (Verifikasi passed).', null, 'done');
            
            addTimelineItem('Arsip Digital', `Surat keluar telah dikirim and diarsipkan pada ${senderUnit || 'database arsip'}.`, null, 'done');

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
