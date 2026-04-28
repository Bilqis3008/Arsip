<?php
$file = 'c:/laragon/www/Arsip/sekretariat/surat_masuk.php';
$content = file_get_contents($file);

// 1. Update fetch logic
$marker1 = '$all_mails = $stmt->fetchAll();';
$fetch_new = <<<'EOD'
$all_mails = $stmt->fetchAll();

$mails = [];
$riwayat_mails = [];
foreach ($all_mails as $m) {
    if ($m['status'] === 'selesai') {
        $riwayat_mails[] = $m;
    } else {
        $mails[] = $m;
    }
}

// --- FETCH SURAT TUGAS (DISPOSISI KADIN TO SEKRETARIAT UMUM) ---
$id_bidang_sekretariat = 8;
$query_tugas = "SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.tanggal_disposisi as tgl_dispo_kadin, d.id_disposisi
              FROM surat_masuk sm 
              JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk
              WHERE d.id_bidang = ? 
              AND d.nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
              AND sm.status IN ('didispokan', 'diteruskan')
              AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)
              ORDER BY sm.created_at DESC";
$stmt_tugas = $pdo->prepare($query_tugas);
$stmt_tugas->execute([$id_bidang_sekretariat, "%$search%", "%$search%", "%$search%"]);
$tugas_mails = $stmt_tugas->fetchAll();

// --- HANDLE ACTION: ARSIP TUGAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_tugas') {
    $id_target = $_POST['id_surat'];
    try {
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai', id_bidang = ? WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang_sekretariat, $id_target]);
        $_SESSION['success_msg'] = "Surat berhasil diarsipkan ke Sekretariat Umum.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal mengarsipkan: " . $e->getMessage();
    }
    header("Location: surat_masuk.php");
    exit;
}
EOD;

// We need to replace the entire block from $mails = []; to the end of loop if we want to be clean, 
// but let's just replace from $all_mails = $stmt->fetchAll(); and we will have some duplicate code we need to remove or replace.

// Let's try replacing from $mails = []; with regex to handle whitespace
$content = preg_replace('/\$mails = \[\];\s+\$riwayat_mails = \[\];\s+foreach \(\$all_mails as \$m\) \{.*?\}\s+/s', $fetch_new, $content);

// 2. Update Tabs
$tabs_old = '<button class="tab-btn" onclick="switchTab(\'riwayat\')">';
$tabs_new = <<<'EOD'
<button class="tab-btn" onclick="switchTab('tugas')">
                    <svg class="icon" style="margin-right: 0.5rem;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg> Surat Tugas
                </button>
                <button class="tab-btn" onclick="switchTab('riwayat')">
EOD;

$content = str_replace($tabs_old, $tabs_new, $content);

// 3. Update Sections
$section_old = '            <!-- Section: Riwayat -->';
$section_new = <<<'EOD'
            <!-- Section: Surat Tugas -->
            <section id="section-tugas" class="module-section">
                <div class="card">
                    <div class="card-header" style="margin-bottom: 1.5rem;">
                        <h2>Agenda Surat Tugas (Sekretariat Umum)</h2>
                        <p>Mengelola surat disposisi dari pimpinan yang ditujukan untuk Sekretariat Umum.</p>
                    </div>

                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 150px;">Tgl Terima</th>
                                    <th>Identitas & Instruksi Pimpinan</th>
                                    <th>Pengirim</th>
                                    <th style="width: 150px;">Status</th>
                                    <th style="width: 150px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tugas_mails)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                                            Tidak ada agenda surat tugas untuk ditampilkan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tugas_mails as $m): ?>
                                        <tr>
                                            <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'])) ?></b></td>
                                            <td>
                                                <div style="font-weight: 700; color: var(--navy);"><?= htmlspecialchars($m['perihal']) ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">No: <?= htmlspecialchars($m['nomor_surat']) ?></div>
                                                <?php if ($m['instruksi_kadin']): ?>
                                                    <div style="background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border-left: 3px solid var(--primary); font-size: 0.8rem; font-style: italic; color: #475569;">
                                                        "<?= htmlspecialchars($m['instruksi_kadin']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($m['pengirim']) ?></td>
                                            <td>
                                                <span class="badge-status status-<?= $m['status'] ?>">
                                                    <?= ucfirst($m['status'] === 'didispokan' ? 'Perlu Tindakan' : $m['status']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                                    <?php if ($m['file_path']): ?>
                                                        <a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Preview Dokumen">
                                                            <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                        </a>
                                                    <?php endif; ?>

                                                    <a href="disposisi_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="action-btn btn-edit" title="Teruskan ke Staf" style="background: #e0e7ff; color: #4338ca; border-color: #c7d2fe;">
                                                        <svg class="icon" style="width: 16px; height: 16px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                                    </a>
                                                    
                                                    <button onclick="openArchiveTugasModal(<?= $m['id_surat_masuk'] ?>, '<?= htmlspecialchars(addslashes($m['perihal'])) ?>')" class="action-btn" style="background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; cursor: pointer;" title="Arsip Langsung">
                                                        <svg class="icon" style="width: 16px; height: 16px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: Riwayat -->
EOD;

$content = str_replace($section_old, $section_new, $content);

// 4. Update JS and Modals
$js_old = '    <script>
        const allMailData = <?= json_encode($mails) ?>;';
$js_new = <<<'EOD'
    <!-- ====== MODAL: ARSIP TUGAS ====== -->
    <div id="archiveTugasModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: #fff; width: 100%; max-width: 400px; border-radius: 1.5rem; padding: 2rem; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <button onclick="closeArchiveTugasModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">✕</button>
            <div style="width: 60px; height: 60px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <svg viewBox="0 0 24 24" style="width:32px; height:32px; fill:none; stroke:currentColor; stroke-width:2.5;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h3 style="margin-bottom: 0.5rem; color: #0f172a; font-size: 1.25rem; text-align: center;">Arsipkan Surat Tugas</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; text-align: center;">Apakah Anda yakin ingin mengarsipkan surat <strong id="modal-tugas-perihal" style="color: #0f172a;"></strong> ini?</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="archive_tugas">
                <input type="hidden" name="id_surat" id="modal-tugas-id">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <button type="button" onclick="closeArchiveTugasModal()" style="padding: 0.85rem; border-radius: 0.75rem; font-weight: 700; cursor: pointer; border: none; background: #f1f5f9; color: #64748b;">Batal</button>
                    <button type="submit" style="padding: 0.85rem; border-radius: 0.75rem; font-weight: 700; cursor: pointer; border: none; background: #10b981; color: white;">Ya, Arsipkan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const allMailData = <?= json_encode($mails) ?>;

        function openArchiveTugasModal(id, perihal) {
            document.getElementById('modal-tugas-id').value = id;
            document.getElementById('modal-tugas-perihal').textContent = '"' + perihal + '"';
            document.getElementById('archiveTugasModal').style.display = 'flex';
        }
        function closeArchiveTugasModal() {
            document.getElementById('archiveTugasModal').style.display = 'none';
        }
EOD;

$content = str_replace($js_old, $js_new, $content);

file_put_contents($file, $content);
echo "Successfully updated surat_masuk.php with regex";
?>
