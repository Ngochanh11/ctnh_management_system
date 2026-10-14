<?php
/**
 * CTNH Management System Configuration
 * Version: 2.0 - Hybrid Mode (Local + Cloud Sync)
 */

// ============================================
// All application data is stored through the company KRUD API.
define('DB_MODE', 'krud');

// ============================================
// KRUD API Configuration (Cloud)
// ============================================
define('KRUD_API_URL', 'https://kio.dvqt.vn/');
define('KRUD_JS_URL', 'https://kio.dvqt.vn/krud.js');
define('LIST_JS_URL', 'https://kio.dvqt.vn/list.js');
define('KRUD_TENANT_ORIGIN', '');

// ============================================
// Application settings
// ============================================
define('APP_NAME', 'Hệ thống In Chứng Từ Chất Thải');
define('APP_VERSION', '2.0');
define('APP_ROOT_URL', 'http://localhost/ctnh_management_system');
define('BASE_URL', APP_ROOT_URL . '/api');
define('SITE_NAME', 'CTNH Management System');
define('TIMEZONE', 'Asia/Ho_Chi_Minh');

// ============================================
// Database Tables
// ============================================
define('TABLE_PREFIX', 'ctnh_');
define('TABLE_USERS', TABLE_PREFIX . 'users');
define('TABLE_CUSTOMERS', TABLE_PREFIX . 'customers');
define('TABLE_WASTE_TYPES', TABLE_PREFIX . 'waste_types');
define('TABLE_DOCUMENTS', TABLE_PREFIX . 'documents');
define('TABLE_DOCUMENT_ITEMS', TABLE_PREFIX . 'document_items');
define('TABLE_SCHEDULES', TABLE_PREFIX . 'schedules');
define('TABLE_CUSTOMER_WASTE', TABLE_PREFIX . 'customer_waste');

// Tables used by the main application on the company KRUD API.
define('API_TABLE_USERS', 'users');
define('API_TABLE_CUSTOMERS', 'customers');
define('API_TABLE_WASTE_TYPES', 'waste_types');
define('API_TABLE_DOCUMENTS', 'collection_documents');
define('API_TABLE_DOCUMENT_ITEMS', 'document_items');
define('API_TABLE_SCHEDULES', 'collection_schedules');
define('API_TABLE_SETTINGS', 'system_settings');
define('API_TABLE_PRINT_HISTORY', 'print_history');
define('API_TABLE_CUSTOMER_PRINT_HISTORY', 'customer_print_history');
define('API_TABLE_CUSTOMER_WASTES', 'customer_wastes');
define('API_TABLE_TAX_INFO', 'tax_info');

require_once __DIR__ . '/krud_client.php';

// ============================================
// Session settings
// ============================================
define('SESSION_LIFETIME', 3600);
define('SESSION_TIMEOUT', 1800);

// ============================================
// Other settings
// ============================================
define('DEFAULT_ROWS_PER_PAGE', 10);
define('MAX_ROWS_PER_PAGE', 15);
define('TAX_API_ENABLED', true);
define('TAX_API_URL', 'https://api.vietqr.io/v2/business/');
define('ITEMS_PER_PAGE', 20);

date_default_timezone_set(TIMEZONE);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function checkPermission($module, $action = 'view') {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    
    if (isset($_SESSION['permissions'])) {
        $permissions = is_string($_SESSION['permissions']) 
            ? json_decode($_SESSION['permissions'], true) 
            : $_SESSION['permissions'];
        
        return permissionValueEnabled($permissions, $module, $action);
    }
    
    return false;
}

function permissionValueEnabled($permissions, $module, $action) {
    if (!is_array($permissions) || !isset($permissions[$module]) || !is_array($permissions[$module])) {
        return false;
    }
    if (array_key_exists($action, $permissions[$module])) {
        return filter_var($permissions[$module][$action], FILTER_VALIDATE_BOOLEAN);
    }
    return in_array($action, $permissions[$module], true);
}

function isPermissionCustomized($module) {
    if (!isLoggedIn() || isAdmin()) return false;

    $defaultPermissions = [
        'customers' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
        'wastes' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
        'documents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'print' => false],
        'reports' => ['view' => true, 'export' => false],
    ];
    if (!isset($defaultPermissions[$module])) return false;

    $permissions = $_SESSION['permissions'] ?? [];
    if (is_string($permissions)) {
        $permissions = json_decode($permissions, true);
    }
    if (!is_array($permissions) || !isset($permissions[$module]) || !is_array($permissions[$module])) {
        return false;
    }

    foreach ($defaultPermissions[$module] as $action => $defaultValue) {
        $value = permissionValueEnabled($permissions, $module, $action);
        if ($value !== $defaultValue) return true;
    }

    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $returnUrl = isset($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
        redirect(BASE_URL . '/index.php?return=' . $returnUrl);
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('❌ Không có quyền truy cập!');
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function showAlert($message, $type = 'success') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'email' => $_SESSION['email'] ?? ''
    ];
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00') return '';
    return date($format, strtotime($date));
}

function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, ',', '.');
}

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function getDocumentStatusMeta($status, $default = 'draft')
{
    $status = (string) ($status ?? $default);
    $map = [
        'draft' => ['label' => 'Nháp', 'color' => 'secondary'],
        'pending' => ['label' => 'Chờ duyệt', 'color' => 'warning'],
        'approved' => ['label' => 'Đã duyệt', 'color' => 'success'],
        'cancelled' => ['label' => 'Hủy', 'color' => 'danger'],
        'rejected' => ['label' => 'Từ chối', 'color' => 'danger'],
    ];
    return $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)) ?: 'Không xác định', 'color' => 'secondary'];
}

function getScheduleStatusMeta($status, $default = 'scheduled')
{
    $status = (string) ($status ?? $default);
    $map = [
        'scheduled' => ['label' => 'Đã lên lịch', 'color' => 'primary'],
        'completed' => ['label' => 'Hoàn thành', 'color' => 'success'],
        'cancelled' => ['label' => 'Đã hủy', 'color' => 'danger'],
        'rescheduled' => ['label' => 'Đã dời lịch', 'color' => 'warning'],
    ];
    return $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)) ?: 'Không xác định', 'color' => 'secondary'];
}

function getCustomerStatusMeta($status, $default = 'active')
{
    $status = (string) ($status ?? $default);
    $map = [
        'active' => ['label' => 'Hoạt động', 'color' => 'success'],
        'inactive' => ['label' => 'Ngừng', 'color' => 'secondary'],
        'pending' => ['label' => 'Chờ xử lý', 'color' => 'warning'],
    ];
    return $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)) ?: 'Không xác định', 'color' => 'secondary'];
}
