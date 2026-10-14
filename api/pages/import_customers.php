<?php
require_once '../includes/config.php';
requireLogin();
if (!checkPermission('customers', 'create') && !checkPermission('customers', 'edit')) {
    die('Không có quyền nhập khách hàng.');
}

function importHeaderKey($value)
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim((string) $value));
    return strtolower(preg_replace('/[^a-z0-9]/', '', $value));
}

function importColumnIndex($cellReference)
{
    if (!preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
        return null;
    }
    $index = 0;
    foreach (str_split(strtoupper($matches[1])) as $letter) {
        $index = $index * 26 + ord($letter) - ord('A') + 1;
    }
    return $index - 1;
}

function importReadXml(PharData $archive, $path)
{
    if (!isset($archive[$path])) {
        return null;
    }
    $document = new DOMDocument();
    if (!$document->loadXML($archive[$path]->getContent(), LIBXML_NONET | LIBXML_NOBLANKS)) {
        throw new RuntimeException('File Excel có cấu trúc XML không hợp lệ.');
    }
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    return $xpath;
}

function importReadWorkbook($path)
{
    $archive = new PharData($path);
    $sharedStrings = [];
    $sharedXpath = importReadXml($archive, 'xl/sharedStrings.xml');
    if ($sharedXpath) {
        foreach ($sharedXpath->query('//x:si') as $stringNode) {
            $parts = [];
            foreach ($sharedXpath->query('.//x:t', $stringNode) as $textNode) {
                $parts[] = $textNode->textContent;
            }
            $sharedStrings[] = implode('', $parts);
        }
    }

    $worksheets = [];
    for ($sheetNumber = 1; $sheetNumber <= 50; $sheetNumber++) {
        $sheetXpath = importReadXml($archive, 'xl/worksheets/sheet' . $sheetNumber . '.xml');
        if (!$sheetXpath) {
            continue;
        }
        $rows = [];
        foreach ($sheetXpath->query('//x:sheetData/x:row') as $rowNode) {
            $row = [];
            foreach ($sheetXpath->query('x:c', $rowNode) as $cellNode) {
                $columnIndex = importColumnIndex($cellNode->getAttribute('r'));
                if ($columnIndex === null) {
                    continue;
                }
                $type = $cellNode->getAttribute('t');
                if ($type === 'inlineStr') {
                    $valueNodes = $sheetXpath->query('.//x:is//x:t', $cellNode);
                    $value = '';
                    foreach ($valueNodes as $valueNode) {
                        $value .= $valueNode->textContent;
                    }
                } else {
                    $valueNode = $sheetXpath->query('x:v', $cellNode)->item(0);
                    $value = $valueNode ? $valueNode->textContent : '';
                    if ($type === 's' && ctype_digit($value)) {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }
                $row[$columnIndex] = trim($value);
            }
            if ($row) {
                ksort($row);
                $rows[] = $row;
            }
        }
        if ($rows) {
            $worksheets[] = $rows;
        }
    }
    return $worksheets;
}

function importMapHeaders(array $row)
{
    $aliases = [
        'company_name' => ['tencongty', 'tenkhachhang', 'tendonvi', 'company', 'companyname', 'fullname'],
        'address' => ['diachi', 'address'],
        'phone' => ['dienthoai', 'sodienthoai', 'phone', 'mobile'],
        'tax_code' => ['mst', 'masothue', 'taxcode', 'taxid'],
    ];
    $columns = [];
    foreach ($row as $index => $value) {
        $key = importHeaderKey($value);
        foreach ($aliases as $field => $fieldAliases) {
            if (in_array($key, $fieldAliases, true)) {
                $columns[$field] = $index;
                break;
            }
        }
    }
    return $columns;
}

function importNameKey($value)
{
    return preg_replace('/\s+/', ' ', importHeaderKey($value));
}

$error = '';
$preview = $_SESSION['customer_import_preview'] ?? null;
$previewToken = $_SESSION['customer_import_preview_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    try {
        $file = $_FILES['excel_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Chọn một file Excel .xlsx hợp lệ để tải lên.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new RuntimeException('Dung lượng file tối đa là 10 MB.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Chỉ hỗ trợ file Excel .xlsx.');
        }

        $workbookPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'customer-import-' . bin2hex(random_bytes(12)) . '.xlsx';
        if (!copy($file['tmp_name'], $workbookPath)) {
            throw new RuntimeException('Không thể lưu file Excel tạm thời để đọc.');
        }
        try {
            $worksheets = importReadWorkbook($workbookPath);
        } finally {
            @unlink($workbookPath);
        }
        $rows = null;
        $headerIndex = null;
        $columns = [];
        $scheduleWorkbook = false;
        foreach ($worksheets as $sheetRows) {
            foreach (array_slice($sheetRows, 0, 50, true) as $index => $row) {
                $candidate = importMapHeaders($row);
                if (isset($candidate['company_name'])) {
                    $headerKeys = array_map('importHeaderKey', array_values($row));
                    $hasScheduleColumns = count(array_intersect($headerKeys, ['thanhphan', 'thanhphanchatthai', 'thu', 'tuan'])) >= 2;
                    if ($hasScheduleColumns) {
                        $scheduleWorkbook = true;
                        continue;
                    }
                    $rows = $sheetRows;
                    $headerIndex = $index;
                    $columns = $candidate;
                    break 2;
                }
            }
        }
        if ($rows === null || $headerIndex === null) {
            if ($scheduleWorkbook) {
                throw new RuntimeException('File này là lịch thu gom, không phải danh sách khách hàng. Hãy dùng file danh sách khách hàng riêng có tên công ty, địa chỉ, điện thoại và MST.');
            }
            throw new RuntimeException('Không tìm thấy cột tên công ty/khách hàng trong 50 dòng đầu.');
        }

        $previewRows = [];
        $seenNames = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $companyName = trim((string) ($row[$columns['company_name']] ?? ''));
            if ($companyName === '') {
                continue;
            }
            $nameKey = importNameKey($companyName);
            if (isset($seenNames[$nameKey])) {
                throw new RuntimeException('File có tên khách hàng trùng lặp: ' . $companyName . '. Hãy xử lý trùng trước khi nhập.');
            }
            $seenNames[$nameKey] = true;
            $record = ['company_name' => $companyName];
            foreach (['address', 'phone', 'tax_code'] as $field) {
                if (isset($columns[$field])) {
                    $record[$field] = trim((string) ($row[$columns[$field]] ?? ''));
                }
            }
            $previewRows[] = $record;
            if (count($previewRows) > 2000) {
                throw new RuntimeException('File vượt quá giới hạn 2.000 khách hàng mỗi lần nhập.');
            }
        }
        if (!$previewRows) {
            throw new RuntimeException('Không tìm thấy dòng khách hàng nào bên dưới hàng tiêu đề.');
        }

        $_SESSION['customer_import_preview'] = $previewRows;
        $_SESSION['customer_import_preview_token'] = bin2hex(random_bytes(24));
        $preview = $previewRows;
        $previewToken = $_SESSION['customer_import_preview_token'];
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    try {
        $sessionToken = $_SESSION['customer_import_preview_token'] ?? '';
        if ($sessionToken === '' || !hash_equals($sessionToken, (string) ($_POST['token'] ?? ''))) {
            throw new RuntimeException('Phiên xem trước không hợp lệ hoặc đã hết hạn. Hãy tải file lên lại.');
        }
        $previewRows = $_SESSION['customer_import_preview'] ?? [];
        if (!$previewRows) {
            throw new RuntimeException('Không có dữ liệu chờ nhập.');
        }

        $customerResult = krudApiList(API_TABLE_CUSTOMERS, [], [], 1, 5000);
        if (!($customerResult['success'] ?? false)) {
            throw new RuntimeException($customerResult['error'] ?? 'Không thể tải danh sách khách hàng.');
        }
        $customersByName = [];
        foreach (($customerResult['data'] ?? []) as $customer) {
            $customersByName[importNameKey(krudCustomerName($customer))][] = $customer;
        }
        $taxResult = krudApiList(API_TABLE_TAX_INFO, [], [], 1, 5000);
        if (!($taxResult['success'] ?? false)) {
            throw new RuntimeException($taxResult['error'] ?? 'Không thể tải danh mục mã số thuế.');
        }
        $taxCodes = [];
        foreach (($taxResult['data'] ?? []) as $taxInfo) {
            $taxCodes[(string) ($taxInfo['tax_code'] ?? '')] = true;
        }

        $created = 0;
        $updated = 0;
        $failed = [];
        foreach ($previewRows as $record) {
            try {
                $nameKey = importNameKey($record['company_name']);
                $matches = $customersByName[$nameKey] ?? [];
                if (count($matches) > 1) {
                    throw new RuntimeException('Có nhiều khách hàng cùng tên, không thể tự ghép.');
                }
                $existing = $matches[0] ?? null;
                if ($existing && !checkPermission('customers', 'edit')) {
                    throw new RuntimeException('Tài khoản không có quyền cập nhật khách hàng đã tồn tại.');
                }
                if (!$existing && !checkPermission('customers', 'create')) {
                    throw new RuntimeException('Tài khoản không có quyền tạo khách hàng mới.');
                }

                $data = ['full_name' => $record['company_name']];
                foreach (['address', 'phone', 'tax_code'] as $field) {
                    if (!empty($record[$field])) {
                        $data[$field] = $record[$field];
                    }
                }
                if ($existing) {
                    $saveResult = krudApi('update', API_TABLE_CUSTOMERS, $data, $existing['id']);
                    $customerId = $existing['id'];
                } else {
                    $data['customer_code'] = 'KH' . date('ymd') . random_int(1000, 9999);
                    $data['status'] = 'active';
                    $saveResult = krudApi('insert', API_TABLE_CUSTOMERS, $data);
                    $customerId = $saveResult['data']['id'] ?? null;
                }
                if (!($saveResult['success'] ?? false) || !$customerId) {
                    throw new RuntimeException($saveResult['error'] ?? 'Không thể lưu khách hàng.');
                }
                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                    $customersByName[$nameKey] = [['id' => $customerId, 'full_name' => $record['company_name']]];
                }

                $taxCode = (string) ($record['tax_code'] ?? '');
                if ($taxCode !== '' && !isset($taxCodes[$taxCode])) {
                    $taxData = [
                        'tax_code' => $taxCode,
                        'customer_id' => (int) $customerId,
                        'company_name_official' => $record['company_name'],
                    ];
                    if (!empty($record['address'])) {
                        $taxData['address_official'] = $record['address'];
                    }
                    $taxInsert = krudApi('insert', API_TABLE_TAX_INFO, $taxData);
                    if (!($taxInsert['success'] ?? false)) {
                        throw new RuntimeException($taxInsert['error'] ?? 'Không thể thêm MST vào danh mục.');
                    }
                    $taxCodes[$taxCode] = true;
                }
            } catch (Throwable $exception) {
                $failed[] = $record['company_name'] . ': ' . $exception->getMessage();
            }
        }

        unset($_SESSION['customer_import_preview'], $_SESSION['customer_import_preview_token']);
        $message = 'Import Excel xong: ' . $created . ' khách mới, ' . $updated . ' khách cập nhật.';
        if ($failed) {
            $message .= ' Lỗi ' . count($failed) . ' dòng: ' . implode('; ', array_slice($failed, 0, 3));
            if (count($failed) > 3) {
                $message .= '; ...';
            }
        }
        showAlert($message, $failed ? 'warning' : 'success');
        redirect(BASE_URL . '/pages/customers.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-import me-2"></i>Nhập khách hàng từ Excel</h1>
</div>

<?php if ($error !== ''): ?>
<div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="preview">
            <label class="form-label" for="excelFile">File Excel (.xlsx), tối đa 10 MB</label>
            <div class="d-flex flex-wrap gap-2">
                <input class="form-control" type="file" id="excelFile" name="excel_file" accept=".xlsx" required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-eye me-2"></i>Xem trước dữ liệu</button>
            </div>
            <div class="form-text">Cần có cột tên công ty/khách hàng. Có thể kèm địa chỉ, điện thoại và MST. File lịch thu gom/tuần/thành phần chất thải không phải danh sách khách hàng; không tải file đó tại đây. Định dạng cột MST dạng văn bản để giữ số 0 đầu.</div>
        </form>
    </div>
</div>

<?php if ($preview): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Xem trước <?php echo count($preview); ?> khách hàng</strong>
        <form method="POST">
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="token" value="<?php echo sanitize($previewToken); ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-file-import me-2"></i>Xác nhận nhập</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Tên công ty</th><th>Địa chỉ</th><th>Điện thoại</th><th>MST</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($preview, 0, 100) as $record): ?>
                <tr>
                    <td><?php echo sanitize($record['company_name']); ?></td>
                    <td><?php echo sanitize($record['address'] ?? ''); ?></td>
                    <td><?php echo sanitize($record['phone'] ?? ''); ?></td>
                    <td><?php echo sanitize($record['tax_code'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($preview) > 100): ?><div class="p-3 text-muted">Đang hiển thị 100 dòng đầu; xác nhận sẽ nhập đủ <?php echo count($preview); ?> dòng.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>