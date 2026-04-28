<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING) ?: 'staff';
    $nip = filter_input(INPUT_POST, 'nip', FILTER_SANITIZE_STRING);
    $nama = filter_input(INPUT_POST, 'nama', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $no_hp = filter_input(INPUT_POST, 'no_hp', FILTER_SANITIZE_STRING);
    $asal_instansi = filter_input(INPUT_POST, 'asal_instansi', FILTER_SANITIZE_STRING);
    $jabatan = filter_input(INPUT_POST, 'jabatan', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $id_bidang = filter_input(INPUT_POST, 'id_bidang', FILTER_VALIDATE_INT);
    $id_seksi = filter_input(INPUT_POST, 'id_seksi', FILTER_VALIDATE_INT);

    $status = 'aktif';
    $name_tag_path = null;

    // Handle Staff specific logic
    if ($role === 'staff') {
        $status = 'pending';
        
        // Handle Name Tag Upload
        if (isset($_FILES['name_tag']) && $_FILES['name_tag']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/nametags/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['name_tag']['name'], PATHINFO_EXTENSION);
            $file_name = 'NT_' . $nip . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['name_tag']['tmp_name'], $target_file)) {
                $name_tag_path = $file_name;
            }
        }
    }

    // If role is user, generate a unique ID as NIP since they don't have one
    if ($role === 'user') {
        $nip = 'USR-' . time() . rand(10, 99);
        $jabatan = 'User Eksternal';
        $id_bidang = null;
        $id_seksi = null;
    }

    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, no_hp, asal_instansi, jabatan, password, id_bidang, id_seksi, role, status, name_tag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nip, 
            $nama, 
            $email, 
            $no_hp, 
            $asal_instansi,
            $jabatan, 
            $hashed_password, 
            $id_bidang, 
            $id_seksi ?: null, 
            $role,
            $status,
            $name_tag_path
        ]);

        if ($role === 'staff') {
            echo "<script>
                    alert('Registrasi Berhasil! Akun Anda sedang dalam proses verifikasi oleh Admin.');
                    window.location.href = 'login.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Registrasi Berhasil! Silakan login.');
                    window.location.href = 'login.php';
                  </script>";
        }
        exit;
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            // Error Duplicate NIP/Email
            echo "<script>
                    alert('NIP atau Email sudah terdaftar.');
                    window.history.back();
                  </script>";
        } else {
            die("Error: " . $e->getMessage());
        }
    }
}
?>
