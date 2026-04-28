<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a 'user'
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'user') {
    header('Location: ../auth/login.php');
    exit;
}

$user_nip = $_SESSION['user_nip'];
$user_nama = $_SESSION['user_nama'];

// Search Logic
$search_result = null;
$error_message = '';

if (isset($_GET['nomor_surat']) && !empty($_GET['nomor_surat'])) {
    $nomor_surat = $_GET['nomor_surat'];
    
    // Search in surat_masuk
    $stmt = $pdo->prepare("SELECT * FROM surat_masuk WHERE nomor_surat = ? OR nomor_agenda = ?");
    $stmt->execute([$nomor_surat, $nomor_surat]);
    $search_result = $stmt->fetch();
    
    if (!$search_result) {
        $error_message = "Surat dengan nomor tersebut tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Surat - Arsip Kemendikbud</title>
    <link rel="stylesheet" href="../css/user/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar/Navbar -->
        <nav class="top-nav">
            <div class="logo">
                <i class="fas fa-archive"></i>
                <span>Arsip Digital</span>
            </div>
            <div class="user-info">
                <span>Selamat Datang, <strong><?= htmlspecialchars($user_nama) ?></strong></span>
                <a href="../auth/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </nav>

        <main class="content">
            <div class="hero-section">
                <h1>Lacak Status Surat Anda</h1>
                <p>Masukkan Nomor Surat atau Nomor Agenda untuk melihat proses terkini.</p>
                
                <form action="" method="GET" class="search-form">
                    <div class="search-input-group">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="nomor_surat" placeholder="Contoh: 123/B1/2024" value="<?= isset($_GET['nomor_surat']) ? htmlspecialchars($_GET['nomor_surat']) : '' ?>" required>
                        <button type="submit">Cari Surat</button>
                    </div>
                </form>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <?php if ($search_result): ?>
                <div class="result-card">
                    <div class="card-header">
                        <h2>Informasi Detail Surat</h2>
                        <span class="status-badge status-<?= strtolower($search_result['status']) ?>">
                            <?= ucfirst($search_result['status']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Nomor Agenda</label>
                                <span><?= htmlspecialchars($search_result['nomor_agenda']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Nomor Surat</label>
                                <span><?= htmlspecialchars($search_result['nomor_surat']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Tanggal Surat</label>
                                <span><?= date('d M Y', strtotime($search_result['tanggal_surat'])) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Tanggal Terima</label>
                                <span><?= date('d M Y', strtotime($search_result['tanggal_terima'])) ?></span>
                            </div>
                            <div class="info-item full-width">
                                <label>Pengirim</label>
                                <span><?= htmlspecialchars($search_result['pengirim']) ?></span>
                            </div>
                            <div class="info-item full-width">
                                <label>Perihal</label>
                                <span><?= htmlspecialchars($search_result['perihal']) ?></span>
                            </div>
                        </div>

                        <div class="status-timeline">
                            <h3>Progress Surat</h3>
                            <div class="timeline">
                                <div class="timeline-item <?= in_array($search_result['status'], ['tercatat', 'didispokan', 'diteruskan', 'selesai']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Tercatat</h4>
                                        <p>Surat telah diterima dan dicatat di sistem.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['didispokan', 'diteruskan', 'selesai']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Didisposisikan</h4>
                                        <p>Surat sedang ditinjau oleh pimpinan.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= in_array($search_result['status'], ['diteruskan', 'selesai']) ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Dalam Proses</h4>
                                        <p>Surat sedang ditindaklanjuti oleh bidang terkait.</p>
                                    </div>
                                </div>
                                <div class="timeline-item <?= $search_result['status'] === 'selesai' ? 'active' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <h4>Selesai</h4>
                                        <p>Proses surat telah selesai.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
