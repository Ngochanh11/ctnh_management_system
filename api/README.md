# CTNH Management System

Hệ thống quản lý Chất Thải Nguy Hại (CTNH) cho công ty xử lý chất thải.

## 🚀 Tính năng

- ✅ Quản lý người dùng và phân quyền
- ✅ Quản lý khách hàng (cá nhân/công ty)
- ✅ Nhập khách hàng từ Excel có xem trước
- ✅ Tra cứu MST theo mã số thuế qua VietQR và lưu vào danh mục
- ✅ **Quản lý chất thải của khách hàng**
- ✅ **In chứng từ chất thải nguy hại**
- ✅ In hàng loạt và tự chia trang, tối đa 10 dòng chất thải/trang
- ✅ Báo cáo theo ngày, tháng và khách hàng; tra cứu lịch sử in
- ✅ Quản lý nhân viên và phòng ban
- ✅ Dashboard thống kê
- ✅ Giao diện responsive (Bootstrap 5)

## 📋 Yêu cầu hệ thống

- PHP 7.4+
- Apache/Nginx với mod_rewrite
- XAMPP hoặc môi trường web server
- **Kết nối Internet** (để sử dụng KRUD API)

## Cài đặt

1. Đặt thư mục dự án tại `c:\xampp\htdocs\ctnh_management_system\`.
2. Bật Apache trong XAMPP. Ứng dụng không cần MySQL local.
3. Mở `http://localhost/ctnh_management_system/`. File `index.html` sẽ chuyển tới ứng dụng PHP trong `api/`.
4. Nếu CSDL API chưa có user, mở `http://localhost/ctnh_management_system/api/setup_api.php` để khởi tạo tài khoản quản trị trên server.

Ứng dụng gửi thao tác dữ liệu tới KRUD/LIST API được cấu hình trong `api/includes/config.php`. Trang setup yêu cầu tự nhập thông tin admin; không có mật khẩu mặc định.

## 📖 Hướng dẫn sử dụng

### 1. Quản lý khách hàng

1. Vào menu **"Khách hàng"**
2. Click **"Thêm khách hàng"** để tạo mới
3. Điền thông tin:
   - Họ và tên
   - Công ty
   - Email, điện thoại
   - Loại khách hàng: Cá nhân hoặc Công ty
   - Địa chỉ, mã số thuế
   - Đánh dấu VIP nếu cần
4. Click **"Lưu"**

#### Nhập khách hàng từ Excel

1. Trong trang **Khách hàng**, chọn **Nhập Excel**.
2. Tải file `.xlsx` có hàng tiêu đề và cột tên công ty/khách hàng; có thể kèm địa chỉ, điện thoại và MST.
3. Kiểm tra bảng xem trước, sau đó chọn **Xác nhận nhập**.
4. Hệ thống ghép khách hàng theo tên đã chuẩn hóa, cập nhật các trường có dữ liệu và tạo khách hàng chưa tồn tại. Trường để trống trong Excel không ghi đè dữ liệu hiện có.

Mỗi lần nhập tối đa 2.000 dòng, file tối đa 10 MB. Nên định dạng cột MST là **Text** trong Excel để giữ nguyên số 0 đầu. Luồng này chỉ nhập thông tin khách hàng; không nhập lịch thu gom hay danh sách chất thải.

Tra cứu MST yêu cầu nhập mã số thuế 10 hoặc 13 chữ số. API VietQR tra cứu theo MST; hiện không hỗ trợ tìm MST ngược chỉ bằng tên doanh nghiệp. Kết quả là dữ liệu tham khảo từ nguồn được API công bố, cần đối chiếu hồ sơ chính thức trước khi phát hành chứng từ.

### 2. Quản lý chất thải của khách hàng

1. Từ danh sách khách hàng, click nút **🗑️** (Quản lý chất thải)
2. Click **"Thêm chất thải"**
3. Nhập thông tin:
   - **Tên chất thải** (bắt buộc)
   - **Mã CTNH** (bắt buộc)
   - **Trạng thái**: Rắn / Lỏng / Bùn (bắt buộc)
   - **Số lượng (kg)** (bắt buộc)
   - **Phương pháp xử lý** (tùy chọn)
   - Ghi chú
4. Click **"Lưu"**
5. Hệ thống sẽ tự động tính tổng số lượng

### 3. In chứng từ chất thải nguy hại

#### Cách 1: Từ danh sách khách hàng
- Click nút **🖨️** (In chứng từ) ở hàng khách hàng cần in

#### Cách 2: Từ trang quản lý chất thải
1. Vào **Quản lý chất thải** của khách hàng
2. Click nút **"In chứng từ"** ở góc trên bên phải
3. Trang in sẽ hiển thị:
   - ✓ Thông tin công ty HWASE
   - ✓ Thông tin khách hàng
   - ✓ Bảng danh sách chất thải
   - ✓ Dấu tick (✓) theo trạng thái (Rắn/Lỏng/Bùn)
   - ✓ Tổng số lượng chất thải
   - ✓ Phần chữ ký và dấu
4. Click **"🖨️ In chứng từ"** để in

#### Cấu hình máy in

Mở mục **Hướng dẫn in** trong menu nghiệp vụ. Với phôi liên tục, dùng máy in kim có tractor-feed, khai báo cỡ giấy khớp phôi, đặt tỷ lệ 100%/kích thước thực, tắt đầu trang/chân trang và in thử trên giấy nháp. Căn chính xác vị trí trên phôi 3 liên cần bản phôi thật và model máy in để chạy thử.

## 🔧 Phương pháp xử lý chất thải

| Mã | Phương pháp |
|----|-------------|
| TG | Tái sử dụng |
| PT | Phục hồi/tái chế/lấy lại |
| DH | Đốt hủy |
| ST | Sử dụng làm nhiên liệu |
| TĐ | Thải đúng |
| HR | Trộn hợp |
| CL | Cô lập/bảng kín |
| C  | Chôn lấp |
| SC | Sơ chế |

## 📁 Cấu trúc thư mục

```
ctnh_management_system/
├── assets/
├── js/
├── api/
│   ├── includes/
│   ├── modules/
│   ├── pages/
│   ├── index.php
│   ├── setup_api.php
│   └── ... PHP endpoints and support files
└── index.html
```

The root contains only `assets/`, `js/`, `api/`, and `index.html`. PHP pages, configuration, setup scripts, schema, and import utilities are kept under `api/`.

## 🗄️ Database Tables (Tự động tạo qua API)

### Cách hoạt động của KRUD API

Hệ thống **KHÔNG CẦN tạo bảng thủ công**. KRUD API tự động:
- Tạo bảng khi insert lần đầu
- Thêm cột khi gặp field mới
- Tự động index các trường cần thiết

### ctnh_customer_waste

Cấu trúc tự động tạo:

```javascript
{
  customer_id: 1,              // ID khách hàng
  waste_name: 'Chất thải A',   // Tên chất thải
  waste_code: 'ABC123',        // Mã CTNH
  state: 'solid',              // solid/liquid/sludge
  quantity: 180.01,            // Số lượng (kg)
  treatment_method: 'DH',      // Phương pháp xử lý
  notes: 'Ghi chú'
}
```

## 📚 KRUD API - Cách sử dụng

### Insert dữ liệu

```javascript
sendFormDataKRUD('insert', 'ctnh_customer_waste', null, '#wasteForm')
    .then(res => {
        if (res.success) {
            console.log('ID mới:', res.data.id);
        }
    });
```

### Update dữ liệu

```javascript
sendFormDataKRUD('update', 'ctnh_customer_waste', 123, '#wasteForm')
    .then(res => {
        if (res.success) {
            console.log('Cập nhật thành công');
        }
    });
```

### Lấy danh sách

```javascript
getKrudList({
    table: 'ctnh_customer_waste',
    where: { customer_id: 5 },
    sort: { id: 'ASC' }
}).then(res => {
    if (res.success) {
        console.log('Dữ liệu:', res.data);
    }
});
```

## 🎨 Công nghệ sử dụng

- **Backend:** PHP 7.4+
- **Database:** KRUD API (Cloud)
- **Frontend:** Bootstrap 5.3
- **Icons:** Bootstrap Icons
- **API:** https://kio.dvqt.vn/
- **Font:** Times New Roman (cho chứng từ)

## ❓ Xử lý sự cố

### Không có dữ liệu
**Giải pháp:** Mở `http://localhost/ctnh_management_system/api/setup_api.php` nếu CSDL API chưa có user.

### KRUD API lỗi kết nối
**Giải pháp:** 
- Kiểm tra internet
- Thử truy cập https://kio.dvqt.vn/

### Không đăng nhập được
**Giải pháp:** Tạo user admin qua Console (xem phần Cài đặt bước 4)

### In chứng từ trống
**Giải pháp:** Thêm chất thải cho khách hàng trước khi in

### "Table not found"
**Giải pháp:** Kiểm tra kết nối KRUD API và xác nhận tài khoản đã được khởi tạo trên server.

## 📞 Hỗ trợ

Khi cần hỗ trợ, cung cấp:
1. ✅ Log từ Console (F12)
2. ✅ Network tab để xem API calls
3. ✅ Screenshots lỗi
4. ✅ Phiên bản PHP

## 📄 License

Copyright © 2024-2026 CTNH Management System.

---

**Phiên bản:** 2.0 (KRUD API)  
**Cập nhật:** Sử dụng KRUD API thay vì MySQL trực tiếp  
**Ngày:** 2026
