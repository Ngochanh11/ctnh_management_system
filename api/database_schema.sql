-- =====================================================
-- DATABASE SCHEMA - HỆ THỐNG IN CHỨNG TỪ CHẤT THẢI
-- Version: 2.0
-- Cập nhật: 2026-10-01
-- =====================================================

-- Tạo và sử dụng database
CREATE DATABASE IF NOT EXISTS ctnh_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ctnh_management;

-- =====================================================
-- 1. BẢNG KHÁCH HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) UNIQUE COMMENT 'Mã khách hàng tự động',
    company_name VARCHAR(255) NOT NULL COMMENT 'Tên công ty',
    address TEXT COMMENT 'Địa chỉ đầy đủ',
    phone VARCHAR(50) COMMENT 'Số điện thoại',
    email VARCHAR(100) COMMENT 'Email',
    tax_code VARCHAR(50) COMMENT 'Mã số thuế',
    contract_date DATE COMMENT 'Ngày ký hợp đồng',
    contract_expiry_date DATE COMMENT 'Ngày hết hạn hợp đồng',
    frequency VARCHAR(50) COMMENT 'Tần suất: Hàng tuần, Ký mới, 1 lần/tuần',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    notes TEXT COMMENT 'Ghi chú',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_name (company_name(100)),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. BẢNG LOẠI CHẤT THẢI
-- =====================================================
CREATE TABLE IF NOT EXISTS waste_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waste_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Mã chất thải: 18 02 01, 08 02 04',
    waste_name VARCHAR(255) NOT NULL COMMENT 'Tên chất thải',
    category ENUM('hazardous', 'industrial', 'other') DEFAULT 'hazardous' COMMENT 'Loại: nguy hại, công nghiệp, khác',
    unit VARCHAR(20) DEFAULT 'kg' COMMENT 'Đơn vị: kg, tấn, m3',
    price_per_unit DECIMAL(15,2) COMMENT 'Giá mỗi đơn vị',
    description TEXT COMMENT 'Mô tả chi tiết',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_waste_code (waste_code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. BẢNG CHẤT THẢI CỦA KHÁCH HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS customer_wastes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    waste_type_id INT NOT NULL,
    estimated_quantity DECIMAL(15,2) COMMENT 'Khối lượng ước tính',
    unit VARCHAR(20) DEFAULT 'kg',
    notes TEXT COMMENT 'Ghi chú',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (waste_type_id) REFERENCES waste_types(id),
    UNIQUE KEY unique_customer_waste (customer_id, waste_type_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. BẢNG LỊCH THU GOM
-- =====================================================
CREATE TABLE IF NOT EXISTS collection_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    schedule_date DATE NOT NULL COMMENT 'Ngày dự kiến thu gom',
    week_number INT COMMENT 'Tuần thứ mấy trong tháng (1-4)',
    month INT COMMENT 'Tháng',
    year INT COMMENT 'Năm',
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    actual_date DATE COMMENT 'Ngày thu gom thực tế',
    notes TEXT COMMENT 'Ghi chú',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_schedule_date (schedule_date),
    INDEX idx_customer_date (customer_id, schedule_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. BẢNG CHỨNG TỪ THU GOM
-- =====================================================
CREATE TABLE IF NOT EXISTS collection_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Mã chứng từ',
    schedule_id INT COMMENT 'Liên kết với lịch thu gom',
    customer_id INT NOT NULL,
    collection_date DATE NOT NULL COMMENT 'Ngày thu gom',
    document_type ENUM('receipt', 'delivery_note', 'invoice') DEFAULT 'receipt' COMMENT 'Loại: phiếu thu, biên bản, hóa đơn',
    total_weight DECIMAL(15,2) COMMENT 'Tổng khối lượng',
    unit VARCHAR(20) DEFAULT 'kg',
    driver_name VARCHAR(100) COMMENT 'Tên lái xe',
    vehicle_number VARCHAR(50) COMMENT 'Số xe',
    customer_signature BOOLEAN DEFAULT FALSE COMMENT 'KH đã ký',
    company_signature BOOLEAN DEFAULT FALSE COMMENT 'CT đã ký',
    status ENUM('draft', 'pending', 'approved', 'cancelled') DEFAULT 'draft',
    pdf_path VARCHAR(255) COMMENT 'Đường dẫn file PDF',
    notes TEXT COMMENT 'Ghi chú',
    created_by INT COMMENT 'User ID tạo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES collection_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_document_date (collection_date),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. BẢNG CHI TIẾT CHỨNG TỪ
-- =====================================================
CREATE TABLE IF NOT EXISTS document_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    waste_type_id INT NOT NULL,
    quantity DECIMAL(15,2) NOT NULL COMMENT 'Khối lượng',
    unit VARCHAR(20) DEFAULT 'kg',
    unit_price DECIMAL(15,2) COMMENT 'Đơn giá',
    total_price DECIMAL(15,2) COMMENT 'Thành tiền',
    notes TEXT COMMENT 'Ghi chú',
    FOREIGN KEY (document_id) REFERENCES collection_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (waste_type_id) REFERENCES waste_types(id),
    INDEX idx_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DỮ LIỆU MẪU CHO WASTE_TYPES
-- =====================================================
INSERT IGNORE INTO waste_types (waste_code, waste_name, category, unit, description) VALUES
('05 01 01', 'Bùn đáy từ bể chứa dầu', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 01 01', 'Cặn sơn', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 02 01', 'Mực in thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 02 02', 'Bản mực thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 02 04', 'Hộp mực in thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 03 01', 'Keo thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('08 04 01', 'PU thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('09 01 01', 'Mực chai, phao, dầu mộng, gỗ thưa', 'hazardous', 'kg', 'Chất thải nguy hại'),
('13 01 01', 'Kim, băng băng, góc y tế', 'hazardous', 'kg', 'Chất thải nguy hại'),
('13 01 05', 'Dầu thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('13 02 04', 'Dầu động cơ', 'hazardous', 'kg', 'Chất thải nguy hại'),
('16 01 06', 'Bóng đèn huỳnh quang', 'hazardous', 'kg', 'Chất thải nguy hại'),
('17 01 06', 'Dầu thủy lực tổng hợp thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('17 02 03', 'Dầu đông cơ, hợp số và bôi trơn tổng hợp thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('17 02 04', 'Các loại dầu đông cơ, hợp số và bôi trơn thải khác', 'hazardous', 'kg', 'Chất thải nguy hại'),
('18 01 01', 'Bao bì mềm thải (nhiễm hóa chất)', 'hazardous', 'kg', 'Chất thải nguy hại'),
('18 01 02', 'Bao bì cứng thải bằng kim loại', 'hazardous', 'kg', 'Chất thải nguy hại'),
('18 01 03', 'Bao bì cứng thải bằng nhựa', 'hazardous', 'kg', 'Chất thải nguy hại'),
('18 02 01', 'Chất hấp thu, vật liệu lọc, giẻ lau, vải bảo vệ thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('19 02 05', 'Thiết bị điện tử thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('19 02 08', 'Pin mất trái (tấm quang năng thải)', 'hazardous', 'kg', 'Chất thải nguy hại'),
('19 06 01', 'Pin, ắc quy chì thải', 'hazardous', 'kg', 'Chất thải nguy hại'),
('19 12 03', 'Than hoạt tính', 'hazardous', 'kg', 'Chất thải nguy hại');


-- =====================================================
-- 7. BẢNG USERS - QUẢN LÝ TÀI KHOẢN
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT 'Tên đăng nhập',
    password VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã hash',
    full_name VARCHAR(100) NOT NULL COMMENT 'Họ tên đầy đủ',
    email VARCHAR(100) COMMENT 'Email',
    phone VARCHAR(20) COMMENT 'Số điện thoại',
    role ENUM('admin', 'user') DEFAULT 'user' COMMENT 'Vai trò: admin/user',
    permissions JSON COMMENT 'Quyền chi tiết: {"customers": {"view": true, "create": true, "edit": false, "delete": false}}',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL COMMENT 'Lần đăng nhập cuối',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert admin mặc định (password: admin123)
INSERT INTO users (username, password, full_name, role, permissions) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin', 
'{"customers": {"view": true, "create": true, "edit": true, "delete": true}, 
  "wastes": {"view": true, "create": true, "edit": true, "delete": true},
  "documents": {"view": true, "create": true, "edit": true, "delete": true, "print": true},
  "reports": {"view": true, "export": true},
  "settings": {"view": true, "edit": true}}')
ON DUPLICATE KEY UPDATE username = username;

-- =====================================================
-- 8. BẢNG THÔNG TIN THUẾ (TRA CỨU MST)
-- =====================================================
CREATE TABLE IF NOT EXISTS tax_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    tax_code VARCHAR(50) UNIQUE COMMENT 'Mã số thuế',
    company_name_official VARCHAR(255) COMMENT 'Tên công ty chính thức theo MST',
    address_official TEXT COMMENT 'Địa chỉ chính thức theo MST',
    representative VARCHAR(100) COMMENT 'Người đại diện pháp luật',
    verification_date DATE COMMENT 'Ngày tra cứu/xác thực',
    verified BOOLEAN DEFAULT FALSE COMMENT 'Đã xác thực MST',
    notes TEXT COMMENT 'Ghi chú',
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_tax_code (tax_code),
    INDEX idx_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. BẢNG MẪU PHÔI IN
-- =====================================================
CREATE TABLE IF NOT EXISTS print_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Mã mẫu: PHOI_01, PHOI_02',
    template_name VARCHAR(100) NOT NULL COMMENT 'Tên mẫu phôi',
    description TEXT COMMENT 'Mô tả',
    paper_size VARCHAR(20) DEFAULT 'A4' COMMENT 'Khổ giấy: A4, A5, Custom',
    rows_per_page INT DEFAULT 10 COMMENT 'Số dòng tối đa/trang',
    template_file VARCHAR(255) COMMENT 'File template HTML/CSS',
    is_default BOOLEAN DEFAULT FALSE COMMENT 'Mẫu mặc định',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_code (template_code),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert template mặc định
INSERT INTO print_templates (template_code, template_name, description, rows_per_page, is_default) VALUES
('PHOI_3_LIEN', 'Phôi 3 liên - Chất thải nguy hại', 'Mẫu phôi in 3 liên cho chứng từ chất thải nguy hại', 10, TRUE),
('PHOI_CN', 'Phôi rác công nghiệp', 'Mẫu phôi in cho rác công nghiệp', 10, FALSE)
ON DUPLICATE KEY UPDATE template_code = template_code;

-- =====================================================
-- 10. BẢNG LỊCH SỬ IN ẤN
-- =====================================================
CREATE TABLE IF NOT EXISTS print_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL COMMENT 'ID chứng từ',
    user_id INT COMMENT 'Người thực hiện in',
    template_id INT COMMENT 'Mẫu phôi sử dụng',
    print_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian in',
    page_count INT DEFAULT 1 COMMENT 'Số trang đã in',
    notes TEXT COMMENT 'Ghi chú',
    FOREIGN KEY (document_id) REFERENCES collection_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES print_templates(id) ON DELETE SET NULL,
    INDEX idx_document (document_id),
    INDEX idx_user (user_id),
    INDEX idx_print_date (print_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. BẢNG CÀI ĐẶT HỆ THỐNG
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'Khóa cài đặt',
    setting_value TEXT COMMENT 'Giá trị',
    description TEXT COMMENT 'Mô tả',
    data_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert cài đặt mặc định
INSERT INTO system_settings (setting_key, setting_value, description, data_type) VALUES
('company_name', 'Công ty TNHH Môi Trường CTNH', 'Tên công ty', 'string'),
('company_address', '', 'Địa chỉ công ty', 'string'),
('company_phone', '', 'Số điện thoại công ty', 'string'),
('company_email', '', 'Email công ty', 'string'),
('tax_api_enabled', 'true', 'Bật/tắt API tra cứu MST tự động', 'boolean'),
('default_rows_per_page', '10', 'Số dòng mặc định mỗi trang in', 'number'),
('auto_backup', 'true', 'Tự động sao lưu dữ liệu', 'boolean')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- 12. CẬP NHẬT BẢNG CUSTOMERS - THÊM MÃ SỐ THUẾ
-- =====================================================
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS representative VARCHAR(100) COMMENT 'Người đại diện' AFTER email,
ADD COLUMN IF NOT EXISTS mst_verified BOOLEAN DEFAULT FALSE COMMENT 'MST đã xác thực' AFTER tax_code;

-- =====================================================
-- 13. INDEX TỐI ỨU PERFORMANCE
-- =====================================================
ALTER TABLE collection_documents 
ADD INDEX IF NOT EXISTS idx_document_code (document_code),
ADD INDEX IF NOT EXISTS idx_created_by (created_by);

ALTER TABLE document_items 
ADD INDEX IF NOT EXISTS idx_waste_type (waste_type_id);

-- =====================================================
-- KẾT THÚC SCHEMA
-- =====================================================
