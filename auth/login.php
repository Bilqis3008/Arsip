<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arsip Kemendikbud</title>
    <link rel="stylesheet" href="../css/auth/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Login</h1>
            <p>Silakan masuk ke akun Anda</p>
        </div>
        <form action="process_login.php" method="POST">
            <div class="form-group">
                <label for="identifier">NIP / Email</label>
                <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Masukkan NIP atau Email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="********" required>
            </div>
            <button type="submit" class="btn-primary">Masuk</button>
        </form>
        <div class="auth-info-note" style="margin-top: 1.5rem; padding: 1rem; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; font-size: 0.85rem; color: #1e3a8a; text-align: left; line-height: 1.5; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
            <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                <i class="fas fa-info-circle" style="color: #2563eb; margin-top: 0.15rem; font-size: 1rem;"></i>
                <div>
                    <strong style="color: #1e40af;">Aktor Instansi Luar?</strong><br>
                    Anda tidak perlu login atau registrasi. Silakan gunakan fitur melacak surat langsung melalui <a href="../index.php" style="color: #2563eb; font-weight: 700; text-decoration: none;">Halaman Depan</a>.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
