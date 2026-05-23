<?php
session_start();
require_once 'config/db.php';

// Search Logic
$search_result = null;
$error_message = '';
$search_type = null;

if (isset($_GET['nomor_surat']) && !empty(trim($_GET['nomor_surat']))) {
    $nomor_surat = trim($_GET['nomor_surat']);
    
    // Search in surat_masuk
    $stmt = $pdo->prepare("SELECT * FROM surat_masuk WHERE nomor_surat = ? OR nomor_agenda = ?");
    $stmt->execute([$nomor_surat, $nomor_surat]);
    $search_result = $stmt->fetch();
    
    if ($search_result) {
        $search_type = 'masuk';
    } else {
        // Search in surat_keluar
        $stmt2 = $pdo->prepare("SELECT * FROM surat_keluar WHERE nomor_surat_keluar = ?");
        $stmt2->execute([$nomor_surat]);
        $search_result = $stmt2->fetch();
        if ($search_result) {
            $search_type = 'keluar';
        }
    }
    
    if (!$search_result) {
        $error_message = "Surat dengan nomor tersebut tidak ditemukan.";
    }
}

// Determine dashboard link if user is logged in
$dashboard_link = '';
if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'sekretariat':
            $dashboard_link = 'sekretariat/home.php';
            break;
        case 'kepala_dinas':
            $dashboard_link = 'kadin/home.php';
            break;
        case 'admin_bidang':
            $dashboard_link = 'admin_perbidang/home.php';
            break;
        case 'staff':
            $dashboard_link = 'staff/home.php';
            break;
        case 'user':
            $dashboard_link = 'index.php';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari & Lacak Surat - Arsip Digital</title>
    <link rel="stylesheet" href="css/public_search.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <a href="index.php" class="logo">
            <i class="fas fa-archive"></i>
            <span>Arsip Digital</span>
        </a>
        <div class="nav-actions">
            <?php if (!empty($dashboard_link)): ?>
                <a href="<?= htmlspecialchars($dashboard_link) ?>" class="btn-dashboard">
                    <i class="fas fa-tachometer-alt"></i> Ke Dashboard
                </a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="content">
        <div class="hero-section">
            <h1>Lacak Status & Posisi Surat Anda</h1>
            <p>Masukkan Nomor Surat atau Nomor Agenda Anda di bawah ini untuk melihat progress tindak lanjut secara real-time.</p>
            
            <form action="" method="GET" class="search-form">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="nomor_surat" placeholder="Contoh: 123/B1/2024 atau Nomor Agenda" value="<?= isset($_GET['nomor_surat']) ? htmlspecialchars($_GET['nomor_surat']) : '' ?>" required>
                    <button type="submit">Cari Surat</button>
                </div>
            </form>
        </div>

        <!-- Alert Error -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search Result Card -->
        <?php if ($search_result): ?>
            <div class="result-card">
                <div class="card-header">
                    <h2>Informasi Detail Surat</h2>
                    <span class="status-badge status-<?= strtolower($search_result['status']) ?>">
                        <?= htmlspecialchars(ucfirst($search_result['status'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <?php if ($search_type === 'masuk'): ?>
                            <div class="info-item">
                                <label>Nomor Agenda</label>
                                <span><?= htmlspecialchars($search_result['nomor_agenda'] ?? '-') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <label><?= $search_type === 'masuk' ? 'Nomor Surat' : 'Nomor Surat Keluar' ?></label>
                            <span><?= htmlspecialchars($search_type === 'masuk' ? ($search_result['nomor_surat'] ?? '-') : ($search_result['nomor_surat_keluar'] ?? '-')) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Tanggal Surat</label>
                            <span><?= date('d M Y', strtotime($search_result['tanggal_surat'])) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label><?= $search_type === 'masuk' ? 'Tanggal Terima' : 'Tujuan Instansi' ?></label>
                            <span><?= $search_type === 'masuk' ? date('d M Y', strtotime($search_result['tanggal_terima'])) : htmlspecialchars($search_result['tujuan'] ?? '-') ?></span>
                        </div>
                        
                        <div class="info-item full-width">
                            <label><?= $search_type === 'masuk' ? 'Pengirim' : 'Perihal' ?></label>
                            <span><?= htmlspecialchars($search_type === 'masuk' ? ($search_result['pengirim'] ?? '-') : ($search_result['perihal'] ?? '-')) ?></span>
                        </div>
                        
                        <?php if ($search_type === 'masuk'): ?>
                            <div class="info-item full-width">
                                <label>Perihal</label>
                                <span><?= htmlspecialchars($search_result['perihal'] ?? '-') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline Status -->
                    <div class="status-timeline">
                        <h3>Progress Tindak Lanjut Surat <?= $search_type === 'masuk' ? 'Masuk' : 'Keluar' ?></h3>
                        <div class="timeline">
                            <?php if ($search_type === 'masuk'): ?>
                                <!-- TIMELINE SURAT MASUK -->
                                <div class="timeline-item <?= in_array($search_result['status'], ['tercatat', 'didispokan', 'diteruskan', 'selesai', 'diarsipkan']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Tercatat</h4>
                                        <p>Surat telah diterima dan dicatat ke dalam sistem kearsipan.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['didispokan', 'diteruskan', 'selesai', 'diarsipkan']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Didisposisikan</h4>
                                        <p>Surat sedang ditinjau dan diarahkan oleh pimpinan/kepala dinas.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['diteruskan', 'selesai', 'diarsipkan']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Dalam Proses</h4>
                                        <p>Surat telah diteruskan dan sedang ditindaklanjuti oleh bidang/seksi terkait.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['selesai', 'diarsipkan']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Selesai</h4>
                                        <p>Seluruh proses tindak lanjut surat telah selesai dilaksanakan.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- TIMELINE SURAT KELUAR -->
                                <div class="timeline-item active">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Draft Dibuat</h4>
                                        <p>Konsep berkas surat keluar telah dibuat oleh staf pembuat.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['disetujui', 'diarsipkan']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Pengajuan & Persetujuan</h4>
                                        <p>Surat sedang dalam proses verifikasi dan penandatanganan oleh pimpinan.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= $search_result['status'] === 'diarsipkan' ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Diarsipkan & Dikirim</h4>
                                        <p>Surat telah resmi disetujui, diarsipkan di sistem, dan dikirimkan ke instansi tujuan.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; <?= date('Y') ?> Arsip Digital. Hak Cipta Dilindungi.</p>
    </footer>
</body>
</html>