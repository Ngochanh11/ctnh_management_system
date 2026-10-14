<?php
require_once '../includes/config.php';
requireLogin();
requireAdmin();

$action = $_GET['action'] ?? 'list';
$openUserModal = $action === 'create';
if ($openUserModal) {
    $action = 'list';
}
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $deleteId = (int) ($_POST['delete_user'] ?? 0);
    if ($deleteId <= 0 || $deleteId === (int) $_SESSION['user_id']) {
        showAlert('Không thể xóa tài khoản đang đăng nhập.', 'error');
        redirect(BASE_URL . '/pages/users.php');
    }
    $result = krudApi('delete', API_TABLE_USERS, [], $deleteId);
    if (!($result['success'] ?? false)) {
        showAlert($result['error'] ?? 'Không thể xóa người dùng.', 'error');
    } else {
        showAlert('Đã xóa người dùng.', 'success');
    }
    redirect(BASE_URL . '/pages/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    try {
        $permissions = [
            'customers' => [],
            'wastes' => [],
            'documents' => [],
            'reports' => [],
        ];
        foreach ([
            'customers' => ['view', 'create', 'edit', 'delete'],
            'wastes' => ['view', 'create', 'edit', 'delete'],
            'documents' => ['view', 'create', 'edit', 'delete', 'print'],
            'reports' => ['view', 'export'],
        ] as $module => $actions) {
            foreach ($actions as $permissionAction) {
                if (isset($_POST['perm_' . $module . '_' . $permissionAction])) {
                    $permissions[$module][] = $permissionAction;
                }
            }
        }

        $data = [
            'username' => trim($_POST['username']),
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'role' => $_POST['role'],
            'status' => $_POST['status'],
            'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE)
        ];
        
        if ($id) {
            if (!empty($_POST['password'])) {
                $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            } else {
                unset($data['password']);
            }
            $result = krudApi('update', API_TABLE_USERS, $data, $id);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể cập nhật người dùng.');
            }
            showAlert('Cập nhật thành công!', 'success');
        } else {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $result = krudApi('insert', API_TABLE_USERS, $data);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể thêm người dùng.');
            }
            showAlert('Thêm người dùng thành công!', 'success');
        }
        redirect(BASE_URL . '/pages/users.php');
    } catch (Exception $e) {
        showAlert('Lỗi: ' . $e->getMessage(), 'error');
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div><h1><i class="fas fa-users me-2"></i>Quản lý Người dùng</h1></div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userCreateModal">
            <i class="fas fa-plus me-2"></i>Thêm người dùng
        </button>
    </div>
</div>

<?php if ($action === 'list'):
    $usersPage = max(1, (int) ($_GET['page'] ?? 1));
    $usersPerPage = 100;
    $result = krudApiList(API_TABLE_USERS, [], ['created_at' => 'DESC'], $usersPage, $usersPerPage);
    $users = ($result['success'] ?? false) ? ($result['data'] ?? []) : [];
    $usersTotalPages = max(1, (int) ceil((int) ($result['total'] ?? 0) / $usersPerPage));
?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Username</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập cuối</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?php echo $u['username']; ?></strong></td>
                        <td><?php echo sanitize($u['full_name']); ?></td>
                        <td><?php echo sanitize($u['email']); ?></td>
                        <td><span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'primary'; ?>"><?php echo $u['role'] === 'admin' ? 'Admin' : 'User'; ?></span></td>
                        <td><span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $u['status'] === 'active' ? 'Hoạt động' : 'Khóa'; ?></span></td>
                        <td><?php echo $u['last_login'] ? formatDate($u['last_login'], 'd/m/Y H:i') : '-'; ?></td>
                        <td class="action-buttons">
                            <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                            <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa tài khoản này? Hành động không thể hoàn tác.');">
                                <button type="submit" name="delete_user" value="<?php echo (int) $u['id']; ?>" class="btn btn-sm btn-danger" aria-label="Xóa người dùng">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($usersTotalPages > 1): ?>
<nav class="mt-3" aria-label="Phân trang người dùng">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $usersPage <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo max(1, $usersPage - 1); ?>" aria-label="Trang trước">Trước</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Trang <?php echo $usersPage; ?> / <?php echo $usersTotalPages; ?></span></li>
        <li class="page-item <?php echo $usersPage >= $usersTotalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo min($usersTotalPages, $usersPage + 1); ?>" aria-label="Trang tiếp theo">Sau</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="userCreateModal" tabindex="-1" aria-labelledby="userCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userCreateModalLabel">Thêm người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Vai trò</label>
                            <select name="role" class="form-select" onchange="togglePermissions(this)">
                                <option value="user" selected>User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Hoạt động</option>
                                <option value="inactive">Khóa</option>
                            </select>
                        </div>
                    </div>

                    <div class="permissions-section">
                        <h6 class="permissions-heading">Phân quyền chi tiết</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <fieldset class="permission-group">
                                    <legend>Khách hàng</legend>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_view" checked><label class="form-check-label">Xem</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_create"><label class="form-check-label">Thêm mới</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_edit"><label class="form-check-label">Sửa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_delete"><label class="form-check-label">Xóa</label></div>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <fieldset class="permission-group">
                                    <legend>Chất thải</legend>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_view" checked><label class="form-check-label">Xem</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_create"><label class="form-check-label">Thêm mới</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_edit"><label class="form-check-label">Sửa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_delete"><label class="form-check-label">Xóa</label></div>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <fieldset class="permission-group">
                                    <legend>Chứng từ</legend>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_view" checked><label class="form-check-label">Xem</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_create"><label class="form-check-label">Tạo mới</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_edit"><label class="form-check-label">Sửa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_delete"><label class="form-check-label">Xóa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_print"><label class="form-check-label">In ấn</label></div>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <fieldset class="permission-group">
                                    <legend>Báo cáo</legend>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_reports_view" checked><label class="form-check-label">Xem</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_reports_export"><label class="form-check-label">Xuất báo cáo</label></div>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu người dùng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($openUserModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('userCreateModal')).show();
});
</script>
<?php endif; ?>

<?php elseif ($action === 'edit'):
    $user = null;
    $permissions = ['customers' => [], 'wastes' => [], 'documents' => [], 'reports' => []];
    if ($id) {
        $result = krudApiGet(API_TABLE_USERS, $id);
        $user = ($result['success'] ?? false) ? ($result['data'] ?? null) : null;
        if (!empty($user['permissions'])) {
            $permissions = is_string($user['permissions']) ? json_decode($user['permissions'], true) : $user['permissions'];
        }
    }
?>

<div class="card">
    <div class="card-header"><h5 class="mb-0"><?php echo $id ? 'Sửa' : 'Thêm'; ?> người dùng</h5></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?php echo $user['username'] ?? ''; ?>" <?php echo $id ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mật khẩu <?php echo $id ? '' : '<span class="text-danger">*</span>'; ?></label>
                    <input type="password" name="password" class="form-control" <?php echo $id ? '' : 'required'; ?> placeholder="<?php echo $id ? 'Để trống nếu không đổi' : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required value="<?php echo $user['full_name'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo $user['email'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $user['phone'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Vai trò</label>
                    <select name="role" class="form-select" onchange="togglePermissions(this)">
                        <option value="user" <?php echo ($user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="inactive" <?php echo ($user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Khóa</option>
                    </select>
                </div>
            </div>
            
            <div class="permissions-section" style="<?php echo ($user['role'] ?? 'user') === 'admin' ? 'display:none' : ''; ?>">
                <hr><h6 class="mb-3">Phân quyền chi tiết</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3"><div class="card-body">
                            <h6 class="card-title">Chất thải</h6>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_view" <?php echo permissionValueEnabled($permissions, 'wastes', 'view') ? 'checked' : ''; ?>><label class="form-check-label">Xem</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_create" <?php echo permissionValueEnabled($permissions, 'wastes', 'create') ? 'checked' : ''; ?>><label class="form-check-label">Thêm mới</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_edit" <?php echo permissionValueEnabled($permissions, 'wastes', 'edit') ? 'checked' : ''; ?>><label class="form-check-label">Sửa</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_wastes_delete" <?php echo permissionValueEnabled($permissions, 'wastes', 'delete') ? 'checked' : ''; ?>><label class="form-check-label">Xóa</label></div>
                        </div></div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Khách hàng</h6>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_view" <?php echo permissionValueEnabled($permissions, 'customers', 'view') ? 'checked' : ''; ?>><label class="form-check-label">Xem</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_create" <?php echo permissionValueEnabled($permissions, 'customers', 'create') ? 'checked' : ''; ?>><label class="form-check-label">Thêm mới</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_edit" <?php echo permissionValueEnabled($permissions, 'customers', 'edit') ? 'checked' : ''; ?>><label class="form-check-label">Sửa</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_customers_delete" <?php echo permissionValueEnabled($permissions, 'customers', 'delete') ? 'checked' : ''; ?>><label class="form-check-label">Xóa</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Chứng từ</h6>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_view" <?php echo permissionValueEnabled($permissions, 'documents', 'view') ? 'checked' : ''; ?>><label class="form-check-label">Xem</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_create" <?php echo permissionValueEnabled($permissions, 'documents', 'create') ? 'checked' : ''; ?>><label class="form-check-label">Tạo mới</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_edit" <?php echo permissionValueEnabled($permissions, 'documents', 'edit') ? 'checked' : ''; ?>><label class="form-check-label">Sửa</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_delete" <?php echo permissionValueEnabled($permissions, 'documents', 'delete') ? 'checked' : ''; ?>><label class="form-check-label">Xóa</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_documents_print" <?php echo permissionValueEnabled($permissions, 'documents', 'print') ? 'checked' : ''; ?>><label class="form-check-label">In ấn</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3"><div class="card-body">
                            <h6 class="card-title">Báo cáo</h6>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_reports_view" <?php echo permissionValueEnabled($permissions, 'reports', 'view') ? 'checked' : ''; ?>><label class="form-check-label">Xem</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_reports_export" <?php echo permissionValueEnabled($permissions, 'reports', 'export') ? 'checked' : ''; ?>><label class="form-check-label">Xuất báo cáo</label></div>
                        </div></div>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu</button>
            <a href="?" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Hủy</a>
        </form>
    </div>
</div>

<script>
function togglePermissions(select) {
    const section = select.closest('form').querySelector('.permissions-section');
    if (section) {
        section.style.display = select.value === 'admin' ? 'none' : 'block';
    }
}
</script>

<?php endif; include '../includes/footer.php'; ?>
