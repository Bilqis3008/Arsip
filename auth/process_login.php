<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = $_POST['nip'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
        $stmt->execute([$nip]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login Success
            $_SESSION['user_nip'] = $user['nip'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect based on role
            switch ($user['role']) {
                case 'sekretariat':
                    header('Location: ../sekretariat/home.php');
                    break;
                case 'kepala_dinas':
                    header('Location: ../kadin/home.php');
                    break;
                case 'admin_bidang':
                    header('Location: ../admin_perbidang/home.php');
                    break;
                case 'staff':
                    header('Location: ../staff/home.php');
                    break;
                default:
                    header('Location: ../auth/login.php');
                    break;
            }
            exit;
        } else {
            // Login Failed
            echo "<script>
                    alert('NIP atau Password salah.');
                    window.history.back();
                  </script>";
        }
    } catch (\PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>
