<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('documents', 'print') or die('Không có quyền');

include '../includes/header.php';

// Load documents once instead of making one API request per status.
$result = krudApiList(API_TABLE_DOCUMENTS, [], ['collection_date' => 'DESC'], 1, 1000);
$documentsByStatus = array_values(array_filter($result['data'] ?? [], static function ($document) {
    return in_array($document['status'] ?? '', ['draft', 'pending', 'approved'], true);
}));
usort($documentsByStatus, static function ($left, $right) {
    return [$right['collection_date'] ?? '', $right['created_at'] ?? ''] <=> [$left['collection_date'] ?? '', $left['created_at'] ?? ''];
});
$docs = array_slice($documentsByStatus, 0, 200);
$customerResult = krudApiList(API_TABLE_CUSTOMERS, [], [], 1, 1000);
$customersById = [];
foreach (($customerResult['data'] ?? []) as $customer) {
    $customersById[$customer['id']] = krudCustomerRecord($customer);
}
foreach ($docs as &$document) {
    $document['company_name'] = $customersById[$document['customer_id']]['company_name'] ?? '';
}
unset($document);
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-print me-2"></i>In chứng từ nguy hại</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                <i class="fas fa-check-square me-2"></i>Chọn tất cả
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                <i class="fas fa-square me-2"></i>Bỏ chọn
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" id="printForm">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAllCheckbox" aria-label="Chọn tất cả chứng từ" onchange="toggleAll(this)">
                            </th>
                            <th>Mã chứng từ</th>
                            <th>Khách hàng</th>
                            <th>Ngày thu gom</th>
                            <th>Khối lượng</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($docs)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                Không có chứng từ nào
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($docs as $doc): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_docs[]" value="<?php echo $doc['id']; ?>" class="doc-checkbox">
                            </td>
                            <td><strong><?php echo $doc['document_code']; ?></strong></td>
                            <td><?php echo sanitize($doc['company_name']); ?></td>
                            <td><?php echo formatDate($doc['collection_date']); ?></td>
                            <td><?php echo formatNumber($doc['total_weight']); ?> kg</td>
                            <td>
                                <?php $statusMeta = getDocumentStatusMeta($doc['status'] ?? 'draft'); ?>
                                <span class="badge bg-<?php echo $statusMeta['color']; ?>">
                                    <?php echo $statusMeta['label']; ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button type="button" class="btn btn-sm btn-success" onclick="printSingle(<?php echo $doc['id']; ?>)">
                                    <i class="fas fa-print me-1"></i>In
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($docs)): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span class="badge bg-info" id="selectedCount">0</span> chứng từ đã chọn
                </div>
                <button type="button" onclick="printSelected()" class="btn btn-primary" id="printBtn" disabled>
                    <i class="fas fa-print me-2"></i>In các chứng từ đã chọn
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="alert alert-info mt-3">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Lưu ý:</strong>
    <ul class="mb-0 mt-2">
        <li>Mỗi chứng từ giữ nguyên mẫu form CTNH</li>
        <li>Tối đa 10 dòng chất thải trên mỗi trang; từ dòng 11 tự chuyển sang trang tiếp theo</li>
    </ul>
</div>

<script>
function updateCount() {
    const checked = document.querySelectorAll('.doc-checkbox:checked').length;
    const selectedCount = document.getElementById('selectedCount');
    const printButton = document.getElementById('printBtn');
    if (selectedCount) selectedCount.textContent = checked;
    if (printButton) printButton.disabled = checked === 0;
}

function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.doc-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateCount();
}

function selectAll() {
    document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateCount();
}

function printSingle(id) {
    window.open('<?php echo BASE_URL; ?>/modules/print.php?id=' + id, '_blank');
}

function printSelected() {
    const ids = Array.from(document.querySelectorAll('.doc-checkbox:checked'), checkbox => checkbox.value);
    if (!ids.length) return;

    const printUrl = '<?php echo BASE_URL; ?>/modules/print.php?ids=' + ids.map(encodeURIComponent).join(',');
    if (!window.open(printUrl, '_blank')) {
        alert('Trình duyệt đã chặn tab in. Hãy cho phép popup cho trang này rồi bấm in lại.');
    }
}

// Update count on checkbox change
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('doc-checkbox')) {
        updateCount();
    }
});

// Initial count
updateCount();
</script>

<?php include '../includes/footer.php'; ?>
