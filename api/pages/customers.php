<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('customers', 'view') or die('Không có quyền truy cập');
$openCustomerModal = ($_GET['action'] ?? '') === 'create';

// Xử lý lấy dữ liệu khách hàng để edit
if (isset($_GET['get_customer']) && $_GET['get_customer']) {
    checkPermission('customers', 'edit') or die('Không có quyền sửa khách hàng.');
    $id = $_GET['get_customer'];
    $result = krudApiGet(API_TABLE_CUSTOMERS, $id);
    $customer = ($result['success'] ?? false) ? krudCustomerRecord($result['data'] ?? []) : [];
    header('Content-Type: application/json');
    echo json_encode($customer ?: []);
    exit;
}

if (isset($_GET['lookup_tax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!checkPermission('customers', 'create') && !checkPermission('customers', 'edit')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Không có quyền tra cứu MST.']);
        exit;
    }

    $taxCode = preg_replace('/\D/', '', (string) $_GET['lookup_tax']);
    if (!defined('TAX_API_ENABLED') || !TAX_API_ENABLED || !preg_match('/^(\d{10}|\d{13})$/', $taxCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nhập MST gồm 10 hoặc 13 chữ số.']);
        exit;
    }

    $handle = curl_init(rtrim(TAX_API_URL, '/') . '/' . rawurlencode($taxCode));
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
    ]);
    $body = curl_exec($handle);
    $statusCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $lookup = is_string($body) ? json_decode($body, true) : null;
    $business = $lookup['data'] ?? null;
    if ($statusCode < 200 || $statusCode >= 300 || ($lookup['code'] ?? '') !== '00' || !is_array($business)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy thông tin MST hoặc dịch vụ tra cứu đang lỗi.']);
        exit;
    }

    $taxData = [
        'tax_code' => $taxCode,
        'company_name_official' => $business['name'] ?? '',
        'address_official' => $business['address'] ?? '',
        'source' => $lookup['metadata']['source'] ?? 'VietQR business API',
        'source_updated_at' => $lookup['metadata']['updatedAt'] ?? '',
    ];
    $existingTax = krudApiList(API_TABLE_TAX_INFO, [
        ['field' => 'tax_code', 'operator' => '=', 'value' => $taxCode],
    ], [], 1, 1);
    if (!($existingTax['success'] ?? false)) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Không thể kiểm tra danh mục MST trên máy chủ.']);
        exit;
    }
    if (!empty($existingTax['data'][0]['id'])) {
        $saveResult = krudApi('update', API_TABLE_TAX_INFO, $taxData, $existingTax['data'][0]['id']);
    } else {
        $saveResult = krudApi('insert', API_TABLE_TAX_INFO, $taxData);
    }
    if (!($saveResult['success'] ?? false)) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => $saveResult['error'] ?? 'Không thể lưu thông tin MST.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $taxData], JSON_UNESCAPED_UNICODE);
    exit;
}

// Xử lý CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = $_POST['id'] ?? null;
    if ($id && !checkPermission('customers', 'edit')) {
        die('Không có quyền sửa khách hàng.');
    }
    if (!$id && !checkPermission('customers', 'create')) {
        die('Không có quyền thêm khách hàng.');
    }
    
    try {
        $data = [
            'company_name' => trim($_POST['company_name']),
            'address' => trim($_POST['address'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'tax_code' => trim($_POST['tax_code'] ?? ''),
            'representative' => trim($_POST['representative'] ?? ''),
            'contract_date' => $_POST['contract_date'] ?? null,
            'contract_expiry_date' => $_POST['contract_expiry_date'] ?? null,
            'frequency' => trim($_POST['frequency'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];

        if ($data['tax_code'] !== '') {
            $taxInfoResult = krudApiList(API_TABLE_TAX_INFO, [
                ['field' => 'tax_code', 'operator' => '=', 'value' => $data['tax_code']],
            ], [], 1, 1);
            if (!($taxInfoResult['success'] ?? false) || empty($taxInfoResult['data'][0])) {
                throw new RuntimeException('Vui lòng chọn mã số thuế có trong danh mục.');
            }
        }
        
        if ($id) {
            $result = krudApi('update', API_TABLE_CUSTOMERS, krudCustomerData($data), $id);
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể cập nhật khách hàng.');
            }
            showAlert('Cập nhật khách hàng thành công!', 'success');
        } else {
            $data['customer_code'] = 'KH' . date('ymd') . rand(1000, 9999);
            $result = krudApi('insert', API_TABLE_CUSTOMERS, krudCustomerData($data));
            if (!($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Không thể thêm khách hàng.');
            }
            showAlert('Thêm khách hàng thành công!', 'success');
        }
        
        redirect(BASE_URL . '/pages/customers.php');
    } catch (Exception $e) {
        showAlert('Lỗi: ' . $e->getMessage(), 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    checkPermission('customers', 'delete') or die('Không có quyền xóa khách hàng.');
    $deleteId = (int) $_POST['delete_customer'];
    try {
        $result = krudApi('delete', API_TABLE_CUSTOMERS, [], $deleteId);
        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Không thể xóa khách hàng.');
        }
        showAlert('Xóa khách hàng thành công!', 'success');
        redirect(BASE_URL . '/pages/customers.php');
    } catch (Exception $e) {
        showAlert('Không thể xóa: ' . $e->getMessage(), 'error');
    }
}

include '../includes/header.php';

// Lấy danh sách khách hàng
$result = krudApiList(API_TABLE_CUSTOMERS, [], ['created_date' => 'DESC'], 1, 1000);
$customers = ($result['success'] ?? false) ? array_map('krudCustomerRecord', $result['data'] ?? []) : [];
$taxInfoResult = krudApiList(API_TABLE_TAX_INFO, [], ['tax_code' => 'ASC'], 1, 1000);
$taxInfoOptions = ($taxInfoResult['success'] ?? false) ? ($taxInfoResult['data'] ?? []) : [];
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-building me-2"></i>Quản lý Khách hàng</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Trang chủ</a></li>
                    <li class="breadcrumb-item active">Khách hàng</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <?php if (checkPermission('customers', 'create') || checkPermission('customers', 'edit')): ?>
            <a class="btn btn-outline-primary" href="<?php echo BASE_URL; ?>/pages/import_customers.php">
                <i class="fas fa-file-import me-2"></i>Nhập Excel
            </a>
            <?php endif; ?>
            <?php if (checkPermission('documents', 'print')): ?>
            <button type="button" class="btn btn-outline-success" onclick="printSelectedCustomers()">
                <i class="fas fa-print me-2"></i>In khách đã chọn
            </button>
            <?php endif; ?>
            <?php if (checkPermission('customers', 'create')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openAddModal()">
                <i class="fas fa-plus me-2"></i>Thêm khách hàng
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <?php if (checkPermission('documents', 'print')): ?><th><input type="checkbox" aria-label="Chọn tất cả khách hàng" onchange="toggleCustomers(this)"></th><?php endif; ?>
                        <th>Mã KH</th>
                        <th>Tên công ty</th>
                        <th>Địa chỉ</th>
                        <th>Điện thoại</th>
                        <th>MST</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <?php if (checkPermission('documents', 'print')): ?><td><input type="checkbox" class="customer-checkbox" value="<?php echo (int) $c['id']; ?>" aria-label="Chọn <?php echo sanitize($c['company_name']); ?>"></td><?php endif; ?>
                        <td><strong><?php echo $c['customer_code']; ?></strong></td>
                        <td><?php echo sanitize($c['company_name']); ?></td>
                        <td><?php echo substr(sanitize($c['address']), 0, 50); ?>...</td>
                        <td>
                            <?php if (!empty($c['phone'])): ?>
                                <a href="tel:<?php echo sanitize($c['phone']); ?>"><?php echo sanitize($c['phone']); ?></a>
                            <?php else: ?>
                                <span class="text-muted">Chưa có</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($c['tax_code'])): ?>
                                <span class="font-monospace"><?php echo sanitize($c['tax_code']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">Chưa có</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo $c['status'] === 'active' ? 'Hoạt động' : 'Ngừng'; ?>
                            </span>
                        </td>
                        <td class="action-buttons">
                            <?php if (checkPermission('wastes', 'view')): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="customer_waste.php?customer_id=<?php echo (int) $c['id']; ?>" title="Quản lý chất thải" aria-label="Quản lý chất thải của <?php echo sanitize($c['company_name']); ?>">
                                <i class="fas fa-recycle"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (checkPermission('customers', 'edit')): ?>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?php echo (int) $c['id']; ?>)" aria-label="Sửa khách hàng">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (checkPermission('customers', 'delete')): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa khách hàng này? Hành động không thể hoàn tác.');">
                                <button type="submit" name="delete_customer" value="<?php echo (int) $c['id']; ?>" class="btn btn-sm btn-danger" aria-label="Xóa khách hàng"><i class="fas fa-trash"></i></button>
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

<script>
function toggleCustomers(selectAll) {
    document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function printSelectedCustomers() {
    const ids = Array.from(document.querySelectorAll('.customer-checkbox:checked'), checkbox => checkbox.value);
    if (!ids.length) {
        alert('Vui lòng chọn ít nhất một khách hàng.');
        return;
    }
    const url = '<?php echo BASE_URL; ?>/pages/print_certificate.php?ids=' + ids.map(encodeURIComponent).join(',');
    if (!window.open(url, '_blank')) {
        alert('Trình duyệt đã chặn tab in. Hãy cho phép popup cho trang này rồi thử lại.');
    }
}
</script>

<!-- Modal Thêm/Sửa Khách hàng -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">Thêm khách hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="customerForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="customerId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên công ty <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="companyName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="taxCode">Mã số thuế</label>
                            <div class="input-group">
                                <input type="text" name="tax_code" id="taxCode" class="form-control" list="taxCodeOptions" placeholder="Nhập MST rồi tra cứu" onchange="selectTaxInfo(this)" aria-describedby="taxCodeHelp">
                                <datalist id="taxCodeOptions">
                                <?php foreach ($taxInfoOptions as $taxInfo): ?>
                                <option value="<?php echo sanitize($taxInfo['tax_code'] ?? ''); ?>"><?php echo sanitize($taxInfo['tax_code'] ?? ''); ?><?php echo !empty($taxInfo['company_name_official']) ? ' - ' . sanitize($taxInfo['company_name_official']) : ''; ?></option>
                                <?php endforeach; ?>
                                </datalist>
                                <button type="button" class="btn btn-outline-primary" onclick="lookupTaxInfo()"><i class="fas fa-search me-1"></i>Tra cứu</button>
                            </div>
                            <div id="taxCodeHelp" class="form-text">Chọn MST từ danh mục đã khai báo trên máy chủ.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Địa chỉ</label>
                            <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Điện thoại</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Người đại diện</label>
                            <input type="text" name="representative" id="representative" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ngày ký HĐ</label>
                            <input type="date" name="contract_date" id="contractDate" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ngày hết hạn</label>
                            <input type="date" name="contract_expiry_date" id="contractExpiryDate" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tần suất</label>
                            <input type="text" name="frequency" id="frequency" class="form-control" placeholder="Hàng tuần, 1 lần/tháng...">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Ngừng hoạt động</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="submit" name="save" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const TAX_INFO_OPTIONS = <?php echo json_encode($taxInfoOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function openAddModal() {
    document.getElementById('customerModalLabel').textContent = 'Thêm khách hàng';
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
}

function selectTaxInfo(select) {
    const taxInfo = TAX_INFO_OPTIONS.find(item => item.tax_code === select.value);
    if (!taxInfo) return;

    document.getElementById('companyName').value = taxInfo.company_name_official || '';
    document.getElementById('address').value = taxInfo.address_official || '';
    document.getElementById('representative').value = taxInfo.representative || '';
}

async function lookupTaxInfo() {
    const taxCode = document.getElementById('taxCode').value.trim();
    if (!taxCode) {
        alert('Nhập mã số thuế cần tra cứu.');
        return;
    }
    showLoading();
    try {
        const response = await fetch(`?lookup_tax=${encodeURIComponent(taxCode)}`, { headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Không tra cứu được MST.');

        const option = document.createElement('option');
        option.value = result.data.tax_code;
        option.label = result.data.company_name_official;
        document.getElementById('taxCodeOptions').appendChild(option);
        document.getElementById('taxCode').value = result.data.tax_code;
        document.getElementById('companyName').value = result.data.company_name_official || '';
        document.getElementById('address').value = result.data.address_official || '';
        document.getElementById('taxCodeHelp').textContent = 'Đã tra cứu và lưu MST vào danh mục.';
    } catch (error) {
        alert(error.message);
    } finally {
        hideLoading();
    }
}

function selectExistingTaxCode(taxCode) {
    const input = document.getElementById('taxCode');
    if (!taxCode) {
        input.value = '';
        return;
    }
    const options = document.getElementById('taxCodeOptions');
    if (![...options.options].some(option => option.value === taxCode)) {
        const option = document.createElement('option');
        option.value = taxCode;
        options.appendChild(option);
    }
    input.value = taxCode;
}

async function openEditModal(id) {
    document.getElementById('customerModalLabel').textContent = 'Sửa khách hàng';
    
    showLoading();
    try {
        const response = await fetch(`?get_customer=${id}`);
        const customer = await response.json();
        
        if (customer && customer.id) {
            const taxInfo = TAX_INFO_OPTIONS.find(item => String(item.customer_id) === String(customer.id)) || null;
            document.getElementById('customerId').value = customer.id;
            document.getElementById('companyName').value = customer.company_name || '';
            selectExistingTaxCode(customer.tax_code || taxInfo?.tax_code || '');
            document.getElementById('address').value = customer.address || '';
            document.getElementById('phone').value = customer.phone || '';
            document.getElementById('email').value = customer.email || '';
            document.getElementById('representative').value = customer.representative || taxInfo?.representative || '';
            if (taxInfo) {
                document.getElementById('companyName').value ||= taxInfo.company_name_official || '';
                document.getElementById('address').value ||= taxInfo.address_official || '';
            }
            document.getElementById('contractDate').value = customer.contract_date || '';
            document.getElementById('contractExpiryDate').value = customer.contract_expiry_date || '';
            document.getElementById('frequency').value = customer.frequency || '';
            document.getElementById('status').value = customer.status || 'active';
            
            // Mở modal
            const modal = new bootstrap.Modal(document.getElementById('customerModal'));
            modal.show();
        } else {
            alert('Không tìm thấy thông tin khách hàng!');
        }
    } catch (error) {
        alert('Lỗi tải dữ liệu: ' + error.message);
    } finally {
        hideLoading();
    }
}

</script>

<?php if ($openCustomerModal && checkPermission('customers', 'create')): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal')).show();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
