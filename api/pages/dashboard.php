<?php
require_once '../includes/config.php';
requireLogin();

$canViewCustomers = checkPermission('customers', 'view');
$canViewDocuments = checkPermission('documents', 'view');
$canViewSchedules = $canViewCustomers;

// Lấy thống kê tổng quan
try {
    $fetchRows = static function ($table, $filters = [], $sort = [], $limit = 1000) {
        $result = krudApiList($table, $filters, $sort, 1, $limit);
        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Không thể tải dữ liệu từ máy chủ.');
        }
        return $result['data'] ?? [];
    };

    $customers = $canViewCustomers || $canViewDocuments || $canViewSchedules
        ? $fetchRows(API_TABLE_CUSTOMERS)
        : [];
    $customersById = [];
    foreach ($customers as $customer) {
        $customersById[$customer['id']] = krudCustomerRecord($customer);
    }
    $totalCustomers = $canViewCustomers ? count($customers) : 0;

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $monthDocuments = $canViewDocuments ? $fetchRows(API_TABLE_DOCUMENTS, [
        ['field' => 'collection_date', 'operator' => '>=', 'value' => $monthStart],
        ['field' => 'collection_date', 'operator' => '<=', 'value' => $monthEnd],
    ], [], 5000) : [];
    $totalDocuments = count($monthDocuments);
    $totalWeight = array_sum(array_map(static fn($document) => (float) ($document['total_weight'] ?? 0), $monthDocuments));

    $recentDocuments = $canViewDocuments ? $fetchRows(API_TABLE_DOCUMENTS, [], ['created_at' => 'DESC'], 10) : [];
    foreach ($recentDocuments as &$document) {
        $document['company_name'] = $customersById[$document['customer_id']]['company_name'] ?? '';
    }
    unset($document);

    $todaySchedules = $canViewSchedules ? count($fetchRows(API_TABLE_SCHEDULES, [
        ['field' => 'schedule_date', 'operator' => '=', 'value' => date('Y-m-d')],
        ['field' => 'status', 'operator' => '=', 'value' => 'scheduled'],
    ])) : 0;
    $upcomingSchedules = $canViewSchedules ? $fetchRows(API_TABLE_SCHEDULES, [
        ['field' => 'schedule_date', 'operator' => '>=', 'value' => date('Y-m-d')],
        ['field' => 'status', 'operator' => '=', 'value' => 'scheduled'],
    ], ['schedule_date' => 'ASC'], 10) : [];
    foreach ($upcomingSchedules as &$schedule) {
        $schedule['company_name'] = $customersById[$schedule['customer_id']]['company_name'] ?? '';
    }
    unset($schedule);
    
} catch (Exception $e) {
    showAlert('Lỗi tải dữ liệu: ' . $e->getMessage(), 'error');
    $totalCustomers = $totalDocuments = $todaySchedules = $totalWeight = 0;
    $recentDocuments = $upcomingSchedules = [];
}

include '../includes/header.php';
?>

<div class="page-header dashboard-shell-header">
    <div class="dashboard-shell-header__content">
        <div>
            <span class="dashboard-eyebrow"><i class="fas fa-chart-line me-2"></i>Tổng quan vận hành</span>
            <h1><i class="fas fa-home me-2"></i>Trang chủ</h1>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
</div>

<div class="dashboard-hero">
    <div class="dashboard-hero__content">
        <span class="dashboard-tag"><i class="fas fa-shield-check me-2"></i>Hệ thống đang hoạt động ổn định</span>
        <h2>Chào mừng quay lại, <?php echo sanitize($currentUser['full_name'] ?? 'Người dùng'); ?></h2>
        <p>Theo dõi nhanh tình trạng khách hàng, chứng từ và lịch thu gom trong ngày.</p>
        <div class="dashboard-badges">
            <span><i class="fas fa-user-check"></i> <?php echo formatNumber($totalCustomers); ?> khách hàng</span>
            <span><i class="fas fa-file-signature"></i> <?php echo formatNumber($totalDocuments); ?> chứng từ</span>
            <span><i class="fas fa-calendar-day"></i> <?php echo formatNumber($todaySchedules); ?> lịch hôm nay</span>
        </div>
    </div>
    <div class="dashboard-hero__stats">
        <div class="mini-stat mini-stat--primary">
            <span>Khối lượng tháng</span>
            <strong><?php echo formatNumber($totalWeight); ?> kg</strong>
        </div>
        <div class="mini-stat mini-stat--success">
            <span>Tuần này</span>
            <strong><?php echo formatNumber(max(0, $totalDocuments)); ?> mục</strong>
        </div>
        <div class="mini-stat mini-stat--warning">
            <span>Lịch sắp tới</span>
            <strong><?php echo formatNumber(count($upcomingSchedules)); ?> sự kiện</strong>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php if ($canViewCustomers): ?><div class="col-md-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-value"><?php echo formatNumber($totalCustomers); ?></div>
            <div class="stat-label">Khách hàng hoạt động</div>
        </div>
    </div><?php endif; ?>
    
    <?php if ($canViewDocuments): ?><div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-value"><?php echo formatNumber($totalDocuments); ?></div>
            <div class="stat-label">Chứng từ tháng này</div>
        </div>
    </div><?php endif; ?>
    
    <?php if ($canViewSchedules): ?><div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?php echo formatNumber($todaySchedules); ?></div>
            <div class="stat-label">Lịch thu gom hôm nay</div>
        </div>
    </div><?php endif; ?>
    
    <?php if ($canViewDocuments): ?><div class="col-md-3">
        <div class="stat-card stat-danger">
            <div class="stat-icon">
                <i class="fas fa-weight"></i>
            </div>
            <div class="stat-value"><?php echo formatNumber($totalWeight); ?></div>
            <div class="stat-label">Kg tháng này</div>
        </div>
    </div><?php endif; ?>
</div>

<!-- Quick Actions - Modern Cards -->
<div class="row g-3 mb-4">
    <?php if (checkPermission('documents', 'create')): ?>
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/pages/documents.php?action=create" class="quick-action-btn">
            <div class="quick-icon bg-primary"><i class="fas fa-plus-circle"></i></div>
            <div class="quick-title">Tạo chứng từ mới</div>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if (checkPermission('documents', 'print')): ?>
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/pages/print-batch.php" class="quick-action-btn">
            <div class="quick-icon bg-success"><i class="fas fa-print"></i></div>
            <div class="quick-title">In hàng loạt</div>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if (checkPermission('customers', 'create')): ?>
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/pages/customers.php?action=create" class="quick-action-btn">
            <div class="quick-icon bg-info"><i class="fas fa-user-plus"></i></div>
            <div class="quick-title">Thêm khách hàng</div>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if (checkPermission('reports', 'view')): ?>
    <div class="col-md-3">
        <a href="<?php echo BASE_URL; ?>/pages/reports.php" class="quick-action-btn">
            <div class="quick-icon bg-warning"><i class="fas fa-chart-line"></i></div>
            <div class="quick-title">Xem báo cáo</div>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($canViewDocuments || $canViewSchedules): ?><div class="row">
    <!-- Recent Documents -->
    <?php if ($canViewDocuments): ?>
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-alt me-2"></i>Chứng từ gần đây</span>
                <a href="<?php echo BASE_URL; ?>/pages/documents.php" class="btn btn-sm btn-outline-primary">
                    Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentDocuments)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="empty-text">Chưa có chứng từ nào</div>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Mã chứng từ</th>
                                <th>Khách hàng</th>
                                <th>Ngày thu gom</th>
                                <th>Khối lượng</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDocuments as $doc): ?>
                            <tr>
                                <td><strong><?php echo sanitize($doc['document_code']); ?></strong></td>
                                <td><?php echo sanitize($doc['company_name']); ?></td>
                                <td><?php echo formatDate($doc['collection_date']); ?></td>
                                <td><?php echo formatNumber($doc['total_weight']); ?> kg</td>
                                <td>
                                    <?php $statusMeta = getDocumentStatusMeta($doc['status'] ?? 'draft'); ?>
                                    <span class="badge bg-<?php echo $statusMeta['color']; ?>">
                                        <?php echo $statusMeta['label']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Upcoming Schedules -->
    <?php if ($canViewSchedules): ?>
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt me-2"></i>Lịch thu gom sắp tới</span>
                <a href="<?php echo BASE_URL; ?>/pages/schedules.php" class="btn btn-sm btn-outline-primary">
                    Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingSchedules)): ?>
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                    <div class="empty-text">Không có lịch sắp tới</div>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($upcomingSchedules as $schedule): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo sanitize($schedule['company_name']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo formatDate($schedule['schedule_date']); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary">Đã lên lịch</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div><?php endif; ?>

<?php include '../includes/footer.php'; ?>
