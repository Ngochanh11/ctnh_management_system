<?php
if (!defined('API_TABLE_USERS')) {
    require_once __DIR__ . '/config.php';
}

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$canViewCustomers = checkPermission('customers', 'view');
$canViewWastes = checkPermission('wastes', 'view');
$canViewDocuments = checkPermission('documents', 'view');
$canPrintDocuments = checkPermission('documents', 'print');
$canViewReports = checkPermission('reports', 'view');
$showOperationsMenu = $canViewCustomers || $canViewWastes || $canViewDocuments || $canPrintDocuments;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_ROOT_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="logo">
            <i class="fas fa-recycle"></i>
            <span><?php echo APP_NAME; ?></span>
        </a>
        
        <div style="flex:1"></div>
        
        <?php if ($currentUser): ?>
        <div class="user-dropdown">
            <div class="user-info" data-bs-toggle="dropdown">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                </div>
                <div style="text-align:left">
                    <div style="font-weight:600;font-size:14px"><?php echo sanitize($currentUser['full_name']); ?></div>
                    <div style="font-size:12px;opacity:0.8"><?php echo $currentUser['role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên'; ?></div>
                </div>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/profile.php">
                    <i class="fas fa-user me-2"></i>Hồ sơ
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                </a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <?php if ($currentUser): ?>
    <aside class="sidebar">
        <ul class="nav-menu">
            <li class="nav-section">MENU CHÍNH</li>
            
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Trang chủ</span>
                </a>
            </li>
            
            <?php if ($canViewCustomers): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/customers.php" class="nav-link <?php echo $currentPage === 'customers' ? 'active' : ''; ?><?php echo isPermissionCustomized('customers') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('customers') ? 'Quyền khách hàng đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Khách hàng</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($canViewWastes): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/waste-types.php" class="nav-link <?php echo $currentPage === 'waste-types' ? 'active' : ''; ?><?php echo isPermissionCustomized('wastes') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('wastes') ? 'Quyền chất thải đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-trash-alt"></i>
                    <span>Danh mục chất thải</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($showOperationsMenu): ?>
            <li class="nav-section">NGHIỆP VỤ</li>
            <?php endif; ?>
            
            <?php if ($canViewDocuments): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/documents.php" class="nav-link <?php echo $currentPage === 'documents' ? 'active' : ''; ?><?php echo isPermissionCustomized('documents') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('documents') ? 'Quyền chứng từ đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Chứng từ</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($canPrintDocuments): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/print-batch.php" class="nav-link <?php echo $currentPage === 'print-batch' ? 'active' : ''; ?><?php echo isPermissionCustomized('documents') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('documents') ? 'Quyền chứng từ đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-print"></i>
                    <span>In hàng loạt</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/printer_guide.php" class="nav-link <?php echo $currentPage === 'printer_guide' ? 'active' : ''; ?><?php echo isPermissionCustomized('documents') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('documents') ? 'Quyền chứng từ đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-circle-question"></i>
                    <span>Hướng dẫn in</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($canViewCustomers): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/schedules.php" class="nav-link <?php echo $currentPage === 'schedules' ? 'active' : ''; ?><?php echo isPermissionCustomized('customers') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('customers') ? 'Quyền khách hàng đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Lịch thu gom</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($canViewReports): ?>
            <li class="nav-section">BÁO CÁO</li>
            
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/reports.php" class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?><?php echo isPermissionCustomized('reports') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('reports') ? 'Quyền báo cáo đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Thống kê</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/print-history.php" class="nav-link <?php echo $currentPage === 'print-history' ? 'active' : ''; ?><?php echo isPermissionCustomized('reports') ? ' permission-customized' : ''; ?>" title="<?php echo isPermissionCustomized('reports') ? 'Quyền báo cáo đã thay đổi so với mặc định' : ''; ?>">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Lịch sử in</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
            <li class="nav-section">HỆ THỐNG</li>
            
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/users.php" class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Người dùng</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/pages/settings.php" class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Cài đặt</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </aside>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php
        $alert = getAlert();
        if ($alert):
            $classes = ['success'=>'alert-success','error'=>'alert-danger','warning'=>'alert-warning','info'=>'alert-info'];
            $icons = ['success'=>'check-circle','error'=>'exclamation-circle','warning'=>'exclamation-triangle','info'=>'info-circle'];
        ?>
        <div class="alert <?php echo $classes[$alert['type']]; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $icons[$alert['type']]; ?> me-2"></i>
            <?php echo sanitize($alert['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
