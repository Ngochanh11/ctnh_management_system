<?php
/**
 * CTNH Management System - Customer Waste Management
 * Quản lý chất thải của khách hàng
 */

require_once __DIR__ . '/../includes/config.php';
requireLogin();
checkPermission('customers', 'view') or die('Không có quyền xem khách hàng');
checkPermission('wastes', 'view') or die('Không có quyền xem chất thải');

// Lấy customer_id từ URL
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if (!$customer_id) {
    redirect('customers.php');
}

if (empty($_SESSION['customer_waste_csrf'])) {
    $_SESSION['customer_waste_csrf'] = bin2hex(random_bytes(32));
}

function customerWasteRespond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeWasteStateValue($value)
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/\s+/', '', $normalized);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
    $compact = $transliterated !== false ? strtolower((string) $transliterated) : $normalized;
    $compact = preg_replace('/[^a-z]/', '', $compact);

    if ($compact === 'solid' || strpos($compact, 'ran') !== false) {
        return 'solid';
    }
    if ($compact === 'liquid' || strpos($compact, 'long') !== false) {
        return 'liquid';
    }
    if ($compact === 'sludge' || strpos($compact, 'bun') !== false) {
        return 'sludge';
    }

    return '';
}

function inferWasteStateFromName($value)
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return '';
    }

    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $compact = $transliterated !== false ? strtolower((string) $transliterated) : $text;
    $compact = preg_replace('/\s+/', '', $compact);
    $compact = preg_replace('/[^a-z]/', '', $compact);

    $sludgeKeywords = ['bun', 'can', 'cann', 'ba', 'tro', 'chat', 'loc'];
    $liquidKeywords = ['dau', 'nuoc', 'dich', 'long', 'muoi', 'xoa', 'hoa', 'sua', 'xyl', 'chatlong'];

    foreach ($sludgeKeywords as $keyword) {
        if (strpos($compact, $keyword) !== false) {
            return 'sludge';
        }
    }

    foreach ($liquidKeywords as $keyword) {
        if (strpos($compact, $keyword) !== false) {
            return 'liquid';
        }
    }

    return 'solid';
}

if (isset($_GET['ajax'])) {
    $ajaxAction = (string) $_GET['ajax'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $ajaxAction === 'customer') {
        $result = krudApiGet(API_TABLE_CUSTOMERS, $customer_id);
        customerWasteRespond($result);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $ajaxAction === 'list') {
        $result = krudApiList(TABLE_CUSTOMER_WASTE, [
            ['field' => 'customer_id', 'operator' => '=', 'value' => $customer_id],
        ], ['id' => 'ASC'], 1, 1000);
        $linksResult = krudApiList(API_TABLE_CUSTOMER_WASTES, [
            ['field' => 'customer_id', 'operator' => '=', 'value' => $customer_id],
        ], ['id' => 'ASC'], 1, 1000);
        $typesResult = krudApiList(API_TABLE_WASTE_TYPES, [], [], 1, 1000);
        $typesById = [];
        foreach (($typesResult['data'] ?? []) as $type) {
            $typesById[(string) $type['id']] = $type;
        }
        $linked = [];
        foreach (($linksResult['data'] ?? []) as $link) {
            $type = $typesById[(string) ($link['waste_type_id'] ?? '')] ?? null;
            if (!$type) {
                continue;
            }
            $linked[] = [
                'id' => (int) $link['id'],
                'source' => 'catalog',
                'waste_name' => $type['waste_name'] ?? '',
                'waste_code' => $type['waste_code'] ?? '',
                'state' => normalizeWasteStateValue($type['state'] ?? '') ?: inferWasteStateFromName($type['waste_name'] ?? ($type['description'] ?? '')),
                'quantity' => $link['estimated_quantity'] ?? '',
                'treatment_method' => '',
            ];
        }
        customerWasteRespond([
            'success' => (bool) ($result['success'] ?? false) && (bool) ($linksResult['success'] ?? false) && (bool) ($typesResult['success'] ?? false),
            'data' => $result['data'] ?? [],
            'linked' => $linked,
        ]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!hash_equals($_SESSION['customer_waste_csrf'], (string) ($payload['csrf_token'] ?? ''))) {
            customerWasteRespond(['success' => false, 'error' => 'Phiên gửi biểu mẫu hết hạn. Hãy tải lại trang.'], 403);
        }
        $id = (int) ($payload['id'] ?? 0);
        if ($ajaxAction === 'save') {
            $existing = null;
            if ($id > 0) {
                if (!checkPermission('wastes', 'edit')) {
                    customerWasteRespond(['success' => false, 'error' => 'Không có quyền sửa chất thải.'], 403);
                }
                $existingResult = krudApiGet(TABLE_CUSTOMER_WASTE, $id);
                $existing = $existingResult['data'] ?? null;
                if (!$existing || (int) ($existing['customer_id'] ?? 0) !== $customer_id) {
                    customerWasteRespond(['success' => false, 'error' => 'Không tìm thấy dòng chất thải thuộc khách hàng này.'], 404);
                }
            } elseif (!checkPermission('wastes', 'create')) {
                customerWasteRespond(['success' => false, 'error' => 'Không có quyền thêm chất thải.'], 403);
            }

            $state = normalizeWasteStateValue($payload['state'] ?? '');
            $data = [
                'customer_id' => $customer_id,
                'waste_name' => trim((string) ($payload['waste_name'] ?? '')),
                'waste_code' => trim((string) ($payload['waste_code'] ?? '')),
                'state' => $state,
                'quantity' => (float) ($payload['quantity'] ?? 0),
                'treatment_method' => trim((string) ($payload['treatment_method'] ?? '')),
                'notes' => trim((string) ($payload['notes'] ?? '')),
            ];
            if ($data['waste_name'] === '' || $data['waste_code'] === '' || $data['quantity'] <= 0 || $state === '') {
                customerWasteRespond(['success' => false, 'error' => 'Kiểm tra tên, mã, trạng thái và số lượng chất thải.'], 422);
            }
            $result = $id
                ? krudApi('update', TABLE_CUSTOMER_WASTE, $data, $id)
                : krudApi('insert', TABLE_CUSTOMER_WASTE, $data);
            customerWasteRespond($result, ($result['success'] ?? false) ? 200 : 502);
        }
        if ($ajaxAction === 'delete') {
            if (!checkPermission('wastes', 'delete')) {
                customerWasteRespond(['success' => false, 'error' => 'Không có quyền xóa chất thải.'], 403);
            }
            $existingResult = krudApiGet(TABLE_CUSTOMER_WASTE, $id);
            $existing = $existingResult['data'] ?? null;
            if (!$existing || (int) ($existing['customer_id'] ?? 0) !== $customer_id) {
                customerWasteRespond(['success' => false, 'error' => 'Không tìm thấy dòng chất thải thuộc khách hàng này.'], 404);
            }
            $result = krudApi('delete', TABLE_CUSTOMER_WASTE, [], $id);
            customerWasteRespond($result, ($result['success'] ?? false) ? 200 : 502);
        }
    }
    customerWasteRespond(['success' => false, 'error' => 'Yêu cầu không hợp lệ.'], 400);
}

$page_title = 'Quản lý Chất thải - ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="text-gradient">
            <i class="bi bi-trash"></i> Quản lý Chất thải khách hàng
        </h2>
        <div id="customerInfo" class="text-muted mt-2"></div>
    </div>
    <div class="col-md-6 text-end">
        <?php if (checkPermission('documents', 'print')): ?><a href="print_certificate.php?id=<?php echo $customer_id; ?>" 
           class="btn btn-success" target="_blank">
            <i class="bi bi-printer"></i> In chứng từ
        </a><?php endif; ?>
        <?php if (checkPermission('wastes', 'create')): ?><button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#wasteModal" onclick="resetWasteForm()">
            <i class="bi bi-plus-circle"></i> Thêm chất thải
        </button><?php endif; ?>
        <a href="customers.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>
</div>

<!-- Data Table -->
<div class="data-table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th width="50">STT</th>
                    <th>Tên chất thải</th>
                    <th>Mã CTNH</th>
                    <th width="100">Trạng thái</th>
                    <th width="120">Số lượng (kg)</th>
                    <th>Phương pháp xử lý</th>
                    <th width="150">Thao tác</th>
                </tr>
            </thead>
            <tbody id="wasteTable">
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Waste Modal -->
<div class="modal fade" id="wasteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Thêm chất thải</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="wasteForm">
                    <input type="hidden" id="waste_id" value="">
                    <input type="hidden" class="data-element" name="customer_id" value="<?php echo $customer_id; ?>">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Tên chất thải <span class="text-danger">*</span></label>
                            <input type="text" class="form-control data-element" name="waste_name" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mã CTNH <span class="text-danger">*</span></label>
                            <input type="text" class="form-control data-element" name="waste_code" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                            <select class="form-select data-element" name="state" required>
                                <option value="">-- Chọn --</option>
                                <option value="solid">Rắn</option>
                                <option value="liquid">Lỏng</option>
                                <option value="sludge">Bùn</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Số lượng (kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control data-element" name="quantity" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phương pháp xử lý</label>
                            <select class="form-select data-element" name="treatment_method">
                                <option value="">-- Chọn --</option>
                                <option value="TG">TG - Tái sử dụng</option>
                                <option value="PT">PT - Phục hồi/tái chế</option>
                                <option value="DH">DH - Đốt hủy</option>
                                <option value="ST">ST - Sử dụng làm nhiên liệu</option>
                                <option value="TĐ">TĐ - Thải đúng</option>
                                <option value="HR">HR - Trộn hợp</option>
                                <option value="CL">CL - Cô lập/bảng kín</option>
                                <option value="C">C - Chôn lấp</option>
                                <option value="SC">SC - Sơ chế</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control data-element" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-info" onclick="saveWaste()">
                    <i class="bi bi-save"></i> Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CUSTOMER_ID = <?php echo $customer_id; ?>;
const CSRF_TOKEN = <?php echo json_encode($_SESSION['customer_waste_csrf']); ?>;
const CAN_EDIT_WASTE = <?php echo json_encode(checkPermission('wastes', 'edit')); ?>;
const CAN_DELETE_WASTE = <?php echo json_encode(checkPermission('wastes', 'delete')); ?>;
let loadedWasteItems = [];

document.addEventListener('DOMContentLoaded', function() {
    loadCustomerInfo();
    loadWasteItems();
});

function loadCustomerInfo() {
    fetch(`?customer_id=${CUSTOMER_ID}&ajax=customer`, { headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(res => {
        const customer = res.data;
        if (res.success && customer) {
            document.getElementById('customerInfo').innerHTML = 
                `<strong>${escapeHtml(customer.full_name || '')}</strong> - 
                 ${escapeHtml(customer.company || '')} - 
                 ${escapeHtml(customer.phone || '')}`;
        }
    });
}

function normalizeWasteState(value) {
    const raw = String(value ?? '').trim().toLowerCase();
    const compact = raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z]/g, '');
    if (compact === 'solid' || compact.includes('ran')) return 'solid';
    if (compact === 'liquid' || compact.includes('long')) return 'liquid';
    if (compact === 'sludge' || compact.includes('bun')) return 'sludge';
    return '';
}

function loadWasteItems() {
    fetch(`?customer_id=${CUSTOMER_ID}&ajax=list`, { headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(res => {
        const tbody = document.getElementById('wasteTable');
        
        if (!res.success) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">
                <i class="bi bi-exclamation-triangle"></i> ${res.error || 'Có lỗi xảy ra'}
            </td></tr>`;
            return;
        }
        loadedWasteItems = res.data;
        
        const legacyItems = Array.isArray(res.data) ? res.data : [];
        const legacyCodes = new Set(legacyItems.map(item => String(item.waste_code || '').trim()).filter(Boolean));
        const linkedItems = (Array.isArray(res.linked) ? res.linked : []).filter(item => !legacyCodes.has(String(item.waste_code || '').trim()));
        const displayItems = [...legacyItems, ...linkedItems];
        loadedWasteItems = legacyItems;

        if (displayItems.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">
                <i class="bi bi-inbox"></i> Chưa có chất thải nào
            </td></tr>`;
            return;
        }
        
        // Calculate total
        const totalQuantity = displayItems.reduce((sum, item) => 
            sum + parseFloat(item.quantity || 0), 0);
        
        tbody.innerHTML = displayItems.map((item, index) => {
            const stateKey = normalizeWasteState(item.state);
            const stateText = {
                'solid': '<span class="badge bg-secondary">Rắn</span>',
                'liquid': '<span class="badge bg-info">Lỏng</span>',
                'sludge': '<span class="badge bg-warning">Bùn</span>'
            }[stateKey] || '';
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${escapeHtml(item.waste_name || '')}</strong></td>
                    <td><code>${escapeHtml(item.waste_code || '')}</code></td>
                    <td>${stateText}</td>
                    <td class="text-end">${parseFloat(item.quantity || 0).toFixed(2)}</td>
                    <td>${escapeHtml(item.treatment_method || '-')}</td>
                    <td>
                        <div class="table-actions">
                            ${item.source !== 'catalog' && CAN_EDIT_WASTE ? `
                            <button class="btn btn-sm btn-info" onclick="editWaste(${item.id})" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </button>
                            ` : ''}
                            ${item.source !== 'catalog' && CAN_DELETE_WASTE ? `
                            <button class="btn btn-sm btn-danger" onclick="deleteWaste(${item.id})" title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                            ` : ''}
                            ${item.source === 'catalog' ? '<span class="text-muted">Danh mục</span>' : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('') + `
            <tr class="table-info fw-bold">
                <td colspan="4" class="text-end">Tổng cộng:</td>
                <td class="text-end">${totalQuantity.toFixed(2)}</td>
                <td colspan="2"></td>
            </tr>
        `;
    });
}

function saveWaste() {
    if (!validateForm('#wasteForm')) {
        showAlert('Vui lòng điền đầy đủ thông tin bắt buộc', 'warning');
        return;
    }
    
    const id = document.getElementById('waste_id').value;
    const action = id ? 'update' : 'insert';
    
    const formData = Object.fromEntries(new FormData(document.getElementById('wasteForm')).entries());
    fetch(`?customer_id=${CUSTOMER_ID}&ajax=save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ ...formData, id, csrf_token: CSRF_TOKEN })
    }).then(response => response.json()).then(res => {
            if (res.success) {
                showAlert(action === 'insert' 
                    ? 'Thêm chất thải thành công!' 
                    : 'Cập nhật chất thải thành công!', 'success');
                
                bootstrap.Modal.getInstance(document.getElementById('wasteModal')).hide();
                loadWasteItems();
                resetWasteForm();
            } else {
                showAlert('Lỗi: ' + (res.error || 'Không thể lưu dữ liệu'), 'danger');
            }
        }).catch(error => showAlert('Lỗi: ' + error.message, 'danger'));
}

function editWaste(id) {
    const waste = loadedWasteItems.find(item => String(item.id) === String(id));
    if (waste) {
            document.getElementById('waste_id').value = id;
            document.getElementById('modalTitle').textContent = 'Sửa chất thải';
            populateForm('#wasteForm', waste);
            
            new bootstrap.Modal(document.getElementById('wasteModal')).show();
    } else {
        showAlert('Không thể tải dữ liệu', 'danger');
    }
}

function deleteWaste(id) {
    if (!confirmDelete('Bạn có chắc muốn xóa chất thải này?')) return;
    
    fetch(`?customer_id=${CUSTOMER_ID}&ajax=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ id, csrf_token: CSRF_TOKEN })
    }).then(response => response.json()).then(res => {
        if (res.success) {
            showAlert('Xóa chất thải thành công!', 'success');
            loadWasteItems();
        } else {
            showAlert('Lỗi: ' + (res.error || 'Không thể xóa'), 'danger');
        }
    }).catch(error => showAlert('Lỗi: ' + error.message, 'danger'));
}

function resetWasteForm() {
    document.getElementById('waste_id').value = '';
    document.getElementById('modalTitle').textContent = 'Thêm chất thải';
    resetForm('#wasteForm');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
