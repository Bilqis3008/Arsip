<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arsip Kemendikbud</title>
    <link rel="stylesheet" href="../css/auth/login.css">
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
        <div class="auth-footer">
            Belum punya akun? <a href="registrasi.php">Daftar di sini</a>
        </div>
    </div>
</body>
</html>
