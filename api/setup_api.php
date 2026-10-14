<?php
require_once __DIR__ . '/includes/config.php';

function seedDemoData()
{
    $customerRows = [
        [
            'customer_code' => 'KH240101',
            'company_name' => 'Công ty TNHH MTV Xanh Việt',
            'address' => 'Lô 12, KCN Tân Phú, Đồng Nai',
            'phone' => '0901234567',
            'email' => 'contact@xanhviet.vn',
            'tax_code' => '0312345678',
            'representative' => 'Nguyễn Văn A',
            'contract_date' => '2024-01-15',
            'contract_expiry_date' => '2027-01-15',
            'frequency' => '1 lần/tuần',
            'status' => 'active',
        ],
        [
            'customer_code' => 'KH240102',
            'company_name' => 'Công ty Cổ phần Trường An',
            'address' => 'Số 8, đường Lê Lợi, Quận 1, TP.HCM',
            'phone' => '0907654321',
            'email' => 'info@truongan.vn',
            'tax_code' => '0309876543',
            'representative' => 'Trần Thị B',
            'contract_date' => '2024-02-10',
            'contract_expiry_date' => '2027-02-10',
            'frequency' => '2 lần/tháng',
            'status' => 'active',
        ],
        [
            'customer_code' => 'KH240103',
            'company_name' => 'Nhà máy Sản xuất Thực phẩm Hoàng Gia',
            'address' => 'Khu công nghiệp Long Bình, Bình Dương',
            'phone' => '0912345678',
            'email' => 'admin@hoanggiafood.vn',
            'tax_code' => '3701234567',
            'representative' => 'Lê Quốc C',
            'contract_date' => '2024-03-20',
            'contract_expiry_date' => '2028-03-20',
            'frequency' => '1 lần/tháng',
            'status' => 'pending',
        ],
    ];

    $wasteRows = [
        ['waste_code' => '18 02 01', 'waste_name' => 'Chất hấp thu, vật liệu lọc, giẻ lau', 'category' => 'hazardous', 'unit' => 'kg', 'price_per_unit' => 125000, 'description' => 'Phế phẩm có nhiễm hóa chất'],
        ['waste_code' => '19 06 01', 'waste_name' => 'Pin, ắc quy chì thải', 'category' => 'hazardous', 'unit' => 'kg', 'price_per_unit' => 180000, 'description' => 'Acs quy và pin chì'],
        ['waste_code' => '08 02 04', 'waste_name' => 'Hộp mực in thải', 'category' => 'hazardous', 'unit' => 'kg', 'price_per_unit' => 98000, 'description' => 'Mực in và bao bì chứa mực'],
        ['waste_code' => '17 02 03', 'waste_name' => 'Dầu đông cơ, hợp số và bôi trơn tổng hợp thải', 'category' => 'hazardous', 'unit' => 'kg', 'price_per_unit' => 165000, 'description' => 'Dầu bôi trơn đã qua sử dụng'],
        ['waste_code' => '13 02 04', 'waste_name' => 'Dầu động cơ', 'category' => 'hazardous', 'unit' => 'kg', 'price_per_unit' => 155000, 'description' => 'Dầu động cơ thải'],
        ['waste_code' => '19 12 03', 'waste_name' => 'Than hoạt tính', 'category' => 'industrial', 'unit' => 'kg', 'price_per_unit' => 65000, 'description' => 'Bông lọc và than hoạt tính'],
    ];

    $listCustomers = krudApiList(API_TABLE_CUSTOMERS, [], ['id' => 'ASC'], 1, 50);
    if (!($listCustomers['success'] ?? false)) {
        return false;
    }
    if (($listCustomers['total'] ?? 0) === 0) {
        foreach ($customerRows as $customer) {
            krudApi('insert', API_TABLE_CUSTOMERS, krudCustomerData($customer));
        }
    }

    $listWaste = krudApiList(API_TABLE_WASTE_TYPES, [], ['id' => 'ASC'], 1, 50);
    if (!($listWaste['success'] ?? false)) {
        return false;
    }
    if (($listWaste['total'] ?? 0) === 0) {
        foreach ($wasteRows as $waste) {
            krudApi('insert', API_TABLE_WASTE_TYPES, $waste);
        }
    }

    $customerList = krudApiList(API_TABLE_CUSTOMERS, [], ['id' => 'ASC'], 1, 50);
    $wasteList = krudApiList(API_TABLE_WASTE_TYPES, [], ['id' => 'ASC'], 1, 50);
    $customerIds = array_values(array_filter(array_map('intval', array_column($customerList['data'] ?? [], 'id'))));
    $wasteIds = array_values(array_filter(array_map('intval', array_column($wasteList['data'] ?? [], 'id'))));

    if (empty($customerIds) || empty($wasteIds)) {
        return true;
    }

    $scheduleTable = krudApiList(API_TABLE_SCHEDULES, [], ['id' => 'ASC'], 1, 50);
    if (($scheduleTable['success'] ?? false) && ($scheduleTable['total'] ?? 0) === 0) {
        foreach ($customerIds as $index => $customerId) {
            $scheduleDate = date('Y-m-d', strtotime('+' . ($index + 2) . ' days'));
            krudApi('insert', API_TABLE_SCHEDULES, [
                'customer_id' => $customerId,
                'schedule_date' => $scheduleDate,
                'week_number' => (($index % 4) + 1),
                'month' => (int) date('n'),
                'year' => (int) date('Y'),
                'status' => 'scheduled',
                'notes' => 'Lịch thu gom dữ liệu mẫu',
            ]);
        }
    }

    $customerWasteTable = krudApiList(API_TABLE_CUSTOMER_WASTES, [], ['id' => 'ASC'], 1, 50);
    if (($customerWasteTable['success'] ?? false) && ($customerWasteTable['total'] ?? 0) === 0) {
        foreach ($customerIds as $index => $customerId) {
            $wasteId = $wasteIds[$index % count($wasteIds)];
            krudApi('insert', API_TABLE_CUSTOMER_WASTES, [
                'customer_id' => $customerId,
                'waste_type_id' => $wasteId,
                'estimated_quantity' => 250 + ($index * 75),
                'unit' => 'kg',
                'notes' => 'Dữ liệu mẫu',
            ]);
        }
    }

    $documentTable = krudApiList(API_TABLE_DOCUMENTS, [], ['id' => 'ASC'], 1, 50);
    if (($documentTable['success'] ?? false) && ($documentTable['total'] ?? 0) === 0) {
        $scheduleList = krudApiList(API_TABLE_SCHEDULES, [], ['id' => 'ASC'], 1, 50);
        $scheduleIds = array_values(array_filter(array_map('intval', array_column($scheduleList['data'] ?? [], 'id'))));
        foreach ($customerIds as $index => $customerId) {
            $scheduleId = $scheduleIds[$index] ?? null;
            $documentCode = 'CTNH-' . date('ymd') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $insertedDoc = krudApi('insert', API_TABLE_DOCUMENTS, [
                'document_code' => $documentCode,
                'schedule_id' => $scheduleId,
                'customer_id' => $customerId,
                'collection_date' => date('Y-m-d', strtotime('+' . ($index + 1) . ' days')),
                'document_type' => $index % 2 === 0 ? 'receipt' : 'delivery_note',
                'total_weight' => 120 + ($index * 40),
                'unit' => 'kg',
                'driver_name' => 'Nguyễn Văn ' . chr(65 + $index),
                'vehicle_number' => 'NG-' . (50 + $index),
                'customer_signature' => true,
                'company_signature' => true,
                'status' => 'approved',
                'notes' => 'Chứng từ mẫu do hệ thống tạo tự động',
                'created_by' => 1,
            ]);

            if (($insertedDoc['success'] ?? false) && !empty($insertedDoc['data']['id'])) {
                $documentId = (int) $insertedDoc['data']['id'];
                $wasteId = $wasteIds[$index % count($wasteIds)];
                krudApi('insert', API_TABLE_DOCUMENT_ITEMS, [
                    'document_id' => $documentId,
                    'waste_type_id' => $wasteId,
                    'quantity' => 100 + ($index * 25),
                    'unit' => 'kg',
                    'unit_price' => 150000 + ($index * 5000),
                    'total_price' => (100 + ($index * 25)) * (150000 + ($index * 5000)),
                    'notes' => 'Mục dữ liệu mẫu',
                ]);
            }
        }
    }

    return true;
}

$userCheck = krudApiList(API_TABLE_USERS, [], [], 1, 1);
$error = '';
$created = false;
$seededDemoData = false;

if (!($userCheck['success'] ?? false)) {
    $error = $userCheck['error'] ?? 'Không thể kết nối CSDL máy chủ.';
} elseif ((int) ($userCheck['total'] ?? 0) > 0) {
    $error = 'CSDL máy chủ đã có tài khoản. Hãy đăng nhập hoặc liên hệ quản trị viên.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $fullName === '' || strlen($password) < 8) {
        $error = 'Nhập đầy đủ thông tin và mật khẩu tối thiểu 8 ký tự.';
    } else {
        $permissions = [
            'customers' => ['view', 'create', 'edit', 'delete'],
            'wastes' => ['view', 'create', 'edit', 'delete'],
            'documents' => ['view', 'create', 'edit', 'delete', 'print'],
            'reports' => ['view', 'export'],
            'users' => ['view', 'create', 'edit', 'delete'],
        ];
        $result = krudApi('insert', API_TABLE_USERS, [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName,
            'email' => trim($_POST['email'] ?? ''),
            'role' => 'admin',
            'status' => 'active',
            'permissions' => json_encode($permissions),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result['success'] ?? false) {
            $created = true;
            $seededDemoData = seedDemoData();
        } else {
            $error = $result['error'] ?? 'Không thể tạo tài khoản quản trị.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Khởi tạo tài khoản quản trị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: #f1f5f6; color: #263b46; font-family: 'Segoe UI', sans-serif; }
        .setup-form { width: min(100%, 440px); padding: 2rem; border: 1px solid #dce6e7; border-radius: 12px; background: #fff; box-shadow: 0 18px 48px rgba(25, 48, 60, .1); }
        .setup-form h1 { margin: 0 0 .5rem; font-size: 1.35rem; }
        .setup-form > p { margin-bottom: 1.5rem; color: #71818b; font-size: .9rem; }
        .form-label { font-size: .88rem; font-weight: 600; }
        .form-control { min-height: 42px; border-color: #d7e1e7; }
        .btn-primary { min-height: 42px; background: #16796a; border-color: #16796a; }
    </style>
</head>
<body>
    <main class="setup-form">
        <h1>Khởi tạo tài khoản quản trị</h1>
        <p>Tài khoản sẽ được lưu trên KRUD API của công ty.</p>
        <?php if ($created): ?>
            <div class="alert alert-success">
                <?php if ($seededDemoData): ?>
                    Đã tạo tài khoản quản trị và dữ liệu mẫu cho các bảng chính trên máy chủ.
                <?php else: ?>
                    Đã tạo tài khoản quản trị trên máy chủ.
                <?php endif; ?>
            </div>
            <a class="btn btn-primary w-100" href="index.php">Đến trang đăng nhập</a>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php if ((int) ($userCheck['total'] ?? 0) > 0): ?>
                <a class="btn btn-primary w-100" href="index.php">Đến trang đăng nhập</a>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" for="username">Tên đăng nhập</label>
                    <input class="form-control" id="username" name="username" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="full_name">Họ tên</label>
                    <input class="form-control" id="full_name" name="full_name" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu (ít nhất 8 ký tự)</label>
                    <input class="form-control" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-100" type="submit">Tạo tài khoản trên máy chủ</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>