<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('reports', 'view') or die('Không có quyền xem lịch sử in.');

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-29 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$customerId = trim((string) ($_GET['customer_id'] ?? ''));
$filters = [
    ['field' => 'print_date', 'operator' => '>=', 'value' => $fromDate . ' 00:00:00'],
    ['field' => 'print_date', 'operator' => '<=', 'value' => $toDate . ' 23:59:59'],
];
$historyResult = krudApiList(API_TABLE_PRINT_HISTORY, $filters, ['print_date' => 'DESC'], 1, 1000);
$customerHistoryResult = krudApiList(API_TABLE_CUSTOMER_PRINT_HISTORY, $filters, ['print_date' => 'DESC'], 1, 1000);
$documentResult = krudApiList(API_TABLE_DOCUMENTS, [], [], 1, 5000);
$customerResult = krudApiList(API_TABLE_CUSTOMERS, [], [], 1, 5000);
$userResult = krudApiList(API_TABLE_USERS, [], [], 1, 1000);
$documentsById = [];
$customersById = [];
$usersById = [];
foreach (($documentResult['data'] ?? []) as $document) {
    $documentsById[(string) $document['id']] = $document;
}
foreach (($customerResult['data'] ?? []) as $customer) {
    $customersById[(string) $customer['id']] = krudCustomerRecord($customer);
}
foreach (($userResult['data'] ?? []) as $user) {
    $usersById[(string) $user['id']] = $user;
}
$history = [];
foreach (($historyResult['data'] ?? []) as $entry) {
    $document = $documentsById[(string) ($entry['document_id'] ?? '')] ?? [];
    $customer = $customersById[(string) ($document['customer_id'] ?? '')] ?? [];
    if ($customerId !== '' && (string) ($document['customer_id'] ?? '') !== $customerId) {
        continue;
    }
    $entry['document_code'] = $document['document_code'] ?? '';
    $entry['company_name'] = $customer['company_name'] ?? '';
    $entry['user_name'] = $usersById[(string) ($entry['user_id'] ?? '')]['full_name'] ?? '';
    $entry['print_type'] = 'Theo chứng từ';
    $history[] = $entry;
}
foreach (($customerHistoryResult['data'] ?? []) as $entry) {
    $customer = $customersById[(string) ($entry['customer_id'] ?? '')] ?? [];
    if ($customerId !== '' && (string) ($entry['customer_id'] ?? '') !== $customerId) {
        continue;
    }
    $entry['document_code'] = '-';
    $entry['company_name'] = $customer['company_name'] ?? '';
    $entry['user_name'] = $usersById[(string) ($entry['user_id'] ?? '')]['full_name'] ?? '';
    $entry['print_type'] = 'Theo khách hàng';
    $history[] = $entry;
}
usort($history, static fn($left, $right) => ($right['print_date'] ?? '') <=> ($left['print_date'] ?? ''));
$customers = array_values($customersById);

include '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-clock-rotate-left me-2"></i>Lịch sử in chứng từ</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label" for="fromDate">Từ ngày</label><input class="form-control" type="date" id="fromDate" name="from_date" value="<?php echo sanitize($fromDate); ?>"></div>
            <div class="col-md-3"><label class="form-label" for="toDate">Đến ngày</label><input class="form-control" type="date" id="toDate" name="to_date" value="<?php echo sanitize($toDate); ?>"></div>
            <div class="col-md-4"><label class="form-label" for="customerId">Khách hàng</label><select class="form-select" id="customerId" name="customer_id"><option value="">Tất cả khách hàng</option><?php foreach ($customers as $customer): ?><option value="<?php echo (int) $customer['id']; ?>" <?php echo $customerId === (string) $customer['id'] ? 'selected' : ''; ?>><?php echo sanitize($customer['company_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-2"></i>Lọc</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Thời gian in</th><th>Loại</th><th>Mã chứng từ</th><th>Khách hàng</th><th>Người thực hiện</th><th>Số trang</th></tr></thead>
            <tbody>
                <?php if (!$history): ?><tr><td colspan="6" class="text-center text-muted py-4">Không có lịch sử in trong khoảng đã chọn.</td></tr><?php endif; ?>
                <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?php echo sanitize(formatDate($entry['print_date'] ?? '', 'd/m/Y H:i')); ?></td>
                    <td><?php echo sanitize($entry['print_type']); ?></td>
                    <td><?php echo sanitize($entry['document_code']); ?></td>
                    <td><?php echo sanitize($entry['company_name']); ?></td>
                    <td><?php echo sanitize($entry['user_name']); ?></td>
                    <td><?php echo formatNumber($entry['page_count'] ?? 1); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>