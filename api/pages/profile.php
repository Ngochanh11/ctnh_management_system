<?php
require_once '../includes/config.php';
requireLogin();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($newPassword !== $confirmPassword) {
        showAlert('Mật khẩu mới không khớp!', 'error');
    } else {
        try {
            $userResult = krudApiGet(API_TABLE_USERS, $userId);
            $user = ($userResult['success'] ?? false) ? ($userResult['data'] ?? null) : null;
            
            if ($user && password_verify($currentPassword, $user['password'])) {
                $result = krudApi('update', API_TABLE_USERS, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)], $userId);
                if (!($result['success'] ?? false)) {
                    throw new RuntimeException($result['error'] ?? 'Không thể cập nhật mật khẩu.');
                }
                showAlert('Đổi mật khẩu thành công!', 'success');
            } else {
                showAlert('Mật khẩu hiện tại không đúng!', 'error');
            }
        } catch (Exception $e) {
            showAlert('Lỗi: ' . $e->getMessage(), 'error');
        }
    }
}

$userResult = krudApiGet(API_TABLE_USERS, $userId);
$user = ($userResult['success'] ?? false) ? ($userResult['data'] ?? []) : [];

include '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user me-2"></i>Hồ sơ cá nhân</h1>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Thông tin</h5></div>
            <div class="card-body">
                <p><strong>Username:</strong> <?php echo $user['username']; ?></p>
                <p><strong>Họ tên:</strong> <?php echo sanitize($user['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo sanitize($user['email']); ?></p>
                <p><strong>Vai trò:</strong> <?php echo $user['role'] === 'admin' ? 'Admin' : 'User'; ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Đổi mật khẩu</h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
