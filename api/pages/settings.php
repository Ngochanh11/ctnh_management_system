<?php
require_once '../includes/config.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8);
                $existing = krudApiList(API_TABLE_SETTINGS, [
                    ['field' => 'setting_key', 'operator' => '=', 'value' => $settingKey],
                ], [], 1, 1);
                $settingsData = ['setting_value' => $value];
                if (!empty($existing['data'][0]['id'])) {
                    $result = krudApi('update', API_TABLE_SETTINGS, $settingsData, $existing['data'][0]['id']);
                } else {
                    $settingsData['setting_key'] = $settingKey;
                    $result = krudApi('insert', API_TABLE_SETTINGS, $settingsData);
                }
                if (!($result['success'] ?? false)) {
                    throw new RuntimeException($result['error'] ?? 'Không thể cập nhật cài đặt.');
                }
            }
        }
        showAlert('Cập nhật cài đặt thành công!', 'success');
    } catch (Exception $e) {
        showAlert('Lỗi: ' . $e->getMessage(), 'error');
    }
}

$settingsResult = krudApiList(API_TABLE_SETTINGS, [], ['setting_key' => 'ASC'], 1, 1000);
$settings = [];
foreach (($settingsResult['data'] ?? []) as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'] ?? '';
}

include '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog me-2"></i>Cài đặt hệ thống</h1>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Thông tin công ty</h5></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tên công ty</label>
                    <input type="text" name="setting_company_name" class="form-control" value="<?php echo $settings['company_name'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="setting_company_phone" class="form-control" value="<?php echo $settings['company_phone'] ?? ''; ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="setting_company_address" class="form-control" value="<?php echo $settings['company_address'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="setting_company_email" class="form-control" value="<?php echo $settings['company_email'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số dòng chất thải trên mỗi trang in</label>
                    <input type="number" class="form-control" value="10" readonly>
                    <div class="form-text">Giới hạn cố định theo yêu cầu hợp đồng.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Lưu cài đặt</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
