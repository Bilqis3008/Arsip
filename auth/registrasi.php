<?php
require_once '../config/db.php';

// Fetch Bidang
$stmt = $pdo->query("SELECT id_bidang, nama_bidang FROM bidang");
$bidang_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Staf - Arsip Kemendikbud</title>
    <link rel="stylesheet" href="../css/auth/registrasi.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Registrasi Staf</h1>
            <p>Silakan lengkapi data diri Anda untuk mendaftar</p>
        </div>
        <form action="process_registrasi.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" placeholder="Masukkan Nama Lengkap" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <div class="form-group">
                <label for="no_hp">No. HP</label>
                <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="Masukkan No. HP" required>
            </div>
            <div class="form-group">
                <label for="role">Daftar Sebagai</label>
                <select id="role" name="role" class="form-control" required onchange="handleRoleChange(this.value)">
                    <option value="staff">Staf Internal</option>
                    <option value="user">User Umum (Eksternal)</option>
                </select>
            </div>
            <div id="instansi-container" class="form-group hidden">
                <label for="asal_instansi">Asal Instansi</label>
                <input type="text" id="asal_instansi" name="asal_instansi" class="form-control" placeholder="Masukkan Asal Instansi">
            </div>
            <div id="nip-container" class="form-group">
                <label for="nip_input">NIP</label>
                <input type="text" id="nip_input" name="nip" class="form-control" placeholder="Masukkan NIP" required>
            </div>
            <div id="nametag-container" class="form-group">
                <label for="name_tag">Upload Name Tag (Wajib untuk Staf)</label>
                <input type="file" id="name_tag" name="name_tag" class="form-control" accept="image/*" required>
            </div>
            <div id="jabatan-container" class="form-group">
                <label for="jabatan">Jabatan</label>
                <input type="text" id="jabatan" name="jabatan" class="form-control" placeholder="Masukkan Jabatan" required>
            </div>
            <div id="bidang-container" class="form-group">
                <label for="id_bidang">Bidang</label>
                <select id="id_bidang" name="id_bidang" class="form-control" required onchange="fetchSeksi(this.value)">
                    <option value="">Pilih Bidang</option>
                    <?php foreach ($bidang_list as $bidang): ?>
                        <option value="<?= $bidang['id_bidang'] ?>"><?= $bidang['nama_bidang'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="seksi-container" class="form-group hidden">
                <label for="id_seksi">Seksi</label>
                <select id="id_seksi" name="id_seksi" class="form-control">
                    <option value="">Pilih Seksi</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="********" required>
            </div>
            <button type="submit" class="btn-primary">Daftar Sekarang</button>
        </form>
        <div class="auth-footer">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
    </div>

    <script>
        function handleRoleChange(role) {
            const nipContainer = document.getElementById('nip-container');
            const jabatanContainer = document.getElementById('jabatan-container');
            const bidangContainer = document.getElementById('bidang-container');
            const seksiContainer = document.getElementById('seksi-container');
            const instansiContainer = document.getElementById('instansi-container');
            const nametagContainer = document.getElementById('nametag-container');
            
            const nipInput = document.getElementById('nip_input');
            const jabatanInput = document.getElementById('jabatan');
            const bidangSelect = document.getElementById('id_bidang');
            const instansiInput = document.getElementById('asal_instansi');
            const nametagInput = document.getElementById('name_tag');

            if (role === 'user') {
                nipContainer.classList.add('hidden');
                jabatanContainer.classList.add('hidden');
                bidangContainer.classList.add('hidden');
                seksiContainer.classList.add('hidden');
                instansiContainer.classList.remove('hidden');
                nametagContainer.classList.add('hidden');
                
                nipInput.required = false;
                jabatanInput.required = false;
                bidangSelect.required = false;
                instansiInput.required = true;
                nametagInput.required = false;
            } else {
                nipContainer.classList.remove('hidden');
                jabatanContainer.classList.remove('hidden');
                bidangContainer.classList.remove('hidden');
                instansiContainer.classList.add('hidden');
                nametagContainer.classList.remove('hidden');
                
                nipInput.required = true;
                jabatanInput.required = true;
                bidangSelect.required = true;
                instansiInput.required = false;
                nametagInput.required = true;
                
                // Re-check seksi if bidang is selected
                if (bidangSelect.value) {
                    fetchSeksi(bidangSelect.value);
                }
            }
        }

        function fetchSeksi(idBidang) {
            const seksiContainer = document.getElementById('seksi-container');
            const seksiSelect = document.getElementById('id_seksi');
            const role = document.getElementById('role').value;
            
            if (!idBidang || role === 'user') {
                seksiContainer.classList.add('hidden');
                seksiSelect.innerHTML = '<option value="">Pilih Seksi</option>';
                seksiSelect.required = false;
                return;
            }

            fetch(`../api/get_seksi.php?id_bidang=${idBidang}`)
                .then(response => response.json())
                .then(data => {
                    seksiSelect.innerHTML = '<option value="">Pilih Seksi</option>';
                    if (data.length > 0) {
                        seksiContainer.classList.remove('hidden');
                        seksiSelect.required = true;
                        data.forEach(seksi => {
                            const option = document.createElement('option');
                            option.value = seksi.id_seksi;
                            option.textContent = seksi.nama_seksi;
                            seksiSelect.appendChild(option);
                        });
                    } else {
                        seksiContainer.classList.add('hidden');
                        seksiSelect.required = false;
                    }
                })
                .catch(error => {
                    console.error('Error fetching seksi:', error);
                    seksiContainer.classList.add('hidden');
                });
        }
    </script>
</body>
</html>
