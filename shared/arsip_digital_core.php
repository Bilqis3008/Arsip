<?php
// --- SHARED ARSIP DIGITAL CORE ---
// Variables provided by caller: $role, $id_bidang (opt), $id_seksi (opt)

$tab = $_GET['tab'] ?? 'masuk';
$search = $_GET['search'] ?? '';

// --- ARCHIVE QUERY LOGIC ---
if ($tab === 'masuk') {
    $base_query = "SELECT sm.*, sk.status as reply_status 
                   FROM surat_masuk sm 
                   LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
                   WHERE sm.status = 'selesai'";
    $params = [];
    
    if ($role === 'admin_bidang') {
        $base_query .= " AND sm.id_bidang = ?";
        $params[] = $id_bidang;
    } elseif ($role === 'staff') {
        $base_query .= " AND sm.id_seksi = ?";
        $params[] = $id_seksi;
    }
    
    if ($search) {
        $base_query .= " AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $base_query .= " ORDER BY sm.tanggal_terima DESC LIMIT 100";
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
} else {
    $base_query = "SELECT sk.*, u.nama as uploader FROM surat_keluar sk 
                   JOIN users u ON sk.uploaded_by = u.nip
                   WHERE sk.status = 'diarsipkan'";
    $params = [];
    
    if ($role === 'admin_bidang') {
        $base_query .= " AND u.id_bidang = ?";
        $params[] = $id_bidang;
    } elseif ($role === 'staff') {
        $base_query .= " AND u.id_seksi = ?";
        $params[] = $id_seksi;
    }
    
    if ($search) {
        $base_query .= " AND (sk.perihal LIKE ? OR sk.nomor_surat_keluar LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $base_query .= " ORDER BY sk.created_at DESC LIMIT 100";
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
}
$archives = $stmt->fetchAll();
?>

<div class="archive-container">
    <div class="archive-tabs">
        <a href="?tab=masuk" class="tab-item <?= $tab === 'masuk' ? 'active' : '' ?>">Surat Masuk</a>
        <a href="?tab=keluar" class="tab-item <?= $tab === 'keluar' ? 'active' : '' ?>">Surat Keluar</a>
    </div>

    <div class="search-wrapper">
        <form method="GET" class="search-input-group">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" name="search" placeholder="Cari perihal, nomor, atau pengirim..." value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>

    <div class="archive-grid">
        <?php if (empty($archives)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 6rem; background: #fff; border-radius: 2rem; border: 1px solid #e2e8f0;">
                <svg style="width:64px;height:64px;color:#cbd5e1;margin-bottom:1.5rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <p style="font-weight: 800; color: #64748b; font-size: 1.1rem;">Arsip tidak ditemukan.</p>
            </div>
        <?php else: ?>
            <?php foreach ($archives as $doc): ?>
                <div class="archive-card">
                    <span class="doc-type-badge <?= $tab === 'masuk' ? 'badge-incoming' : 'badge-outgoing' ?>">
                        <?= $tab === 'masuk' ? 'Surat Masuk' : 'Surat Keluar' ?>
                    </span>
                    
                    <?php if ($tab === 'masuk' && $doc['perlu_balasan'] == 1): ?>
                        <div style="position: absolute; top: 2rem; right: 2rem;">
                            <?php if ($doc['reply_status'] === 'diarsipkan'): ?>
                                <span style="background: #dcfce7; color: #15803d; font-size: 0.6rem; padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 900;">SUDAH DIBALAS</span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #b91c1c; font-size: 0.6rem; padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 900;">PERLU BALASAN</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <h4><?= htmlspecialchars($doc['perihal']) ?></h4>
                    <div class="doc-meta">
                        <span><b>No:</b> <?= htmlspecialchars($doc[$tab === 'masuk' ? 'nomor_surat' : 'nomor_surat_keluar']) ?></span>
                        <span><b>Tgl:</b> <?= date('d M Y', strtotime($doc[$tab === 'masuk' ? 'tanggal_surat' : 'tanggal_surat'])) ?></span>
                        <?php if ($tab === 'masuk'): ?>
                            <span><b>Asal:</b> <?= htmlspecialchars($doc['pengirim']) ?></span>
                        <?php else: ?>
                            <span><b>Tujuan:</b> <?= htmlspecialchars($doc['tujuan']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-actions">
                        <button class="btn-icon btn-detail" onclick="openDetail(<?= htmlspecialchars(json_encode($doc)) ?>, '<?= $tab ?>')">
                            <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Detail
                        </button>
                        <a href="../<?= htmlspecialchars($doc['file_path'] ?? '') ?>" target="_blank" class="btn-icon btn-file">
                            <svg style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> File
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- UNIVERSAL MODAL -->
<div id="universalModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 id="mPerihal" style="font-size: 1.4rem; font-weight: 900; color: #0f172a;">Perihal Dokumen</h2>
                <p id="mSub" style="font-size: 0.85rem; color: #64748b; margin-top: 0.35rem;">Informasi metadata dan alur surat digital.</p>
            </div>
            <button onclick="closeModal()" style="background:none; border:none; cursor:pointer; color:#64748b;">
                <svg style="width:32px;height:32px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="modal-body">
            <!-- Left: Timeline -->
            <div class="timeline-section">
                <div class="timeline-title">
                    <svg style="width:20px;height:20px;stroke:#2563eb;" viewBox="0 0 24 24" fill="none" stroke-width="3"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    ALUR PERJALANAN SURAT
                </div>
                <div id="timelineContainer">
                    <!-- AJAX Data Here -->
                </div>
            </div>
            
            <!-- Right: Preview -->
            <div class="preview-section">
                <div id="previewContainer">
                    <div class="preview-placeholder">
                        <svg style="width:48px;height:48px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Membangun Pratinjau Dokumen...
                    </div>
                </div>
            </div>

            <!-- Bottom: Full Detail -->
            <div class="info-section">
                <div class="detail-grid">
                    <div class="detail-item"><label>Nomor Surat</label><span id="mNo">-</span></div>
                    <div class="detail-item"><label>Tanggal Surat</label><span id="mTgl">-</span></div>
                    <div class="detail-item"><label id="mLabelAsal">Asal/Tujuan</label><span id="mAsal">-</span></div>
                    <div class="detail-item"><label>Sifat Surat</label><span id="mSifat">-</span></div>
                    <div class="detail-item"><label>Status Arsip</label><span id="mStatus">Selesai / Terverifikasi</span></div>
                    <div class="detail-item" style="grid-column: span 3; margin-top: 1rem;">
                        <label>Ringkasan / Keterangan</label>
                        <p id="mKet" style="font-size: 0.95rem; color: #334155; line-height: 1.6; background: #f8fafc; padding: 1.5rem; border-radius: 1.25rem; border: 1px solid #e2e8f0;"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openDetail(doc, tab) {
        const modal = document.getElementById('universalModal');
        const timeline = document.getElementById('timelineContainer');
        const preview = document.getElementById('previewContainer');
        
        // Fill Metadata
        document.getElementById('mPerihal').innerText = doc.perihal;
        document.getElementById('mNo').innerText = doc[tab === 'masuk' ? 'nomor_surat' : 'nomor_surat_keluar'];
        document.getElementById('mTgl').innerText = doc.tanggal_surat;
        document.getElementById('mAsal').innerText = doc[tab === 'masuk' ? 'pengirim' : 'tujuan'];
        document.getElementById('mLabelAsal').innerText = tab === 'masuk' ? 'PENGIRIM' : 'TUJUAN TERIMA';
        document.getElementById('mSifat').innerText = doc.sifat_surat.toUpperCase();
        document.getElementById('mKet').innerText = doc.keterangan || 'Tidak ada keterangan tambahan.';
        
        // Clear & Load Timeline
        timeline.innerHTML = '<p style="text-align:center; padding:2rem; color:#64748b;">Memuat riwayat...</p>';
        fetch(`../shared/get_alur_surat.php?id=${doc[tab === 'masuk' ? 'id_surat_masuk' : 'id_surat_keluar']}&type=${tab}`)
            .then(r => r.json())
            .then(data => {
                let html = '';
                data.forEach((item, index) => {
                    html += `
                        <div class="history-item ${index === data.length - 1 ? 'active' : ''}">
                            <div class="history-dot"></div>
                            <div class="history-content">
                                <h5>${item.aktor}</h5>
                                <p>${item.activity}</p>
                                <span class="history-time">${item.tanggal}</span>
                            </div>
                        </div>
                    `;
                });
                timeline.innerHTML = html;
            });

        // Set Preview - file_path already contains full relative path e.g. 'uploads/surat_masuk/file.pdf'
        if (doc.file_path) {
            const filePath = `../${doc.file_path}`;
            const isImage = /\.(png|jpg|jpeg|gif|webp)$/i.test(doc.file_path);
            if (isImage) {
                preview.innerHTML = `<img src="${filePath}" style="width:100%;border-radius:1rem;object-fit:contain;max-height:500px;">`;  
            } else {
                preview.innerHTML = `<iframe src="${filePath}" class="doc-preview"></iframe>`;
            }
        } else {
            preview.innerHTML = `<div class="preview-placeholder"><svg style="width:48px;height:48px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg><p>Tidak ada file lampiran.</p></div>`;
        }

        modal.style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('universalModal').style.display = 'none';
        document.getElementById('previewContainer').innerHTML = '';
    }

    window.onclick = e => { if(e.target.classList.contains('modal-overlay')) closeModal(); }
</script>
