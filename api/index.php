<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } else {
        try {
            $loginResult = krudApiList(API_TABLE_USERS, [
                ['field' => 'username', 'operator' => '=', 'value' => $username],
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ], ['id' => 'ASC'], 1, 1);
            $user = $loginResult['data'][0] ?? null;

            if (!($loginResult['success'] ?? false)) {
                throw new RuntimeException($loginResult['error'] ?? 'Không thể kết nối CSDL máy chủ.');
            }
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $user['role'];
                $_SESSION['permissions'] = $user['permissions'] ?? '{}';
                
                krudApi('update', API_TABLE_USERS, ['last_login' => date('Y-m-d H:i:s')], $user['id']);
                
                redirect(BASE_URL . '/pages/dashboard.php');
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng!';
            }
        } catch (Exception $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <main class="login-shell">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="login-eyebrow"><i class="fas fa-leaf"></i> CỔNG QUẢN LÝ</div>
                    <div class="logo"><i class="fas fa-recycle"></i></div>
                    <h1><?php echo APP_NAME; ?></h1>
                    <p>Hệ thống quản lý in chứng từ chất thải, được bảo mật và dễ sử dụng cho đội ngũ quản lý.</p>
                    <div class="security-visual" aria-hidden="true">
                        <div class="security-visual__top"><span>TRẠCH CHỨNG BẢO MẬT</span><span class="security-status">Đang hoạt động</span></div>
                        <div class="security-chart"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                    </div>
                </div>
                <div class="login-body">
                    <h2 class="welcome-title">Chào mừng bạn đến với hệ thống</h2>
                    <p class="welcome-copy">Đăng nhập để truy cập dữ liệu và quản lý giấy tờ chất thải.</p>
                    <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo sanitize($error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" name="username" placeholder="Tên đăng nhập" required autofocus autocomplete="username">
                        <label><i class="fas fa-user me-2"></i>Tên đăng nhập</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" name="password" placeholder="Mật khẩu" required autocomplete="current-password">
                        <label><i class="fas fa-lock me-2"></i>Mật khẩu</label>
                        <button type="button" class="password-toggle" aria-label="Hiển thị mật khẩu" aria-pressed="false"><i class="fas fa-eye"></i></button>
                    </div>
                    <button type="submit" class="btn btn-login"><i class="fas fa-sign-in-alt me-2"></i>Đăng nhập</button>
                </form>
                <div class="login-help text-center">
                    <i class="fas fa-circle-info me-1"></i> Chưa có tài khoản? <a href="setup_api.php">Khởi tạo quản trị</a>
                </div>
            </div>
            <div class="login-footer">
                <span><?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></span>
                <span>Hệ thống an ninh quản lý</span>
                <a href="setup_api.php">Cài đặt hệ thống</a>
            </div>
        </div>
    </main>
    <script>
        const passwordInput = document.querySelector('input[name="password"]');
        const passwordToggle = document.querySelector('.password-toggle');
        const loginForm = document.querySelector('form[method="POST"]');
        const loginButton = document.querySelector('.btn-login');
        passwordToggle.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', isPassword ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu');
            passwordToggle.setAttribute('aria-pressed', String(isPassword));
            passwordToggle.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
            passwordInput.focus();
        });
        loginForm.addEventListener('submit', () => {
            loginButton.classList.add('is-loading');
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner me-2"></i>Đang đăng nhập...';
        });
    </script>
</body>
</html>
