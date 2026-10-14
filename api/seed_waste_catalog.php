<?php
require_once __DIR__ . '/includes/config.php';

function normalizeWasteCode($value)
{
    return preg_replace('/\s+/', ' ', trim((string) $value));
}

function inferWasteStateFromName($value)
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return 'solid';
    }

    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $compact = $transliterated !== false ? strtolower((string) $transliterated) : $text;
    $compact = preg_replace('/\s+/', '', $compact);
    $compact = preg_replace('/[^a-z]/', '', $compact);

    $sludgeKeywords = ['bun', 'can', 'ba', 'tro', 'loc'];
    $liquidKeywords = ['dau', 'nuoc', 'dich', 'long', 'hoa', 'sua'];

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

function addWasteType($wasteCode, $wasteName, $category = 'hazardous', $unit = 'kg', $pricePerUnit = 120000, $description = '')
{
    $normalized = normalizeWasteCode($wasteCode);
    $existing = krudApiList(API_TABLE_WASTE_TYPES, [
        ['field' => 'waste_code', 'operator' => 'LIKE', 'value' => $normalized],
    ], [], 1, 20);

    if (($existing['success'] ?? false) && (($existing['total'] ?? 0) > 0)) {
        return ['already_exists' => true, 'waste_code' => $normalized];
    }

    return krudApi('insert', API_TABLE_WASTE_TYPES, [
        'waste_code' => $normalized,
        'waste_name' => trim($wasteName),
        'category' => $category,
        'state' => inferWasteStateFromName($wasteName),
        'unit' => $unit,
        'price_per_unit' => (float) $pricePerUnit,
        'description' => trim($description) ?: 'Chất thải nguy hại được quản lý theo danh mục CTNH.',
    ]);
}

$catalog = [
    ['05 01 01', 'Bùn đáy từ bể chứa dầu', 'hazardous', 150000, 'Bùn đáy chứa dầu và cặn hạt'],
    ['05 01 02', 'Bùn cặn từ hệ thống xử lý nước thải dầu khí', 'hazardous', 145000, 'Bùn cặn có chứa hydrocarbon'],
    ['05 01 03', 'Bùn hóa chất công nghiệp', 'hazardous', 160000, 'Bùn từ xử lý hóa chất'],
    ['05 01 04', 'Cặn cuối từ lò hơi', 'hazardous', 130000, 'Cặn than và tro đáy lò'],
    ['05 01 05', 'Cặn từ hệ thống lọc dầu', 'hazardous', 155000, 'Cặn lọc dầu mỡ và tạp chất'],
    ['05 02 01', 'Lõi lọc dầu thải', 'hazardous', 140000, 'Lõi lọc có nhiễm dầu'],
    ['05 02 02', 'Bộ phận lọc hóa chất', 'hazardous', 135000, 'Bộ lọc hóa chất dùng một lần'],
    ['05 02 03', 'Bột than hoạt tính đã sử dụng', 'hazardous', 120000, 'Than hoạt tính nhiễm hóa chất'],
    ['05 02 04', 'Bông lọc trong nhà máy', 'hazardous', 118000, 'Lọc dầu đến bề mặt'],
    ['05 02 05', 'Bộ lọc khí thải', 'hazardous', 146000, 'Lõi lọc khí công nghiệp'],
    ['06 01 01', 'Bông gòn nhiễm thuốc', 'hazardous', 105000, 'Vật liệu y tế nhiễm dược phẩm'],
    ['06 01 02', 'Gạc y tế thải', 'hazardous', 98000, 'Gạc nhiễm hóa chất và máu'],
    ['06 01 03', 'Băng dính y khoa thải', 'hazardous', 92000, 'Băng dính bị nhiễm thuốc'],
    ['06 01 04', 'Khăn giấy nhiễm hóa chất', 'hazardous', 87000, 'Khăn giấy công nghiệp thải'],
    ['07 01 01', 'Dầu nhờn đã sử dụng', 'hazardous', 170000, 'Dầu bôi trơn phát sinh từ máy'],
    ['07 01 02', 'Dầu động cơ thải', 'hazardous', 175000, 'Dầu động cơ đã qua sử dụng'],
    ['07 01 03', 'Dầu hộp số thải', 'hazardous', 172000, 'Dầu hộp số và giảm chấn'],
    ['07 01 04', 'Dầu thủy lực thải', 'hazardous', 168000, 'Dầu thủy lực có tạp chất'],
    ['07 02 01', 'Dầu biến áp thải', 'hazardous', 182000, 'Dầu cách điện cần xử lý'],
    ['07 02 02', 'Dầu cách điện thải', 'hazardous', 180000, 'Dầu cách điện đã qua sử dụng'],
    ['07 02 03', 'Dầu hỏa thải', 'hazardous', 185000, 'Dầu hỏa công nghiệp thải'],
    ['07 02 04', 'Dầu từ bồn chứa', 'hazardous', 178000, 'Dầu tràn và dính bồn'],
    ['08 01 01', 'Cặn sơn thải', 'hazardous', 118000, 'Cặn sơn và dung môi'],
    ['08 01 02', 'Bã sơn cũ', 'hazardous', 110000, 'Sơn cũ phát sinh từ bảo dưỡng'],
    ['08 01 03', 'Chất pha sơn thải', 'hazardous', 126000, 'Dịch pha sơn và chất tẩy'],
    ['08 02 01', 'Mực in thải', 'hazardous', 114000, 'Mực in và thuốc nhuộm thải'],
    ['08 02 02', 'Bản mực in cũ', 'hazardous', 116000, 'Bản mực in đã hết hạn'],
    ['08 02 03', 'Dầu mực in thải', 'hazardous', 122000, 'Mực in dạng dầu'],
    ['08 02 04', 'Hộp mực in thải', 'hazardous', 109000, 'Bao bì mực in'],
    ['08 03 01', 'Keo dán công nghiệp thải', 'hazardous', 100000, 'Keo và chất kết dính hỏng'],
    ['08 03 02', 'Chất kết dính chứa dung môi', 'hazardous', 104000, 'Chất dính có tan'],
    ['08 03 03', 'Dung môi hỏng', 'hazardous', 160000, 'Dung môi công nghiệp thải'],
    ['08 04 01', 'Phụ gia hóa chất', 'hazardous', 121000, 'Phụ gia và hóa chất phụ'],
    ['09 01 01', 'Mẫu hóa chất thải', 'hazardous', 132000, 'Mẫu thí nghiệm và hóa chất'],
    ['09 01 02', 'Caustic thải', 'hazardous', 140000, 'Dung dịch kiềm đã qua sử dụng'],
    ['09 01 03', 'Dung dịch axit thải', 'hazardous', 138000, 'Axit công nghiệp thải'],
    ['09 01 04', 'Chất tẩy rửa thải', 'hazardous', 104000, 'Dung dịch tẩy rửa chứa hóa chất'],
    ['10 01 01', 'Bã mía và cặn hữu cơ', 'industrial', 60000, 'Phế phẩm hữu cơ từ sản xuất'],
    ['10 01 02', 'Cặn bã thực phẩm', 'industrial', 58000, 'Bã thực phẩm sản xuất'],
    ['10 01 03', 'Nước thải chất hữu cơ', 'industrial', 54000, 'Nước thải hữu cơ'],
    ['10 02 01', 'Phế liệu giấy', 'industrial', 42000, 'Giấy và carton thải'],
    ['10 02 02', 'Bã giấy sản xuất', 'industrial', 40000, 'Số lượng giấy phụ phẩm'],
    ['10 02 03', 'Tấm carton hỏng', 'industrial', 39000, 'Carton và bao bì'],
    ['12 01 01', 'Thùng phuy nhựa thải', 'industrial', 35000, 'Phuy và bao bì nhựa'],
    ['12 01 02', 'Bao bì PE thải', 'industrial', 30000, 'Nhựa PE và PVC'],
    ['12 01 03', 'Vỏ bao nhựa', 'industrial', 28000, 'Bao bì nhựa khác'],
    ['12 01 04', 'Bìa nhựa cứng', 'industrial', 32000, 'Nhựa cứng thải'],
    ['12 02 01', 'Túi nilon thải', 'industrial', 25000, 'Túi nhựa rác'],
    ['12 02 02', 'Hộp nhựa hỏng', 'industrial', 26000, 'Phụ tùng nhựa'],
    ['13 01 01', 'Kim tiêm thải', 'hazardous', 95000, 'Kim tiêm và vật dụng tiêm'],
    ['13 01 02', 'Dụng cụ y tế thải', 'hazardous', 90000, 'Bông băng, găng tay thải'],
    ['13 01 03', 'Dịch thuốc thải', 'hazardous', 110000, 'Dung dịch thuốc đã hết hạn'],
    ['13 01 04', 'Vật liệu bệnh phẩm thải', 'hazardous', 98000, 'Chất thải y tế'],
    ['13 02 01', 'Bột thuốc trừ sâu thải', 'hazardous', 150000, 'Thuốc trừ sâu hỏng'],
    ['13 02 02', 'Bón phân hóa học thải', 'hazardous', 136000, 'Phân bón hóa chất thải'],
    ['13 02 03', 'Dầu và mỡ thực phẩm thải', 'industrial', 68000, 'Mỡ thải từ nhà máy'],
    ['14 01 01', 'Bồn chứa hóa chất hỏng', 'hazardous', 200000, 'Bồn chứa đã hết vòng đời'],
    ['14 01 02', 'Vỏ cọc kim loại hóa chất', 'hazardous', 175000, 'Thùng chứa kim loại'],
    ['14 01 03', 'Bơm và van hóa chất thải', 'hazardous', 158000, 'Van và bơm hỏng'],
    ['14 02 01', 'Dây điện hỏng', 'industrial', 48000, 'Dây điện gia công'],
    ['14 02 02', 'Bộ phận điện tử thải', 'industrial', 52000, 'Bo mạch và linh kiện thải'],
    ['15 01 01', 'Phế liệu thép', 'industrial', 44000, 'Sắt, thép hỏng'],
    ['15 01 02', 'Phế liệu nhôm', 'industrial', 46000, 'Nhôm và hợp kim'],
    ['15 01 03', 'Phế liệu đồng', 'industrial', 51000, 'Đồng và đồng thau'],
    ['15 01 04', 'Phế liệu nhôm vỏ máy', 'industrial', 47000, 'Vỏ máy nhôm'],
    ['15 02 01', 'Sắt vụn cắt', 'industrial', 43000, 'Vết cắt và phế liệu kim loại'],
    ['15 02 02', 'Đinh, bu lông thải', 'industrial', 36000, 'Phế liệu ốc vít'],
    ['16 01 01', 'Bóng đèn thủy tinh', 'hazardous', 72000, 'Đèn phóng điện thải'],
    ['16 01 02', 'Bóng đèn LED hỏng', 'hazardous', 70000, 'Đèn LED thải'],
    ['16 01 03', 'Ống huỳnh quang thải', 'hazardous', 76000, 'Đèn huỳnh quang lỗi'],
    ['16 01 04', 'Bóng đèn halogen thải', 'hazardous', 71000, 'Halogen thải'],
    ['16 02 01', 'Màn hình điện tử hỏng', 'hazardous', 89000, 'Màn hình phế liệu'],
    ['16 02 02', 'Máy tính để bàn hỏng', 'hazardous', 92000, 'Tủ và màn hình thải'],
    ['17 01 01', 'Mỡ dầu từ phụ gia', 'hazardous', 126000, 'Mỡ công nghiệp thải'],
    ['17 01 02', 'Tạp chất từ nhà bếp', 'industrial', 62000, 'Mỡ và xà bần thải'],
    ['17 01 03', 'Dầu thực phẩm thải', 'industrial', 64000, 'Dầu chiên và dầu ăn thải'],
    ['17 02 01', 'Keo và chất dính thải', 'hazardous', 101000, 'Keo loang và chất dính'],
    ['17 02 02', 'Dung môi pha sơn', 'hazardous', 150000, 'Hòa tan và sơn thải'],
    ['17 02 03', 'Dầu tổng hợp thải', 'hazardous', 170000, 'Dầu tổng hợp có độc tính'],
    ['17 02 04', 'Chất tẩy công nghiệp', 'hazardous', 130000, 'Dung dịch tẩy tinh khiết'],
    ['18 01 01', 'Bột lớn từ quặng', 'industrial', 38000, 'Phế liệu khoáng'],
    ['18 01 02', 'Cát lọc thải', 'industrial', 37000, 'Cát và vật liệu lọc'],
    ['18 01 03', 'Đá mài thải', 'industrial', 33000, 'Đá mài bị mòn'],
    ['18 01 04', 'Bùn than có nhiễm dầu', 'hazardous', 148000, 'Than bùn thải'],
    ['18 02 01', 'Vật liệu hấp thụ dầu', 'hazardous', 152000, 'Vật liệu hấp thụ dầu thải'],
    ['18 02 02', 'Bột than hấp thụ', 'hazardous', 149000, 'Than hấp thụ thải'],
    ['18 02 03', 'Bùn lọc nước thải', 'hazardous', 140000, 'Bùn lọc có độc tính'],
    ['18 02 04', 'Cặn than sau lọc', 'hazardous', 142000, 'Cặn than trong lọc khí'],
    ['19 01 01', 'Pin lithium thải', 'hazardous', 220000, 'Pin và ắc quy lưới'],
    ['19 01 02', 'Pin kiềm thải', 'hazardous', 210000, 'Pin AA và AAA hỏng'],
    ['19 01 03', 'Ắc quy ô tô thải', 'hazardous', 240000, 'Ắc quy chì và axit'],
    ['19 01 04', 'Pin xe máy thải', 'hazardous', 215000, 'Pin xe máy cũ'],
    ['19 02 01', 'Bo mạch điện tử hỏng', 'hazardous', 86000, 'Mạch điện tử bỏ đi'],
    ['19 02 02', 'Bộ nguồn máy tính thải', 'hazardous', 90000, 'Nguồn điện và adapter'],
    ['19 02 03', 'Tấm pin mặt trời hỏng', 'hazardous', 155000, 'Pin quang điện thải'],
    ['19 03 01', 'Ống thủy tinh thủy tinh', 'industrial', 30000, 'Ống thủy tinh thải'],
    ['19 03 02', 'Cốc nhựa và chai thải', 'industrial', 26000, 'Thùng chứa nhựa'],
    ['19 03 03', 'Bộ dụng cụ nhựa hỏng', 'industrial', 28000, 'Vật liệu nhựa tái sử dụng'],
    ['20 01 01', 'Bụi than thải', 'industrial', 43000, 'Bụi than và tro'],
    ['20 01 02', 'Tro lò than', 'hazardous', 125000, 'Tro và cặn lò than'],
    ['20 01 03', 'Cặn tro xây dựng', 'industrial', 34000, 'Tro và cát xây dựng'],
    ['20 02 01', 'Nước thải sơn', 'hazardous', 128000, 'Nước thải có sơn'],
    ['20 02 02', 'Bùn xử lý hóa học', 'hazardous', 146000, 'Bùn hóa học từ xử lý nước'],
    ['20 02 03', 'Bùn từ lò phản ứng', 'hazardous', 152000, 'Bùn phản ứng hóa học'],
    ['20 02 04', 'Chất bẩn từ dây mạt', 'hazardous', 119000, 'Mạt và bụi hóa chất'],
    ['21 01 01', 'Màng lọc khói', 'hazardous', 132000, 'Màng lọc khói nhà máy'],
    ['21 01 02', 'Bụi quặng và nhôm', 'industrial', 41000, 'Bụi quặng từ khai thác'],
    ['21 01 03', 'Bột sắt hỏng', 'industrial', 47000, 'Bột sắt và thép'],
    ['21 01 04', 'Dây chằng hóa chất', 'hazardous', 124000, 'Dây và sợi hóa chất'],
    ['22 01 01', 'Phế liệu thủy tinh', 'industrial', 29000, 'Thủy tinh và cốc thải'],
    ['22 01 02', 'Gốm sứ hỏng', 'industrial', 31000, 'Gốm sứ sản xuất'],
    ['22 01 03', 'Ống cao su thải', 'industrial', 37000, 'Cao su và băng dính thải'],
    ['22 01 04', 'Xốp và vật liệu cách nhiệt', 'industrial', 33000, 'Xốp đóng gói thải'],
    ['23 01 01', 'Bùn sơn lót', 'hazardous', 127000, 'Bùn dính sơn và dung môi'],
    ['23 01 02', 'Chất thải bề mặt kim loại', 'industrial', 45000, 'Bề mặt kim loại mòn'],
    ['23 01 03', 'Máy móc cũ', 'industrial', 50000, 'Máy móc và phụ tùng'],
    ['23 01 04', 'Vỏ bao bì và cacton', 'industrial', 36000, 'Bao bì và cuộn'],
    ['24 01 01', 'Bột xỉ than', 'industrial', 41000, 'Xỉ than và cặn lò'],
    ['24 01 02', 'Cặn khói từ lò', 'hazardous', 136000, 'Bụi khói từ lò đốt'],
    ['24 01 03', 'Bùn từ máy giặt hóa chất', 'hazardous', 138000, 'Bùn rửa công nghiệp'],
    ['24 01 04', 'Cặn mỡ và bùn máy', 'hazardous', 133000, 'Mỡ và cặn bồn chứa'],
];

$results = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
];

foreach ($catalog as $index => $row) {
    $code = $row[0];
    $name = $row[1];
    $category = $row[2];
    $price = $row[3];
    $description = $row[4] ?? 'Chất thải được quản lý theo danh mục CTNH';

    $res = addWasteType($code, $name, $category, 'kg', $price, $description);
    if (($res['success'] ?? false)) {
        $results['inserted']++;
    } elseif (($res['already_exists'] ?? false)) {
        $results['skipped']++;
    } else {
        $results['updated']++;
    }
}

$customerList = krudApiList(API_TABLE_CUSTOMERS, [], ['id' => 'ASC'], 1, 50);
$customerIds = array_values(array_filter(array_map('intval', array_column($customerList['data'] ?? [], 'id'))));
$customerId = $customerIds[0] ?? 0;

$wasteRows = krudApiList(API_TABLE_WASTE_TYPES, [], ['id' => 'ASC'], 1, 500);
$wasteIds = array_values(array_filter(array_map('intval', array_column($wasteRows['data'] ?? [], 'id'))));

if ($customerId > 0 && !empty($wasteIds)) {
    $selected = array_slice($wasteIds, 0, 25);
    foreach ($selected as $idx => $wasteId) {
        $check = krudApiList(API_TABLE_CUSTOMER_WASTES, [
            ['field' => 'customer_id', 'operator' => '=', 'value' => $customerId],
            ['field' => 'waste_type_id', 'operator' => '=', 'value' => $wasteId],
        ], [], 1, 10);

        if (($check['total'] ?? 0) === 0) {
            krudApi('insert', API_TABLE_CUSTOMER_WASTES, [
                'customer_id' => $customerId,
                'waste_type_id' => $wasteId,
                'estimated_quantity' => 150 + ($idx * 25),
                'unit' => 'kg',
                'notes' => 'Mẫu liên kết danh mục CTNH để quản lý khách hàng.',
            ]);
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'catalog_total' => count($catalog),
    'inserted' => $results['inserted'],
    'skipped' => $results['skipped'],
    'customer_linked' => $customerId > 0 ? count(array_slice($wasteIds, 0, 25)) : 0,
], JSON_UNESCAPED_UNICODE);
