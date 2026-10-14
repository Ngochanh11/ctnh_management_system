<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('customers', 'view') or die('Không có quyền xem lịch thu gom.');

include '../includes/header.php';

// Fetch schedules and join customer names from the API response.
$scheduleResult = krudApiList(API_TABLE_SCHEDULES, [
    ['field' => 'schedule_date', 'operator' => '>=', 'value' => date('Y-m-d')],
], ['schedule_date' => 'ASC'], 1, 100);
$customerResult = krudApiList(API_TABLE_CUSTOMERS, [], [], 1, 1000);
$customersById = [];
foreach (($customerResult['data'] ?? []) as $customer) {
    $customersById[$customer['id']] = krudCustomerRecord($customer);
}
$schedules = $scheduleResult['data'] ?? [];
foreach ($schedules as &$schedule) {
    $schedule['company_name'] = $customersById[$schedule['customer_id']]['company_name'] ?? '';
}
unset($schedule);
?>

<div class="page-header">
    <h1><i class="fas fa-calendar-alt me-2"></i>Lịch thu gom</h1>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Khách hàng</th>
                        <th>Tuần</th>
                        <th>Trạng thái</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                            Không có lịch thu gom sắp tới
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><strong><?php echo formatDate($s['schedule_date']); ?></strong></td>
                        <td><?php echo sanitize($s['company_name']); ?></td>
                        <td>Tuần <?php echo $s['week_number'] ?? '-'; ?></td>
                        <td>
                            <?php $statusMeta = getScheduleStatusMeta($s['status'] ?? 'scheduled'); ?>
                            <span class="badge bg-<?php echo $statusMeta['color']; ?>">
                                <?php echo $statusMeta['label']; ?>
                            </span>
                        </td>
                        <td><?php echo sanitize($s['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
