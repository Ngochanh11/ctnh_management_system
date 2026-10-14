<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('wastes', 'view') or die('Không có quyền');

$action = $_GET['action'] ?? 'list';
$openWasteTypeModal = $action === 'create';
if ($openWasteTypeModal) {
    $action = 'list';
}
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if ($id && !checkPermission('wastes', 'edit')) {
        die('Không có quyền sửa danh mục chất thải.');
    }
    if (!$id && !checkPermission('wastes', 'create')) {
        die('Không có quyền thêm chất thải.');
    }
    try {
        $data = [
            'waste_code' => trim($_POST['waste_code']),
            'waste_name' => trim($_POST['waste_name']),
            'category' => $_POST['category'],
            'unit' => trim($_POST['unit']),
            'price_per_unit' => $_POST['price_per_unit'] ?? 0,
            'description' => trim($_POST['description'] ?? '')
        ];
        
        if ($id) {
            $result = krudApi('update', API_TABLE_WASTE_TYPES, $data, $id);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể cập nhật danh mục chất thải.');
            }
            showAlert('Cập nhật thành công!', 'success');
        } else {
            $result = krudApi('insert', API_TABLE_WASTE_TYPES, $data);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể thêm chất thải.');
            }
            showAlert('Thêm mới thành công!', 'success');
        }
        redirect(BASE_URL . '/pages/waste-types.php');
    } catch (Exception $e) {
        showAlert('Lỗi: ' . $e->getMessage(), 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_waste_type'])) {
    checkPermission('wastes', 'delete') or die('Không có quyền xóa chất thải.');
    $deleteId = (int) $_POST['delete_waste_type'];
    try {
        $result = krudApi('delete', API_TABLE_WASTE_TYPES, [], $deleteId);
        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Không thể xóa chất thải.');
        }
        showAlert('Xóa thành công!', 'success');
        redirect(BASE_URL . '/pages/waste-types.php');
    } catch (Exception $e) {
        showAlert('Không thể xóa!', 'error');
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-trash-alt me-2"></i>Danh mục Chất thải</h1>
        </div>
        <?php if (checkPermission('wastes', 'create')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wasteTypeModal">
            <i class="fas fa-plus me-2"></i>Thêm mới
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'):
    $result = krudApiList(API_TABLE_WASTE_TYPES, [], ['waste_code' => 'ASC'], 1, 1000);
    $wastes = ($result['success'] ?? false) ? ($result['data'] ?? []) : [];
?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã CT</th>
                        <th>Tên chất thải</th>
                        <th>Loại</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wastes as $w): ?>
                    <tr>
                        <td><strong><?php echo $w['waste_code']; ?></strong></td>
                        <td><?php echo sanitize($w['waste_name']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $w['category'] === 'hazardous' ? 'danger' : 'info'; ?>">
                                <?php echo $w['category'] === 'hazardous' ? 'Nguy hại' : 'Công nghiệp'; ?>
                            </span>
                        </td>
                        <td><?php echo $w['unit']; ?></td>
                        <td><?php echo formatNumber($w['price_per_unit']); ?> đ</td>
                        <td class="action-buttons">
                            <?php if (checkPermission('wastes', 'edit')): ?>
                            <a href="?action=edit&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-info" aria-label="Sửa loại chất thải"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if (checkPermission('wastes', 'delete')): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa loại chất thải này? Hành động không thể hoàn tác.');">
                                <button type="submit" name="delete_waste_type" value="<?php echo (int) $w['id']; ?>" class="btn btn-sm btn-danger" aria-label="Xóa loại chất thải"><i class="fas fa-trash"></i></button>
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

<?php if (checkPermission('wastes', 'create')): ?>
<div class="modal fade" id="wasteTypeModal" tabindex="-1" aria-labelledby="wasteTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wasteTypeModalLabel">Thêm loại chất thải</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mã chất thải <span class="text-danger">*</span></label>
                            <input type="text" name="waste_code" class="form-control" required placeholder="08 02 04">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Tên chất thải <span class="text-danger">*</span></label>
                            <input type="text" name="waste_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loại</label>
                            <select name="category" class="form-select">
                                <option value="hazardous">Nguy hại</option>
                                <option value="industrial">Công nghiệp</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Đơn vị</label>
                            <input type="text" name="unit" class="form-control" value="kg">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Đơn giá (đ)</label>
                            <input type="number" name="price_per_unit" class="form-control" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($openWasteTypeModal && checkPermission('wastes', 'create')): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('wasteTypeModal')).show();
});
</script>
<?php endif; ?>

<?php elseif ($action === 'edit'):
    checkPermission('wastes', 'edit') or die('Không có quyền sửa danh mục chất thải.');
    $waste = null;
    if ($id) {
        $result = krudApiGet(API_TABLE_WASTE_TYPES, $id);
        $waste = ($result['success'] ?? false) ? ($result['data'] ?? null) : null;
    }
?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Mã chất thải <span class="text-danger">*</span></label>
                    <input type="text" name="waste_code" class="form-control" required value="<?php echo $waste['waste_code'] ?? ''; ?>" placeholder="08 02 04">
                </div>
                <div class="col-md-9 mb-3">
                    <label class="form-label">Tên chất thải <span class="text-danger">*</span></label>
                    <input type="text" name="waste_name" class="form-control" required value="<?php echo $waste['waste_name'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Loại</label>
                    <select name="category" class="form-select">
                        <option value="hazardous" <?php echo ($waste['category'] ?? 'hazardous') === 'hazardous' ? 'selected' : ''; ?>>Nguy hại</option>
                        <option value="industrial" <?php echo ($waste['category'] ?? '') === 'industrial' ? 'selected' : ''; ?>>Công nghiệp</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Đơn vị</label>
                    <input type="text" name="unit" class="form-control" value="<?php echo $waste['unit'] ?? 'kg'; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Đơn giá (đ)</label>
                    <input type="number" name="price_per_unit" class="form-control" value="<?php echo $waste['price_per_unit'] ?? 0; ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Mô tả</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo $waste['description'] ?? ''; ?></textarea>
                </div>
            </div>
            <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu</button>
            <a href="?" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Hủy</a>
        </form>
    </div>
</div>

<?php endif; include '../includes/footer.php'; ?>
