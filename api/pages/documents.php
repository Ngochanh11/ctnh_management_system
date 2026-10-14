<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('documents', 'view') or die('Không có quyền');

$action = $_GET['action'] ?? 'list';
$openDocumentModal = $action === 'create';
if ($openDocumentModal) {
    $action = 'list';
}
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if ($id && !checkPermission('documents', 'edit')) {
        die('Không có quyền sửa chứng từ.');
    }
    if (!$id && !checkPermission('documents', 'create')) {
        die('Không có quyền tạo chứng từ.');
    }
    try {
        $docData = [
            'customer_id' => $_POST['customer_id'],
            'collection_date' => $_POST['collection_date'],
            'document_type' => $_POST['document_type'] ?? 'receipt',
            'driver_name' => trim($_POST['driver_name'] ?? ''),
            'vehicle_number' => trim($_POST['vehicle_number'] ?? ''),
            'total_weight' => 0,
            'status' => $_POST['status'] ?? 'draft',
            'notes' => trim($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        
        if ($id) {
            $docData['document_code'] = $_POST['document_code'];
            $result = krudApi('update', API_TABLE_DOCUMENTS, $docData, $id);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể cập nhật chứng từ.');
            }
            $docId = $id;

            $existingItems = krudApiList(API_TABLE_DOCUMENT_ITEMS, [
                ['field' => 'document_id', 'operator' => '=', 'value' => (int) $id],
            ], [], 1, 1000);
            foreach (($existingItems['data'] ?? []) as $existingItem) {
                krudApi('delete', API_TABLE_DOCUMENT_ITEMS, [], $existingItem['id']);
            }
        } else {
            $docData['document_code'] = 'CT' . date('ymd') . rand(1000, 9999);
            $result = krudApi('insert', API_TABLE_DOCUMENTS, $docData);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể tạo chứng từ.');
            }
            $docId = $result['data']['id'] ?? null;
            if (!$docId) {
                throw new RuntimeException('API không trả về mã chứng từ mới.');
            }
        }
        
        $totalWeight = 0;
        if (isset($_POST['waste_type_id']) && is_array($_POST['waste_type_id'])) {
            foreach ($_POST['waste_type_id'] as $idx => $wasteId) {
                if (!empty($wasteId) && !empty($_POST['quantity'][$idx])) {
                    $quantity = floatval($_POST['quantity'][$idx]);
                    $unitPrice = floatval($_POST['unit_price'][$idx] ?? 0);
                    $totalPrice = $quantity * $unitPrice;
                    
                    $result = krudApi('insert', API_TABLE_DOCUMENT_ITEMS, [
                        'document_id' => (int) $docId,
                        'waste_type_id' => (int) $wasteId,
                        'quantity' => $quantity,
                        'unit' => $_POST['unit'][$idx] ?? 'kg',
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                    ]);
                    if (!($result['success'] ?? false)) {
                        throw new RuntimeException($result['error'] ?? 'Không thể lưu chi tiết chứng từ.');
                    }
                    
                    $totalWeight += $quantity;
                }
            }
        }
        
        $result = krudApi('update', API_TABLE_DOCUMENTS, ['total_weight' => $totalWeight], $docId);
        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Không thể cập nhật tổng khối lượng.');
        }
        showAlert('Lưu chứng từ thành công!', 'success');
        redirect(BASE_URL . '/pages/documents.php');
    } catch (Exception $e) {
        showAlert('Lỗi: ' . $e->getMessage(), 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    checkPermission('documents', 'delete') or die('Không có quyền xóa chứng từ.');
    $deleteId = (int) $_POST['delete_document'];
    if ($deleteId <= 0) {
        die('Mã chứng từ không hợp lệ.');
    }
    $itemsResult = krudApiList(API_TABLE_DOCUMENT_ITEMS, [
        ['field' => 'document_id', 'operator' => '=', 'value' => $deleteId],
    ], [], 1, 1000);
    foreach (($itemsResult['data'] ?? []) as $item) {
        krudApi('delete', API_TABLE_DOCUMENT_ITEMS, [], $item['id']);
    }
    $deleteResult = krudApi('delete', API_TABLE_DOCUMENTS, [], $deleteId);
    if (!($deleteResult['success'] ?? false)) {
        showAlert($deleteResult['error'] ?? 'Không thể xóa chứng từ.', 'error');
    } else {
        showAlert('Đã xóa chứng từ.', 'success');
    }
    redirect(BASE_URL . '/pages/documents.php');
}

include '../includes/header.php';

$customerResult = krudApiList(API_TABLE_CUSTOMERS, [], ['full_name' => 'ASC'], 1, 1000);
$wasteResult = krudApiList(API_TABLE_WASTE_TYPES, [], ['waste_code' => 'ASC'], 1, 1000);
$customers = array_map('krudCustomerRecord', $customerResult['data'] ?? []);
$wastes = $wasteResult['data'] ?? [];

?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-file-alt me-2"></i>Quản lý Chứng từ</h1>
        </div>
        <?php if (checkPermission('documents', 'create')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#documentCreateModal">
            <i class="fas fa-plus me-2"></i>Tạo chứng từ
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'):
    $docResult = krudApiList(API_TABLE_DOCUMENTS, [], ['created_at' => 'DESC'], 1, 100);
    $docs = $docResult['data'] ?? [];
    $customersById = [];
    foreach ($customers as $customer) {
        $customersById[$customer['id']] = krudCustomerRecord($customer);
    }
    foreach ($docs as &$document) {
        $document['company_name'] = $customersById[$document['customer_id']]['company_name'] ?? '';
    }
    unset($document);
?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã CT</th>
                        <th>Khách hàng</th>
                        <th>Ngày thu gom</th>
                        <th>Khối lượng</th>
                        <th>Lái xe</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $d): ?>
                    <tr>
                        <td><strong><?php echo $d['document_code']; ?></strong></td>
                        <td><?php echo sanitize($d['company_name']); ?></td>
                        <td><?php echo formatDate($d['collection_date']); ?></td>
                        <td><?php echo formatNumber($d['total_weight']); ?> kg</td>
                        <td><?php echo sanitize($d['driver_name']); ?></td>
                        <td>
                            <?php $statusMeta = getDocumentStatusMeta($d['status'] ?? 'draft'); ?>
                            <span class="badge bg-<?php echo $statusMeta['color']; ?>"><?php echo $statusMeta['label']; ?></span>
                        </td>
                        <td class="action-buttons">
                            <?php if (checkPermission('documents', 'print')): ?>
                            <button onclick="printDocument(<?php echo $d['id']; ?>)" class="btn btn-sm btn-success" title="In">
                                <i class="fas fa-print"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (checkPermission('documents', 'edit')): ?>
                            <a href="?action=edit&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-info" aria-label="Sửa chứng từ"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if (checkPermission('documents', 'delete')): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa chứng từ này? Hành động không thể hoàn tác.');">
                                <button type="submit" name="delete_document" value="<?php echo (int) $d['id']; ?>" class="btn btn-sm btn-danger" aria-label="Xóa chứng từ"><i class="fas fa-trash"></i></button>
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

<?php if (checkPermission('documents', 'create')): ?>
<div class="modal fade" id="documentCreateModal" tabindex="-1" aria-labelledby="documentCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentCreateModalLabel">Tạo chứng từ thu gom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form method="POST" id="docCreateForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Chọn khách hàng --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>">[<?php echo $c['customer_code']; ?>] <?php echo sanitize($c['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ngày thu gom <span class="text-danger">*</span></label>
                            <input type="date" name="collection_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="draft">Nháp</option>
                                <option value="approved">Đã duyệt</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên lái xe</label>
                            <input type="text" name="driver_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số xe</label>
                            <input type="text" name="vehicle_number" class="form-control">
                        </div>
                    </div>

                    <div class="modal-form-section">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                            <h6 class="mb-0">Chi tiết chất thải</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCreateItem()">
                                <i class="fas fa-plus me-1"></i>Thêm dòng
                            </button>
                        </div>
                        <div id="docCreateItems">
                            <div class="row item-row g-2 align-items-start mb-2">
                                <div class="col-md-5">
                                    <select name="waste_type_id[]" class="form-select waste-select" onchange="updateCreatePrice(this)">
                                        <option value="">-- Chọn chất thải --</option>
                                        <?php foreach ($wastes as $w): ?>
                                        <option value="<?php echo $w['id']; ?>" data-price="<?php echo $w['price_per_unit']; ?>" data-unit="<?php echo $w['unit']; ?>">[<?php echo $w['waste_code']; ?>] <?php echo sanitize($w['waste_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="number" name="quantity[]" class="form-control" placeholder="Khối lượng" step="0.01" min="0"></div>
                                <div class="col-md-2"><input type="text" name="unit[]" class="form-control" value="kg" readonly></div>
                                <div class="col-md-2"><input type="number" name="unit_price[]" class="form-control" placeholder="Đơn giá" step="0.01" min="0" value="0"></div>
                                <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCreateItem(this)" aria-label="Xóa dòng"><i class="fas fa-times"></i></button></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu chứng từ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addCreateItem() {
    const container = document.getElementById('docCreateItems');
    const firstRow = container.querySelector('.item-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('input').forEach(input => {
        input.value = input.name === 'unit[]' ? 'kg' : '';
    });
    newRow.querySelector('select').selectedIndex = 0;
    container.appendChild(newRow);
}

function removeCreateItem(button) {
    const rows = document.querySelectorAll('#docCreateItems .item-row');
    if (rows.length > 1) {
        button.closest('.item-row').remove();
    }
}

function updateCreatePrice(select) {
    const row = select.closest('.item-row');
    const option = select.options[select.selectedIndex];
    row.querySelector('[name="unit_price[]"]').value = option.dataset.price || 0;
    row.querySelector('[name="unit[]"]').value = option.dataset.unit || 'kg';
}
</script>
<?php endif; ?>

<?php if ($openDocumentModal && checkPermission('documents', 'create')): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('documentCreateModal')).show();
});
</script>
<?php endif; ?>

<?php elseif ($action === 'edit'):
    checkPermission('documents', 'edit') or die('Không có quyền sửa chứng từ.');
    $doc = null;
    $items = [];
    if ($id) {
        $docResult = krudApiGet(API_TABLE_DOCUMENTS, $id);
        $doc = ($docResult['success'] ?? false) ? ($docResult['data'] ?? null) : null;
        $itemResult = krudApiList(API_TABLE_DOCUMENT_ITEMS, [
            ['field' => 'document_id', 'operator' => '=', 'value' => (int) $id],
        ], ['id' => 'ASC'], 1, 1000);
        $items = $itemResult['data'] ?? [];
    }
    
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><?php echo $id ? 'Sửa' : 'Tạo'; ?> chứng từ</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="docForm">
            <?php if ($doc): ?>
            <input type="hidden" name="document_code" value="<?php echo $doc['document_code']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Chọn khách hàng --</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($doc['customer_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                            [<?php echo $c['customer_code']; ?>] <?php echo sanitize($c['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ngày thu gom <span class="text-danger">*</span></label>
                    <input type="date" name="collection_date" class="form-control" required value="<?php echo $doc['collection_date'] ?? date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?php echo ($doc['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Nháp</option>
                        <option value="approved" <?php echo ($doc['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tên lái xe</label>
                    <input type="text" name="driver_name" class="form-control" value="<?php echo $doc['driver_name'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số xe</label>
                    <input type="text" name="vehicle_number" class="form-control" value="<?php echo $doc['vehicle_number'] ?? ''; ?>">
                </div>
            </div>
            
            <hr class="my-4">
            <h6 class="mb-3">Chi tiết chất thải</h6>
            
            <div id="itemsContainer">
                <?php if (empty($items)): $items = [['waste_type_id' => '', 'quantity' => '', 'unit' => 'kg', 'unit_price' => 0]]; endif; ?>
                <?php foreach ($items as $idx => $item): ?>
                <div class="row item-row mb-2">
                    <div class="col-md-5">
                        <select name="waste_type_id[]" class="form-select waste-select" onchange="updatePrice(this)">
                            <option value="">-- Chọn chất thải --</option>
                            <?php foreach ($wastes as $w): ?>
                            <option value="<?php echo $w['id']; ?>" data-price="<?php echo $w['price_per_unit']; ?>" data-unit="<?php echo $w['unit']; ?>" <?php echo ($item['waste_type_id'] ?? '') == $w['id'] ? 'selected' : ''; ?>>
                                [<?php echo $w['waste_code']; ?>] <?php echo sanitize($w['waste_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="quantity[]" class="form-control" placeholder="Khối lượng" step="0.01" value="<?php echo $item['quantity'] ?? ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="unit[]" class="form-control" value="<?php echo $item['unit'] ?? 'kg'; ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="unit_price[]" class="form-control" placeholder="Đơn giá" step="0.01" value="<?php echo $item['unit_price'] ?? 0; ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addItem()">
                <i class="fas fa-plus me-2"></i>Thêm dòng
            </button>
            
            <hr>
            
            <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo $doc['notes'] ?? ''; ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" name="save" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu</button>
                <a href="?" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Hủy</a>
            </div>
        </form>
    </div>
</div>

<script>
function addItem() {
    const container = document.getElementById('itemsContainer');
    const template = `
    <div class="row item-row mb-2">
        <div class="col-md-5">
            <select name="waste_type_id[]" class="form-select waste-select" onchange="updatePrice(this)">
                <option value="">-- Chọn chất thải --</option>
                <?php foreach ($wastes as $w): ?>
                <option value="<?php echo $w['id']; ?>" data-price="<?php echo $w['price_per_unit']; ?>" data-unit="<?php echo $w['unit']; ?>">
                    [<?php echo $w['waste_code']; ?>] <?php echo sanitize($w['waste_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="quantity[]" class="form-control" placeholder="Khối lượng" step="0.01">
        </div>
        <div class="col-md-2">
            <input type="text" name="unit[]" class="form-control" value="kg" readonly>
        </div>
        <div class="col-md-2">
            <input type="number" name="unit_price[]" class="form-control" placeholder="Đơn giá" step="0.01" value="0">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)"><i class="fas fa-times"></i></button>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', template);
}

function removeItem(btn) {
    if (document.querySelectorAll('.item-row').length > 1) {
        btn.closest('.item-row').remove();
    } else {
        alert('Phải có ít nhất 1 dòng!');
    }
}

function updatePrice(select) {
    const row = select.closest('.item-row');
    const option = select.options[select.selectedIndex];
    const price = option.getAttribute('data-price') || 0;
    const unit = option.getAttribute('data-unit') || 'kg';
    
    row.querySelector('[name="unit_price[]"]').value = price;
    row.querySelector('[name="unit[]"]').value = unit;
}
</script>

<?php endif; include '../includes/footer.php'; ?>
