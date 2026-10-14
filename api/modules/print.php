<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('documents', 'print') or die('Không có quyền in chứng từ.');
checkPermission('documents', 'print') or die('Không có quyền in chứng từ.');

$requestedIds = isset($_GET['ids']) ? explode(',', (string) $_GET['ids']) : [$_GET['id'] ?? ''];
$documentIds = array_values(array_unique(array_filter(array_map(static function ($id) {
    return ctype_digit((string) $id) && (int) $id > 0 ? (int) $id : null;
}, $requestedIds))));
if (!$documentIds) die('Không tìm thấy chứng từ');

try {
    $documentResult = krudApiList(API_TABLE_DOCUMENTS, [], [], 1, 1000);
    $customerResult = krudApiList(API_TABLE_CUSTOMERS, [], [], 1, 1000);
    $taxInfoResult = krudApiList(API_TABLE_TAX_INFO, [], [], 1, 1000);
    $itemResult = krudApiList(API_TABLE_DOCUMENT_ITEMS, [], ['id' => 'ASC'], 1, 10000);
    $wasteResult = krudApiList(API_TABLE_WASTE_TYPES, [], [], 1, 1000);

    $requestedIdSet = array_fill_keys($documentIds, true);
    $documentsById = [];
    foreach (($documentResult['data'] ?? []) as $document) {
        if (isset($requestedIdSet[$document['id']])) {
            $documentsById[$document['id']] = $document;
        }
    }
    $customersById = [];
    foreach (($customerResult['data'] ?? []) as $customer) {
        $customersById[$customer['id']] = krudCustomerRecord($customer);
    }
    $taxInfoByCustomerId = [];
    foreach (($taxInfoResult['data'] ?? []) as $taxInfo) {
        $taxInfoByCustomerId[$taxInfo['customer_id']] = $taxInfo;
    }
    $itemsByDocumentId = [];
    foreach (($itemResult['data'] ?? []) as $item) {
        if (isset($requestedIdSet[$item['document_id']])) {
            $itemsByDocumentId[$item['document_id']][] = $item;
        }
    }
    $wastesById = [];
    foreach (($wasteResult['data'] ?? []) as $waste) {
        $wastesById[$waste['id']] = $waste;
    }

    $printDocuments = [];
    $documentPageCounts = [];
    foreach ($documentIds as $id) {
        $doc = $documentsById[$id] ?? null;
        if (!$doc) continue;
        $customer = $customersById[$doc['customer_id']] ?? [];
        $taxInfo = $taxInfoByCustomerId[$doc['customer_id']] ?? [];
        foreach (['company_name', 'address', 'tax_code', 'representative', 'phone'] as $field) {
            $doc[$field] = $customer[$field] ?? '';
        }
        foreach (['tax_code' => 'tax_code', 'address' => 'address_official', 'representative' => 'representative'] as $field => $taxField) {
            if ($doc[$field] === '') {
                $doc[$field] = $taxInfo[$taxField] ?? '';
            }
        }

        $items = $itemsByDocumentId[$id] ?? [];
        foreach ($items as &$item) {
            $waste = $wastesById[$item['waste_type_id']] ?? [];
            $item['waste_code'] = $waste['waste_code'] ?? '';
            $item['waste_name'] = $waste['waste_name'] ?? '';
            $item['unit'] = $item['unit'] ?? ($waste['unit'] ?? 'kg');
            $item['state'] = normalizeWasteStateValue($item['state'] ?? '')
                ?: normalizeWasteStateValue($waste['state'] ?? '')
                ?: inferWasteStateFromMetadata($item['waste_name'] ?? $waste['waste_name'] ?? ($waste['description'] ?? ''));
            $item['treatment_method'] = $item['treatment_method'] ?? ($waste['treatment_method'] ?? '');
        }
        unset($item);

        $pages = array_chunk($items, DEFAULT_ROWS_PER_PAGE) ?: [[]];
        $documentPageCounts[(string) $id] = count($pages);
        $printDocuments[] = [
            'doc' => $doc,
            'pages' => $pages,
            'documentYear' => !empty($doc['collection_date']) ? date('Y', strtotime($doc['collection_date'])) : date('Y'),
        ];
    }
    if (!$printDocuments) die('Không tìm thấy chứng từ');
    
} catch (Exception $e) {
    die('Lỗi: ' . $e->getMessage());
}

function normalizeWasteStateValue($value)
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/\s+/', '', $normalized);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
    $compact = $transliterated !== false ? strtolower((string) $transliterated) : $normalized;
    $compact = preg_replace('/[^a-z]/', '', $compact);

    if ($compact === 'solid' || strpos($compact, 'ran') !== false) {
        return 'solid';
    }
    if ($compact === 'liquid' || strpos($compact, 'long') !== false) {
        return 'liquid';
    }
    if ($compact === 'sludge' || strpos($compact, 'bun') !== false) {
        return 'sludge';
    }

    return '';
}

function inferWasteStateFromMetadata($value)
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return 'solid';
    }

    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $compact = $transliterated !== false ? strtolower((string) $transliterated) : $text;
    $compact = preg_replace('/\s+/', '', $compact);
    $compact = preg_replace('/[^a-z]/', '', $compact);

    $sludgeKeywords = ['bun', 'can', 'ba', 'tro', 'loc', 'khoi'];
    $liquidKeywords = ['dau', 'nuoc', 'dich', 'long', 'hoa', 'sua', 'xoa', 'muoi'];

    foreach ($sludgeKeywords as $keyword) {
        if (strpos($compact, $keyword) !== false) {
            return 'sludge';
        }
    }

    foreach ($liquidKeywords as $keyword) {
        if (strpos($compact, $keyword) !== false) {
            return 'liquid';
        }
    }

    return 'solid';
}

if (empty($_SESSION['document_print_csrf'])) {
    $_SESSION['document_print_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['log_print'])) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!hash_equals($_SESSION['document_print_csrf'], (string) ($payload['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Phiên in hết hạn.']);
        exit;
    }
    foreach ($documentPageCounts as $id => $pageCount) {
        $result = krudApi('insert', API_TABLE_PRINT_HISTORY, [
            'document_id' => (int) $id,
            'user_id' => (int) $_SESSION['user_id'],
            'print_date' => date('Y-m-d H:i:s'),
            'page_count' => $pageCount,
        ]);
        if (!($result['success'] ?? false)) {
            http_response_code(502);
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Không thể ghi lịch sử in.']);
            exit;
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

$itemsPerPage = DEFAULT_ROWS_PER_PAGE;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In chứng từ<?php echo count($printDocuments) === 1 ? ' - ' . sanitize($printDocuments[0]['doc']['document_code'] ?? '') : ' hàng loạt'; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 10mm;
            margin: 0 auto;
            background: white;
            page-break-after: always;
            position: relative;
        }
        
        .page:last-of-type { page-break-after: avoid; }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0;
        }
        
        .header .doc-code {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }
        
        /* Info Section */
        .info-section {
            margin: 15px 0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 6px;
        }
        
        .info-label {
            width: 150px;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px dotted #333;
            padding-left: 5px;
        }
        
        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        
        .items-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table .col-stt { width: 40px; text-align: center; }
        .items-table .col-code { width: 80px; }
        .items-table .col-name { width: auto; }
        .items-table .col-qty { width: 80px; text-align: right; }
        .items-table .col-unit { width: 60px; text-align: center; }
        .items-table .col-price { width: 100px; text-align: right; }
        
        /* Summary */
        .summary {
            margin: 15px 0;
            font-weight: bold;
        }
        
        .summary-row {
            display: flex;
            margin: 8px 0;
        }
        
        .summary-label {
            width: 150px;
        }
        
        .summary-value {
            flex: 1;
            text-align: right;
            font-size: 15px;
        }
        
        /* Signatures */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            text-align: center;
        }
        
        .signature-box {
            width: 45%;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .signature-subtitle {
            font-style: italic;
            font-size: 11px;
            margin-bottom: 60px;
        }
        
        .signature-name {
            font-weight: bold;
            margin-top: 5px;
        }
        
        /* Page number */
        .page-number {
            position: absolute;
            bottom: 10mm;
            right: 10mm;
            font-size: 11px;
            font-style: italic;
        }
        
        /* Print styles */
        @media print {
            body { margin: 0; }
            .page { 
                margin: 0; 
                padding: 10mm;
                box-shadow: none;
            }
            .no-print { display: none; }
        }
        
        /* Print button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #1e40af;
        }
        
        @media print {
            .print-button { display: none; }
        }

        .page {
            height: 297mm;
            min-height: 0;
            padding: 7mm 8mm 7mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font: 8.2pt/1.22 Arial, Helvetica, sans-serif;
        }
        .form-border { flex: 1; min-height: 0; border: 1px solid #444; display: flex; flex-direction: column; }
        .topline { display: grid; grid-template-columns: 1fr 1.5fr; align-items: center; min-height: 18mm; padding: 2mm 3mm; border-bottom: 1px solid #555; font-weight: 700; }
        .topline .city { text-transform: uppercase; line-height: 1.2; }
        .topline .title { text-align: center; text-transform: uppercase; font-size: 10.5pt; line-height: 1.18; }
        .title-block { text-align: center; }
        .serial { min-height: 0; padding: 1mm 0 0; text-align: center; font-size: 8pt; font-weight: 400; }
        .section { padding: 1.7mm 2.5mm; border-bottom: 1px solid #777; }
        .party { font-size: 7.8pt; line-height: 1.24; }
        .party:nth-of-type(1) { min-height: 25mm; }
        .party:nth-of-type(2) { min-height: 23mm; }
        .party:nth-of-type(3) { min-height: 27mm; }
        .party p { margin: 1mm 0; overflow-wrap: anywhere; }
        .party .line { min-height: 5mm; padding-left: 4mm; }
        .provider-heading { display: grid; grid-template-columns: minmax(0, 1fr) 54mm; gap: 2mm; align-items: start; font-size: 7.2pt; line-height: 1.2; }
        .provider-heading strong { overflow-wrap: anywhere; }
        .provider-license { text-align: right; font-size: 7pt; font-weight: 400; }
        .dotted { display: inline-block; min-width: 20mm; min-height: 3mm; border-bottom: 1px dotted #555; }
        .customer-value { display: inline-block; min-width: 55%; border-bottom: 1px dotted #555; font-weight: 600; }
        .customer-address { min-width: 68%; }
        .owner-heading { display: grid; grid-template-columns: minmax(0, 1fr) 62mm; gap: 2mm; align-items: start; }
        .owner-license { font-size: 7pt; line-height: 1.2; }
        .owner-license .dotted { min-width: 35mm; }
        .table-title { margin-bottom: 1.2mm; font-size: 7.8pt; font-weight: 700; line-height: 1.22; }
        .items-table { width: 100%; margin: 0; border-collapse: collapse; table-layout: fixed; }
        .items-table th, .items-table td { padding: 1mm .7mm; border: 1px solid #555; overflow-wrap: anywhere; }
        .items-table th { height: 7mm; background: #f1f1f1; font-size: 7.2pt; line-height: 1.18; text-align: center; font-weight: 700; }
        .items-table td { height: 6.5mm; font-size: 7.5pt; line-height: 1.2; }
        .items-table .col-stt { width: 7%; text-align: center; }
        .items-table .col-name { width: 29%; }
        .items-table .col-state { width: 6%; text-align: center; }
        .items-table .col-code { width: 13%; text-align: center; }
        .items-table .col-qty { width: 12%; text-align: right; }
        .items-table .col-method { width: 21%; text-align: center; }
        .check { font-size: 10pt; font-weight: 700; }
        .notes { padding: 1.6mm 2.5mm; border-bottom: 1px solid #777; font-size: 6pt; line-height: 1.22; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #777; }
        .meta > div { min-height: 10mm; padding: 1.2mm 2.5mm; }
        .meta > div + div { border-left: 1px solid #777; }
        .acceptance { padding-top: 1.5mm; padding-bottom: 1.5mm; }
        .acceptance { font-size: 7.5pt; line-height: 1.22; }
        .acceptance p { margin: .7mm 0; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; min-height: 38mm; margin-top: 0; border-bottom: 1px solid #777; }
        .signature-box { width: auto; padding: 1.5mm; border-right: 1px solid #777; text-align: center; }
        .signature-box:last-child { border-right: 0; }
        .signature-title { margin-bottom: 1mm; font-size: 7.6pt; font-weight: 700; }
        .signature-subtitle { min-height: 7mm; margin-bottom: 0; font-size: 6.4pt; line-height: 1.2; }
        .signature-space { height: 17mm; }
        .signature-date { margin: 1mm 0 2mm; font-size: 6.8pt; }
        .signature-name { font-size: 7.2pt; font-weight: 600; }
        .bottom-note { padding: 1.5mm 2.5mm; font-size: 6.8pt; line-height: 1.2; }
        .page-number { bottom: 2mm; right: 2mm; font-size: 7pt; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { margin: 0; }
            .page { width: 210mm; height: 297mm; min-height: 0; padding: 7mm 8mm 7mm; margin: 0; page-break-after: always; break-after: page; }
            .page:last-of-type { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="printDocuments()">
        🖨️ In <?php echo count($printDocuments) > 1 ? 'các chứng từ' : 'chứng từ'; ?>
    </button>
    <?php foreach ($printDocuments as $printDocument):
        $doc = $printDocument['doc'];
        $pages = $printDocument['pages'];
        $documentYear = $printDocument['documentYear'];
    ?>
    <?php foreach ($pages as $pageIndex => $pageItems):
        $pageNum = $pageIndex + 1;
        $totalPages = count($pages);
        $startIndex = $pageIndex * $itemsPerPage;
    ?>
    <article class="page">
        <div class="form-border">
            <header class="topline">
                <div class="city">Thành phố<br>Hồ Chí Minh</div>
                <div class="title-block">
                    <div class="title">Chứng từ chất thải nguy hại</div>
                    <div class="serial">Số: <strong><?php echo sanitize($doc['document_code'] ?? ''); ?>/<?php echo sanitize($documentYear); ?></strong></div>
                </div>
            </header>

            <section class="section party">
                <div class="provider-heading">
                    <strong>1. Chủ CS DV XL CTNH 1: CÔNG TY TNHH MỘT THÀNH VIÊN LIÊN HIỆP KHOA HỌC - CÔNG NGHỆ - MÔI TRƯỜNG BIWASE</strong>
                    <span class="provider-license">Số giấy phép môi trường:<br>292/GPMT-BTNMT<br>ĐT: (0274) 654 6528/529</span>
                </div>
                <p class="line">Địa chỉ văn phòng: Số 39, Tổ 6, Khu phố 18, Phường Chánh Phú Hòa, Thành phố Hồ Chí Minh, Việt Nam</p>
                <p class="line">Địa chỉ cơ sở: Số 39, Tổ 6, Khu phố 18, Phường Chánh Phú Hòa, Thành phố Hồ Chí Minh, Việt Nam</p>
            </section>

            <section class="section party">
                <p><strong>2. Chủ xử lý CTNH 2:</strong> <span class="dotted"></span> Giấy phép môi trường/Mã số QLCTNH: <span class="dotted"></span></p>
                <p class="line">Địa chỉ văn phòng: <span class="dotted"></span> ĐT: <span class="dotted"></span></p>
                <p class="line">Địa chỉ cơ sở: <span class="dotted"></span> ĐT: <span class="dotted"></span></p>
            </section>

            <section class="section party">
                <div class="owner-heading">
                    <div><strong>3. Chủ nguồn thải:</strong> <span class="customer-value"><?php echo sanitize($doc['company_name'] ?? ''); ?></span></div>
                    <div class="owner-license">Giấy phép môi trường/Mã số QLCTNH (nếu có):<br><span class="dotted"></span></div>
                </div>
                <p class="line">Mã số thuế: <span class="dotted"><?php echo sanitize($doc['tax_code'] ?? ''); ?></span></p>
                <p class="line">Địa chỉ văn phòng: <span class="customer-value customer-address"><?php echo sanitize($doc['address'] ?? ''); ?></span> ĐT: <span class="dotted"><?php echo sanitize($doc['phone'] ?? ''); ?></span></p>
                <p class="line">Địa chỉ cơ sở: <span class="customer-value customer-address"><?php echo sanitize($doc['address'] ?? ''); ?></span> ĐT: <span class="dotted"><?php echo sanitize($doc['phone'] ?? ''); ?></span></p>
            </section>

            <section class="section">
                <div class="table-title">4. Kê khai CTNH chuyển giao (sử dụng thêm trang phụ lục cho bảng dưới đây nếu không đủ)</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="col-stt">STT</th>
                            <th rowspan="2" class="col-name">Tên CTNH</th>
                            <th colspan="3">Trạng thái tồn tại</th>
                            <th rowspan="2" class="col-code">Mã CTNH</th>
                            <th rowspan="2" class="col-qty">Số lượng (kg)</th>
                            <th rowspan="2" class="col-method">Phương pháp xử lý #</th>
                        </tr>
                        <tr><th class="col-state">Rắn</th><th class="col-state">Lỏng</th><th class="col-state">Bùn</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageItems as $itemIndex => $item):
                            $state = normalizeWasteStateValue($item['state'] ?? '');
                            $itemNumber = $startIndex + $itemIndex + 1;
                        ?>
                        <tr>
                            <td class="col-stt"><?php echo $itemNumber; ?></td>
                            <td><?php echo sanitize($item['waste_name'] ?? ''); ?></td>
                            <td class="col-state"><?php echo $state === 'solid' ? '<span class="check">✓</span>' : ''; ?></td>
                            <td class="col-state"><?php echo $state === 'liquid' ? '<span class="check">✓</span>' : ''; ?></td>
                            <td class="col-state"><?php echo $state === 'sludge' ? '<span class="check">✓</span>' : ''; ?></td>
                            <td class="col-code"><?php echo sanitize($item['waste_code'] ?? ''); ?></td>
                            <td class="col-qty"><?php echo sanitize(formatNumber($item['quantity'] ?? 0, 2)); ?></td>
                            <td class="col-method"><?php echo sanitize($item['treatment_method'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php for ($emptyRow = count($pageItems); $emptyRow < $itemsPerPage; $emptyRow++): ?>
                        <tr>
                            <td class="col-stt"></td><td></td><td class="col-state"></td><td class="col-state"></td><td class="col-state"></td>
                            <td class="col-code"></td><td class="col-qty"></td><td class="col-method"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </section>

            <div class="notes"># Ghi lần lượt ký hiệu của phương pháp xử lý áp dụng đối với từng CTNH: TC (Tận thu/tái chế); TH (Trung hòa); PT (Phân tách/chiết/lọc kết tủa); OH (Oxy hóa); SH (Sinh học); ĐX (Đồng xử lý); TĐ (Thiêu đốt); HR (Hóa rắn); CL (Cô lập/đóng kén); C (Chôn lấp); SC (Sơ chế); Khác (ghi tên phương pháp).</div>

            <?php if ($pageNum === $totalPages): ?>
            <section class="meta">
                <div><strong>5. Xuất khẩu CTNH (nếu có)</strong><br>Nước nhập khẩu: <span class="dotted"></span><br>Số hiệu phương tiện: <span class="dotted"></span> Ngày xuất cảng: <span class="dotted"></span></div>
                <div>Giấy phép xuất khẩu: <span class="dotted"></span><br>Cửa khẩu nhập: <span class="dotted"></span> Cửa khẩu xuất: <span class="dotted"></span></div>
            </section>
            <section class="acceptance section">
                <strong>7. Xác nhận việc tiếp nhận đủ số lượng và loại CTNH như kê khai ở mục 4</strong>
                <p>Số hiệu phương tiện vận chuyển: <span class="dotted"><?php echo sanitize($doc['vehicle_number'] ?? ''); ?></span></p>
                <p>7.1. Họ tên người nhận thay mặt CS DV XL CTNH 1: <span class="dotted"></span>　Ký: <span class="dotted"></span>　Ngày: <span class="dotted"></span></p>
                <p>7.2. Họ tên người nhận thay mặt CS DV XL CTNH 2: <span class="dotted"></span>　Ký: <span class="dotted"></span>　Ngày: <span class="dotted"></span></p>
            </section>
            <section class="signatures">
                <div class="signature-box">
                    <div class="signature-title">6. Chủ nguồn thải</div>
                    <div class="signature-subtitle">Xác nhận đã thống nhất thông tin và khối lượng CTNH</div>
                    <div class="signature-date">Ngày: ........ tháng ........ năm ........</div>
                    <div class="signature-space"></div>
                    <div class="signature-name"><?php echo sanitize($doc['representative'] ?? ''); ?></div>
                    <div>Ký, ghi rõ họ tên và đóng dấu</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">8. Chủ CS DV XL CTNH</div>
                    <div class="signature-subtitle">Xác nhận hoàn thành việc xử lý an toàn</div>
                    <div class="signature-date">Ngày: ........ tháng ........ năm ........</div>
                    <div class="signature-space"></div>
                    <div>Ký, ghi rõ họ tên và đóng dấu</div>
                </div>
            </section>
            <div class="bottom-note">Liên số: □ 1　 □ 2　 □ 3　 □ 4　　 Ghi chú: <span class="dotted"></span></div>
            <?php endif; ?>
        </div>
        <div class="page-number">Trang <?php echo $pageNum; ?>/<?php echo $totalPages; ?></div>
    </article>
    <?php endforeach; ?>
    <?php endforeach; ?>
    
    <script>
        const PRINT_CSRF_TOKEN = <?php echo json_encode($_SESSION['document_print_csrf']); ?>;

        async function printDocuments() {
            try {
                const response = await fetch(`${location.pathname}${location.search}&log_print=1`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ csrf_token: PRINT_CSRF_TOKEN })
                });
                if (!response.ok) throw new Error('Không lưu được lịch sử in.');
            } catch (error) {
                if (!confirm(`${error.message} Bạn vẫn muốn mở hộp thoại in?`)) return;
            }
            window.print();
        }

        // Auto print on load
        window.onload = function() {
            setTimeout(function() {
                // window.print(); // Uncomment để tự động in
            }, 500);
        };
    </script>
</body>
</html>
