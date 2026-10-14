<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
checkPermission('documents', 'print') or die('Không có quyền');

$requestedIds = explode(',', (string) ($_GET['ids'] ?? $_GET['id'] ?? ''));
$customerIds = array_values(array_unique(array_filter(array_map(static function ($id) {
    return ctype_digit(trim($id)) && (int) $id > 0 ? (int) $id : null;
}, $requestedIds))));
if (!$customerIds) {
    http_response_code(400);
    exit('Thiếu thông tin khách hàng.');
}
if (empty($_SESSION['customer_print_csrf'])) {
    $_SESSION['customer_print_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['log_print'])) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!hash_equals($_SESSION['customer_print_csrf'], (string) ($payload['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Phiên in hết hạn.']);
        exit;
    }
    $submittedIds = array_unique(array_map('intval', $payload['customer_ids'] ?? []));
    $submittedIds = array_values(array_intersect($customerIds, $submittedIds));
    $pageCounts = $payload['page_counts'] ?? [];
    foreach ($submittedIds as $customerId) {
        $result = krudApi('insert', API_TABLE_CUSTOMER_PRINT_HISTORY, [
            'customer_id' => (int) $customerId,
            'user_id' => (int) $_SESSION['user_id'],
            'print_date' => date('Y-m-d H:i:s'),
            'page_count' => max(1, (int) ($pageCounts[(string) $customerId] ?? 1)),
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chứng từ chất thải nguy hại</title>
    <style>
        :root {
            color: #111;
            font-family: "Times New Roman", Times, serif;
            --line: #1a1a1a;
            --soft: #ececec;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f3f3;
            color: #111;
        }
        .toolbar { max-width: 210mm; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; color: #42524b; font-size: 13px; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar button, .toolbar a { border: 0; border-radius: 5px; padding: 10px 14px; color: #fff; background: #176b51; font: inherit; text-decoration: none; cursor: pointer; }
        .toolbar a { background: #52635b; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 20px;
            padding: 0;
            background: #fff;
            box-shadow: 0 8px 28px rgba(0,0,0,0.12);
            display: flex;
            flex-direction: column;
            font: 9pt/1.35 "Times New Roman", serif;
            border: 1px solid #000;
        }
        .form-border { flex: 1; border: 1px solid var(--line); display: flex; flex-direction: column; }
        .topline {
            display: grid;
            grid-template-columns: 1.4fr 2fr 1.2fr;
            align-items: center;
            min-height: 18mm;
            border-bottom: 1px solid var(--line);
            padding: 2mm 3mm;
            font-weight: 700;
        }
        .topline .city {
            text-transform: uppercase;
            font-size: 11pt;
            line-height: 1.2;
        }
        .topline .title {
            text-align: center;
            text-transform: uppercase;
            font-size: 14pt;
            font-weight: 700;
        }
        .topline .doc-meta {
            text-align: right;
            font-size: 8.5pt;
            line-height: 1.3;
        }
        .serial { text-align: center; padding: 2mm 2mm 1.5mm; border-bottom: 1px solid var(--line); font-weight: 700; }
        .section { padding: 2mm 3mm; border-bottom: 1px solid var(--line); }
        .party { min-height: 10mm; }
        .party p { margin: 1mm 0; }
        .party .line { min-height: 5mm; padding-left: 5mm; }
        .dotted { display: inline-block; min-width: 25mm; border-bottom: 1px dotted #555; }
        .customer-line { min-height: 7mm; }
        .customer-value { display: inline-block; min-width: 68%; border-bottom: 1px dotted #555; font-weight: 600; }
        .customer-address { min-width: 72%; }
        .table-title { font-weight: 700; margin-bottom: 1.5mm; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid var(--line); padding: 1.2mm 1mm; overflow-wrap: anywhere; }
        th { text-align: center; font-weight: 700; height: 9mm; background: #efefef; }
        td { height: 7.5mm; }
        .col-no { width: 7%; text-align: center; }
        .col-name { width: 30%; }
        .col-state { width: 6%; text-align: center; }
        .col-code { width: 13%; text-align: center; }
        .col-qty { width: 12%; text-align: right; }
        .col-method { width: 20%; text-align: center; }
        .check { font-weight: 700; font-size: 12pt; }
        .notes { padding: 2mm 3mm; font-size: 7pt; border-bottom: 1px solid var(--line); }
        .meta { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--line); }
        .meta > div { padding: 2mm 3mm; min-height: 12mm; }
        .meta > div + div { border-left: 1px solid var(--line); }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; min-height: 39mm; border-bottom: 1px solid var(--line); }
        .signature { text-align: center; padding: 2mm; border-right: 1px solid var(--line); }
        .signature:last-child { border-right: 0; }
        .signature strong { display: block; text-transform: uppercase; }
        .signature em { font-size: 7.5pt; }
        .signature-space { height: 20mm; }
        .signature-person { font-weight: 700; }
        .confirm { display: grid; grid-template-columns: 1fr 1fr; min-height: 22mm; border-bottom: 1px solid var(--line); }
        .confirm > div { padding: 2mm 3mm; }
        .confirm > div + div { border-left: 1px solid var(--line); }
        .bottom-note { padding: 2mm 3mm; font-size: 7.5pt; }
        .page-footer { margin-top: 2mm; text-align: right; font-size: 8pt; color: #555; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { padding: 0; background: #fff; }
            .toolbar { display: none !important; }
            .page { width: 210mm; height: 297mm; min-height: 0; margin: 0; padding: 9mm 10mm 8mm; box-shadow: none; break-after: page; }
            .page:last-child { break-after: auto; }
        }
        @media screen and (max-width: 760px) {
            body { padding: 10px; overflow-x: auto; }
            .toolbar { align-items: flex-start; flex-direction: column; }
            .page { margin-left: 0; transform-origin: top left; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div id="loadStatus">Đang tải thông tin khách hàng và danh mục chất thải...</div>
        <div class="toolbar-actions">
            <?php if (count($customerIds) === 1): ?>
            <a href="customer_waste.php?customer_id=<?php echo (int) $customerIds[0]; ?>">Quay lại chất thải khách hàng</a>
            <?php else: ?>
            <a href="customers.php">Quay lại danh sách khách hàng</a>
            <?php endif; ?>
            <button type="button" onclick="printCertificate()">In chứng từ</button>
        </div>
    </div>
    <main id="pages"></main>

    <script src="<?php echo KRUD_JS_URL; ?>"></script>
    <script src="<?php echo LIST_JS_URL; ?>"></script>
    <script>
        const CUSTOMER_IDS = <?php echo json_encode($customerIds); ?>;
        const TABLE_CUSTOMERS = <?php echo json_encode(API_TABLE_CUSTOMERS); ?>;
        const TABLE_WASTE = <?php echo json_encode(TABLE_CUSTOMER_WASTE); ?>;
        const TABLE_CUSTOMER_WASTES = <?php echo json_encode(API_TABLE_CUSTOMER_WASTES); ?>;
        const TABLE_WASTE_TYPES = <?php echo json_encode(API_TABLE_WASTE_TYPES); ?>;
        const TABLE_TAX_INFO = <?php echo json_encode(API_TABLE_TAX_INFO); ?>;
        const ROWS_PER_PAGE = 10;
        const PRINT_CSRF_TOKEN = <?php echo json_encode($_SESSION['customer_print_csrf']); ?>;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[character]);
        }

        function normalizeWasteState(value) {
            const raw = String(value ?? '').trim().toLowerCase();
            const compact = raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z]/g, '');
            if (compact === 'solid' || compact.includes('ran')) return 'solid';
            if (compact === 'liquid' || compact.includes('long')) return 'liquid';
            if (compact === 'sludge' || compact.includes('bun')) return 'sludge';
            return '';
        }

        function inferWasteStateFromName(value) {
            const text = String(value ?? '').trim().toLowerCase();
            if (!text) return 'solid';
            const compact = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z]/g, '');
            const sludgeKeywords = ['bun', 'can', 'ba', 'tro', 'loc', 'khoi'];
            const liquidKeywords = ['dau', 'nuoc', 'dich', 'long', 'xoa', 'hoa', 'sua', 'muoi'];

            if (sludgeKeywords.some(keyword => compact.includes(keyword))) return 'sludge';
            if (liquidKeywords.some(keyword => compact.includes(keyword))) return 'liquid';
            return 'solid';
        }

        function renderPage(customer, items, pageIndex, pageCount) {
            const pageItems = items.slice(pageIndex * ROWS_PER_PAGE, (pageIndex + 1) * ROWS_PER_PAGE);
            const rows = Array.from({ length: ROWS_PER_PAGE }, (_, rowIndex) => {
                const item = pageItems[rowIndex];
                const stateKey = item ? normalizeWasteState(item.state) : '';
                const rowNumber = pageIndex * ROWS_PER_PAGE + rowIndex + 1;
                return `<tr>
                    <td class="col-no">${item ? rowNumber : ''}</td>
                    <td>${item ? escapeHtml(item.waste_name) : ''}</td>
                    <td class="col-state">${stateKey === 'solid' ? '<span class="check">✓</span>' : ''}</td>
                    <td class="col-state">${stateKey === 'liquid' ? '<span class="check">✓</span>' : ''}</td>
                    <td class="col-state">${stateKey === 'sludge' ? '<span class="check">✓</span>' : ''}</td>
                    <td class="col-code">${item ? escapeHtml(item.waste_code) : ''}</td>
                    <td class="col-qty">${item ? escapeHtml(item.quantity) : ''}</td>
                    <td class="col-method">${item ? escapeHtml(item.treatment_method) : ''}</td>
                </tr>`;
            }).join('');
            const address = escapeHtml(customer.address || '');
            const customerName = escapeHtml(customer.company || customer.full_name || customer.company_name || '');
            const customerCode = escapeHtml(customer.customer_code || customer.id || '');
            const taxCode = escapeHtml(customer.tax_code || '');
            const phone = escapeHtml(customer.phone || '');
            const representative = escapeHtml(customer.representative || '');
            const year = new Date().getFullYear();

            return `<article class="page" data-customer-id="${escapeHtml(customer.id)}">
                <div class="form-border">
                    <header class="topline">
                        <div class="city">Thành phố<br>Hồ Chí Minh</div>
                        <div class="title">Chứng từ chất thải nguy hại</div>
                        <div class="doc-meta">Số: <strong>${customerCode}/${year}</strong><br>DEMO-02</div>
                    </header>
                    <div class="serial">Số: <strong>${customerCode}/${year}</strong></div>
                    <section class="section party">
                        <p><strong>1. Chủ CS DV XL CTNH 1: CÔNG TY TNHH MỘT THÀNH VIÊN LIÊN HIỆP KHOA HỌC - CÔNG NGHỆ - MÔI TRƯỜNG BIWASE</strong></p>
                        <p class="line">Số giấy phép môi trường: 292/GPMT-BTNMT　ĐT: (0274) 654 6528/529</p>
                        <p class="line">Địa chỉ văn phòng: Số 39, Tổ 6, Khu phố 18, Phường Chánh Phú Hòa, Thành phố Hồ Chí Minh, Việt Nam</p>
                        <p class="line">Địa chỉ cơ sở xử lý: Số 39, Tổ 6, Khu phố 18, Phường Chánh Phú Hòa, Thành phố Hồ Chí Minh, Việt Nam</p>
                    </section>
                    <section class="section party">
                        <p><strong>2. Chủ xử lý CTNH 2:</strong> <span class="dotted"></span> Giấy phép môi trường/Mã số QLCTNH: <span class="dotted"></span></p>
                        <p class="line">Địa chỉ văn phòng: <span class="dotted"></span> ĐT: <span class="dotted"></span></p>
                        <p class="line">Địa chỉ cơ sở: <span class="dotted"></span> ĐT: <span class="dotted"></span></p>
                    </section>
                    <section class="section party">
                        <p class="customer-line"><strong>3. Chủ nguồn thải:</strong> <span class="customer-value">${customerName}</span> MST: <span class="dotted">${taxCode}</span></p>
                        <p class="line">Người đại diện: <span class="customer-value">${representative}</span></p>
                        <p class="line">Địa chỉ văn phòng: <span class="customer-value customer-address">${address}</span> ĐT: <span class="dotted">${phone}</span></p>
                        <p class="line">Địa chỉ cơ sở: <span class="customer-value customer-address">${address}</span> ĐT: <span class="dotted">${phone}</span></p>
                    </section>
                    <section class="section">
                        <div class="table-title">4. Kê khai CTNH (sử dụng thêm trang phụ lục nếu không ghi đủ)</div>
                        <table><thead>
                            <tr><th rowspan="2" class="col-no">STT</th><th rowspan="2" class="col-name">Tên CTNH</th><th colspan="3">Trạng thái tồn tại</th><th rowspan="2" class="col-code">Mã CTNH</th><th rowspan="2" class="col-qty">Số lượng (kg)</th><th rowspan="2" class="col-method">Phương pháp xử lý #</th></tr>
                            <tr><th class="col-state">Rắn</th><th class="col-state">Lỏng</th><th class="col-state">Bùn</th></tr>
                        </thead><tbody>${rows}</tbody></table>
                    </section>
                    <div class="notes"># Ghi lần lượt ký hiệu của phương pháp xử lý áp dụng đối với từng CTNH: TC (Tận thu/tái chế); TH (Trung hòa); PT (Phân tách/chiết/lọc kết tủa); OH (Oxy hóa); SH (Sinh học); ĐX (Đồng xử lý); TĐ (Thiêu đốt); HR (Hóa rắn); CL (Cô lập/đóng kén); C (Chôn lấp); SC (Sơ chế); Khác (tên phương pháp).</div>
                    <div class="meta"><div><strong>5. Xuất khẩu CTNH (nếu có)</strong><br>Số hiệu phương tiện: <span class="dotted"></span> Ngày xuất cảng: <span class="dotted"></span></div><div>Giấy phép xuất khẩu: <span class="dotted"></span><br>Cửa khẩu nhập: <span class="dotted"></span> Cửa khẩu xuất: <span class="dotted"></span></div></div>
                    <div class="meta"><div><strong>7. Xác nhận việc tiếp nhận đã xử lý CTNH</strong><br>Người nhận: <span class="dotted"></span> Ký: <span class="dotted"></span> Ngày: <span class="dotted"></span></div><div><strong>Số hiệu phương tiện vận chuyển:</strong> <span class="dotted"></span><br>Người nhận thay mặt CTNH 2: <span class="dotted"></span></div></div>
                    <div class="signatures">
                        <div class="signature"><strong>6. Chủ nguồn thải</strong><em>Xác nhận thông tin và khối lượng CTNH</em><div class="signature-space"></div><span class="signature-person">${representative}</span><br>Ký, ghi rõ họ tên và đóng dấu</div>
                        <div class="signature"><strong>Người giao / người nhận</strong><em>Họ tên, chữ ký và ngày</em><div class="signature-space"></div>Ký, ghi rõ họ tên</div>
                        <div class="signature"><strong>8. Chủ CS DV XL CTNH</strong><em>Xác nhận hoàn thành việc xử lý</em><div class="signature-space"></div>Ký, ghi rõ họ tên và đóng dấu</div>
                    </div>
                    <div class="bottom-note">Liên số: □ 1　 □ 2　 □ 3　 □ 4　　 Ghi chú: <span class="dotted"></span></div>
                </div>
                <div class="page-footer">Trang ${pageIndex + 1}/${pageCount}</div>
            </article>`;
        }

        async function loadCertificate() {
            const status = document.getElementById('loadStatus');
            try {
                const [customerResponse, wasteResponse, taxInfoResponse, linksResponse, typesResponse] = await Promise.all([
                    getKrudList({ table: TABLE_CUSTOMERS, limit: 1000 }),
                    getKrudList({ table: TABLE_WASTE, sort: { id: 'ASC' }, limit: 10000 }),
                    getKrudList({ table: TABLE_TAX_INFO, limit: 5000 }),
                    getKrudList({ table: TABLE_CUSTOMER_WASTES, limit: 10000 }),
                    getKrudList({ table: TABLE_WASTE_TYPES, limit: 10000 })
                ]);
                if (!customerResponse?.success || !Array.isArray(customerResponse.data)) {
                    throw new Error('Không thể tải danh sách khách hàng.');
                }
                const selectedIdSet = new Set(CUSTOMER_IDS.map(String));
                const customers = customerResponse.data.filter(customer => selectedIdSet.has(String(customer.id)));
                if (!customers.length) throw new Error('Không tìm thấy khách hàng đã chọn.');

                const allItems = wasteResponse?.success && Array.isArray(wasteResponse.data) ? wasteResponse.data : [];
                const allTaxInfo = taxInfoResponse?.success && Array.isArray(taxInfoResponse.data) ? taxInfoResponse.data : [];
                const allCustomerLinks = linksResponse?.success && Array.isArray(linksResponse.data) ? linksResponse.data : [];
                const wasteTypesById = new Map((typesResponse?.success && Array.isArray(typesResponse.data) ? typesResponse.data : [])
                    .map(type => [String(type.id), type]));
                let totalPages = 0;
                let totalItems = 0;
                const renderedPages = [];
                customers.forEach(customer => {
                    const taxInfo = allTaxInfo.find(item => String(item.customer_id) === String(customer.id)) || {};
                    customer.tax_code ||= taxInfo.tax_code || '';
                    customer.representative ||= taxInfo.representative || '';
                    customer.address ||= taxInfo.address_official || '';
                    customer.full_name ||= taxInfo.company_name_official || '';
                    const items = allItems.filter(item => String(item.customer_id) === String(customer.id)).map(item => ({
                        ...item,
                        state: normalizeWasteState(item.state) || inferWasteStateFromName(item.waste_name || item.waste_code || item.notes || ''),
                    }));
                    const itemCodes = new Set(items.map(item => String(item.waste_code || '').trim()).filter(Boolean));
                    allCustomerLinks
                        .filter(link => String(link.customer_id) === String(customer.id))
                        .forEach(link => {
                            const wasteType = wasteTypesById.get(String(link.waste_type_id));
                            if (!wasteType || itemCodes.has(String(wasteType.waste_code || '').trim())) return;
                            items.push({
                                waste_name: wasteType.waste_name || '',
                                waste_code: wasteType.waste_code || '',
                                state: normalizeWasteState(wasteType.state || '') || inferWasteStateFromName(wasteType.waste_name || wasteType.description || ''),
                                quantity: link.estimated_quantity ?? '',
                                treatment_method: '',
                            });
                            itemCodes.add(String(wasteType.waste_code || '').trim());
                        });
                    const pageCount = Math.max(1, Math.ceil(items.length / ROWS_PER_PAGE));
                    totalItems += items.length;
                    totalPages += pageCount;
                    renderedPages.push(...Array.from({ length: pageCount }, (_, index) =>
                        renderPage(customer, items, index, pageCount)));
                });
                document.getElementById('pages').innerHTML = renderedPages.join('');
                status.textContent = `${customers.length} khách hàng · ${totalItems} dòng chất thải · ${totalPages} trang · tối đa ${ROWS_PER_PAGE} dòng/trang`;
            } catch (error) {
                status.textContent = error.message || 'Không thể tải dữ liệu chứng từ.';
            }
        }

        async function printCertificate() {
            const pageCounts = {};
            document.querySelectorAll('.page[data-customer-id]').forEach(page => {
                const customerId = page.dataset.customerId;
                pageCounts[customerId] = (pageCounts[customerId] || 0) + 1;
            });
            try {
                const response = await fetch(`${location.pathname}${location.search}&log_print=1`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ customer_ids: CUSTOMER_IDS, page_counts: pageCounts, csrf_token: PRINT_CSRF_TOKEN })
                });
                if (!response.ok) throw new Error('Không lưu được lịch sử in.');
            } catch (error) {
                if (!confirm(`${error.message} Bạn vẫn muốn mở hộp thoại in?`)) return;
            }
            window.print();
        }

        document.addEventListener('DOMContentLoaded', loadCertificate);
    </script>
</body>
</html>