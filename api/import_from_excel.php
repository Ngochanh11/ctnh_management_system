<?php
/**
 * SCRIPT IMPORT DỮ LIỆU TỪ EXCEL VÀO DATABASE
 * File: import_from_excel.php
 */

require_once __DIR__ . '/includes/config.php';

// Cấu hình
$excelFile = __DIR__ . '/7.Khu vực Tân Uyên.xlsx';
$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "==========================================================\n";
echo "    IMPORT DỮ LIỆU TỪ EXCEL VÀO DATABASE\n";
echo "==========================================================\n\n";

if ($dryRun) {
    echo "⚠️  CHẾ ĐỘ TEST (DRY RUN) - Không insert vào database\n\n";
}

// =====================================================
// BƯỚC 1: PARSE EXCEL FILE
// =====================================================
echo "📂 Bước 1: Đọc file Excel...\n";

$tempDir = __DIR__ . '/temp_excel_' . time();
mkdir($tempDir);

$zipFile = $tempDir . '/file.zip';
copy($excelFile, $zipFile);

$command = "powershell -Command \"Expand-Archive -Path '$zipFile' -DestinationPath '$tempDir' -Force\" 2>&1";
exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    die("❌ Không thể giải nén file Excel!\n");
}

// Đọc shared strings
$sharedStringsFile = $tempDir . '/xl/sharedStrings.xml';
$sharedStrings = [];

if (file_exists($sharedStringsFile)) {
    $xml = simplexml_load_file($sharedStringsFile);
    if ($xml) {
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else if (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string)$r->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }
    }
}

// Đọc workbook
$workbookFile = $tempDir . '/xl/workbook.xml';
$sheetNames = [];

if (file_exists($workbookFile)) {
    $xml = simplexml_load_file($workbookFile);
    if ($xml) {
        foreach ($xml->sheets->sheet as $sheet) {
            $sheetNames[] = (string)$sheet['name'];
        }
    }
}

$allSheets = [];

// Đọc từng sheet
foreach ($sheetNames as $sheetIndex => $sheetName) {
    $sheetFile = $tempDir . '/xl/worksheets/sheet' . ($sheetIndex + 1) . '.xml';
    
    if (!file_exists($sheetFile)) {
        continue;
    }
    
    $xml = simplexml_load_file($sheetFile);
    if (!$xml) {
        continue;
    }
    
    $rows = [];
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
            $col = 0;
            $colStr = $matches[1];
            
            for ($i = 0; $i < strlen($colStr); $i++) {
                $col = $col * 26 + (ord($colStr[$i]) - ord('A'));
            }
            
            $value = '';
            if (isset($cell['t']) && (string)$cell['t'] === 's') {
                $index = (int)$cell->v;
                $value = isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
            } else if (isset($cell->v)) {
                $value = (string)$cell->v;
            }
            
            while (count($rowData) < $col) {
                $rowData[] = '';
            }
            
            $rowData[] = $value;
        }
        
        $rows[] = $rowData;
    }
    
    $allSheets[$sheetName] = $rows;
}

// Cleanup temp
function deleteDirectory($dir) {
    if (!file_exists($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

deleteDirectory($tempDir);

echo "✅ Đọc được " . count($allSheets) . " sheets\n\n";

// =====================================================
// BƯỚC 2: XỬ LÝ VÀ IMPORT DỮ LIỆU
// =====================================================

$stats = [
    'customers_imported' => 0,
    'customers_skipped' => 0,
    'wastes_linked' => 0,
    'schedules_created' => 0,
    'waste_codes_missing' => [],
    'errors' => []
];

$availableWasteCodes = [];
if ($dryRun) {
    $wasteCatalog = krudApiList(API_TABLE_WASTE_TYPES, [], [], 1, 1000);
    foreach (($wasteCatalog['data'] ?? []) as $wasteType) {
        $availableWasteCodes[$wasteType['waste_code'] ?? ''] = true;
    }
}

foreach ($allSheets as $sheetName => $rows) {
    echo "📊 Xử lý sheet: $sheetName\n";
    echo str_repeat("-", 60) . "\n";
    
    if (count($rows) < 3) {
        echo "⚠️  Sheet trống hoặc không đủ dữ liệu\n\n";
        continue;
    }
    
    // Xác định loại chất thải từ tên sheet
    $wasteCategory = 'hazardous';
    if (stripos($sheetName, 'công nghiệp') !== false) {
        $wasteCategory = 'industrial';
    }
    
    // Lấy header row (dòng 3, index 2)
    $headerRow = $rows[2];
    
    // Tìm vị trí các cột quan trọng
    $colMap = [];
    foreach ($headerRow as $colIndex => $headerValue) {
        $headerValue = trim($headerValue);
        
        if ($headerValue === 'STT') {
            $colMap['stt'] = $colIndex;
        } else if (stripos($headerValue, 'Tên Công Ty') !== false) {
            $colMap['company'] = $colIndex;
        } else if (stripos($headerValue, 'Địa chỉ') !== false) {
            $colMap['address'] = $colIndex;
        } else if (stripos($headerValue, 'Thành Phần') !== false || stripos($headerValue, 'Thành phần chất thải') !== false) {
            $colMap['wastes'] = $colIndex;
        } else if (stripos($headerValue, 'Tuần') !== false || stripos($headerValue, 'Tuần') !== false) {
            $colMap['frequency'] = $colIndex;
        } else if (stripos($headerValue, 'Điện Thoại') !== false || stripos($headerValue, 'Số Điện Thoại') !== false) {
            $colMap['phone'] = $colIndex;
        } else if (stripos($headerValue, 'Ngày Nhận') !== false || stripos($headerValue, 'Hợp Đồng') !== false) {
            $colMap['contract_date'] = $colIndex;
        } else if (stripos($headerValue, 'Hạn Hẹn') !== false) {
            $colMap['expiry_date'] = $colIndex;
        }
    }
    
    // Tìm các cột lịch (Tuần 1, Tuần 2, ...)
    $scheduleColumns = [];
    foreach ($headerRow as $colIndex => $headerValue) {
        if (preg_match('/Tuần?\s*(\d+)/iu', $headerValue, $matches) || 
            preg_match('/(\d{2}\s+\d{2}\s+\d{2})/iu', $headerValue)) {
            $scheduleColumns[] = $colIndex;
        }
    }
    
    echo "Tìm thấy " . count($scheduleColumns) . " cột lịch thu gom\n";
    
    // Xử lý từng dòng dữ liệu (bắt đầu từ dòng 4, index 3)
    for ($rowIndex = 3; $rowIndex < count($rows); $rowIndex++) {
        $row = $rows[$rowIndex];
        
        // Lấy tên công ty
        $companyName = '';
        if (isset($colMap['company']) && isset($row[$colMap['company']])) {
            $companyName = trim($row[$colMap['company']]);
        } else if (isset($row[1])) {
            $companyName = trim($row[1]);
        }
        
        // Skip nếu không có tên công ty
        if (empty($companyName) || strlen($companyName) < 5) {
            continue;
        }
        
        // Skip nếu là header lặp lại
        if (stripos($companyName, 'Công Ty') === 0 && stripos($companyName, 'STT') !== false) {
            continue;
        }
        
        if ($dryRun) {
            echo "\n📋 Dòng dữ liệu hợp lệ " . ($rowIndex + 1) . "\n";
        } else {
            echo "\n📋 Dòng " . ($rowIndex + 1) . ": $companyName\n";
        }
        
        // Lấy các thông tin khác
        $address = isset($colMap['address']) && isset($row[$colMap['address']]) ? trim($row[$colMap['address']]) : (isset($row[2]) ? trim($row[2]) : '');
        $wastesStr = isset($colMap['wastes']) && isset($row[$colMap['wastes']]) ? trim($row[$colMap['wastes']]) : (isset($row[3]) ? trim($row[3]) : '');
        $frequency = isset($colMap['frequency']) && isset($row[$colMap['frequency']]) ? trim($row[$colMap['frequency']]) : '';
        $phone = isset($colMap['phone']) && isset($row[$colMap['phone']]) ? trim($row[$colMap['phone']]) : '';
        $contractDate = isset($colMap['contract_date']) && isset($row[$colMap['contract_date']]) ? trim($row[$colMap['contract_date']]) : '';
        $expiryDate = isset($colMap['expiry_date']) && isset($row[$colMap['expiry_date']]) ? trim($row[$colMap['expiry_date']]) : '';
        
        // Parse ngày tháng (Excel serial hoặc format DD/MM/YYYY)
        $contractDateFormatted = parseExcelDate($contractDate);
        $expiryDateFormatted = parseExcelDate($expiryDate);
        
        // Parse danh sách mã chất thải
        $wasteCodes = parseWasteCodes($wastesStr);
        
        if ($dryRun) {
            echo "  - Mã chất thải: " . count($wasteCodes) . "\n";
            foreach ($wasteCodes as $wasteCode) {
                if (!isset($availableWasteCodes[$wasteCode])) {
                    $stats['waste_codes_missing'][$wasteCode] = true;
                }
            }
            $stats['customers_imported']++;
            foreach ($scheduleColumns as $scheduleColumn) {
                if (!empty($row[$scheduleColumn]) && parseExcelDate(trim($row[$scheduleColumn]))) {
                    $stats['schedules_created']++;
                }
            }
            continue;
        }

        echo "  - Địa chỉ: " . substr($address, 0, 50) . (strlen($address) > 50 ? '...' : '') . "\n";
        echo "  - Số chất thải: " . count($wasteCodes) . "\n";
        echo "  - Tần suất: $frequency\n";
        
        if (!$dryRun) {
            // INSERT vào database
            try {
                // 1. Insert or update customer through the company API.
                $customerCode = generateCustomerCode($companyName);
                $customerData = [
                    'customer_code' => $customerCode,
                    'full_name' => $companyName,
                    'address' => $address,
                    'phone' => $phone,
                    'contract_date' => $contractDateFormatted ?: null,
                    'contract_expiry_date' => $expiryDateFormatted ?: null,
                    'frequency' => $frequency,
                    'status' => 'active',
                ];
                $existingCustomers = krudApiList(API_TABLE_CUSTOMERS, [
                    ['field' => 'customer_code', 'operator' => '=', 'value' => $customerCode],
                ], [], 1, 1);
                if (!($existingCustomers['success'] ?? false)) {
                    throw new RuntimeException($existingCustomers['error'] ?? 'Không thể tra cứu khách hàng trên API.');
                }
                if (!empty($existingCustomers['data'][0]['id'])) {
                    $customerId = $existingCustomers['data'][0]['id'];
                    $customerResult = krudApi('update', API_TABLE_CUSTOMERS, $customerData, $customerId);
                } else {
                    $customerResult = krudApi('insert', API_TABLE_CUSTOMERS, $customerData);
                    $customerId = $customerResult['data']['id'] ?? null;
                }
                if (!($customerResult['success'] ?? false) || !$customerId) {
                    throw new RuntimeException($customerResult['error'] ?? 'API không trả về mã khách hàng.');
                }
                
                echo "  ✅ Customer ID: $customerId\n";
                $stats['customers_imported']++;
                
                // 2. Link waste types
                foreach ($wasteCodes as $wasteCode) {
                    $wasteTypes = krudApiList(API_TABLE_WASTE_TYPES, [
                        ['field' => 'waste_code', 'operator' => '=', 'value' => $wasteCode],
                    ], [], 1, 1);
                    $wasteTypeId = $wasteTypes['data'][0]['id'] ?? null;
                    
                    if ($wasteTypeId) {
                        $existingLinks = krudApiList(API_TABLE_CUSTOMER_WASTES, [
                            ['field' => 'customer_id', 'operator' => '=', 'value' => (int) $customerId],
                            ['field' => 'waste_type_id', 'operator' => '=', 'value' => (int) $wasteTypeId],
                        ], [], 1, 1);
                        if (empty($existingLinks['data'])) {
                            $linkResult = krudApi('insert', API_TABLE_CUSTOMER_WASTES, [
                                'customer_id' => (int) $customerId,
                                'waste_type_id' => (int) $wasteTypeId,
                            ]);
                            if (!($linkResult['success'] ?? false)) {
                                throw new RuntimeException($linkResult['error'] ?? 'Không thể liên kết chất thải.');
                            }
                            $stats['wastes_linked']++;
                        }
                    }
                }
                
                // 3. Parse lịch thu gom từ các cột tuần
                foreach ($scheduleColumns as $colIndex) {
                    if (isset($row[$colIndex])) {
                        $scheduleValue = trim($row[$colIndex]);
                        
                        if (!empty($scheduleValue)) {
                            $scheduleDate = parseExcelDate($scheduleValue);
                            
                            if ($scheduleDate) {
                                $existingSchedules = krudApiList(API_TABLE_SCHEDULES, [
                                    ['field' => 'customer_id', 'operator' => '=', 'value' => (int) $customerId],
                                    ['field' => 'schedule_date', 'operator' => '=', 'value' => $scheduleDate],
                                ], [], 1, 1);
                                if (empty($existingSchedules['data'])) {
                                    $scheduleResult = krudApi('insert', API_TABLE_SCHEDULES, [
                                        'customer_id' => (int) $customerId,
                                        'schedule_date' => $scheduleDate,
                                        'status' => 'scheduled',
                                    ]);
                                    if (!($scheduleResult['success'] ?? false)) {
                                        throw new RuntimeException($scheduleResult['error'] ?? 'Không thể tạo lịch thu gom.');
                                    }
                                    $stats['schedules_created']++;
                                }
                            }
                        }
                    }
                }
                
            } catch (Exception $e) {
                echo "  ❌ Lỗi: " . $e->getMessage() . "\n";
                $stats['errors'][] = "Row $rowIndex: " . $e->getMessage();
            }
        } else {
            $stats['customers_skipped']++;
        }
    }
    
    echo "\n";
}

// =====================================================
// BƯỚC 3: THỐNG KÊ KẾT QUẢ
// =====================================================
echo "\n";
echo "==========================================================\n";
echo "    KẾT QUẢ IMPORT\n";
echo "==========================================================\n";
echo ($dryRun ? "✅ Khách hàng đủ điều kiện import: " : "✅ Khách hàng đã import: ") . $stats['customers_imported'] . "\n";
echo "✅ Chất thải đã liên kết: " . $stats['wastes_linked'] . "\n";
echo "✅ Lịch thu gom đã tạo: " . $stats['schedules_created'] . "\n";
if ($dryRun) {
    echo "⚠️  Mã chất thải chưa có trong danh mục: " . count($stats['waste_codes_missing']) . " mã duy nhất\n";
}

if ($stats['customers_skipped'] > 0) {
    echo "⚠️  Khách hàng bỏ qua: " . $stats['customers_skipped'] . "\n";
}

if (count($stats['errors']) > 0) {
    echo "\n❌ LỖI (" . count($stats['errors']) . "):\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n✅ HOÀN THÀNH!\n";

// =====================================================
// HÀM HỖ TRỢ
// =====================================================

/**
 * Tạo mã khách hàng từ tên công ty
 */
function generateCustomerCode($companyName) {
    // Lấy chữ cái đầu của mỗi từ
    $words = preg_split('/\s+/', $companyName);
    $code = '';
    
    foreach ($words as $word) {
        if (strlen($word) > 0) {
            $code .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($code) >= 5) break;
    }
    
    // Thêm timestamp để đảm bảo unique
    $code .= '-' . substr(time(), -4);
    
    return $code;
}

/**
 * Parse mã chất thải từ chuỗi
 */
function parseWasteCodes($wastesStr) {
    if (empty($wastesStr)) {
        return [];
    }
    
    $codes = [];
    
    // Tìm tất cả các pattern XX XX XX (mã chất thải)
    preg_match_all('/(\d{2}\s+\d{2}\s+\d{2})/', $wastesStr, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $code) {
            $codes[] = $code;
        }
    }
    
    return array_unique($codes);
}

/**
 * Parse ngày từ Excel (serial number hoặc text)
 */
function parseExcelDate($dateValue) {
    if (empty($dateValue)) {
        return null;
    }
    
    // Nếu là số (Excel date serial)
    if (is_numeric($dateValue)) {
        $excelEpoch = 25569; // Days between 1900-01-01 and 1970-01-01
        $unixTimestamp = ($dateValue - $excelEpoch) * 86400;
        return date('Y-m-d', $unixTimestamp);
    }
    
    // Nếu là text DD/MM/YYYY hoặc DD\\/MM\\/YYYY
    if (preg_match('/(\d{1,2})[\/\\\](\d{1,2})[\/\\\](\d{4})/', $dateValue, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        return "$year-$month-$day";
    }
    
    return null;
}
