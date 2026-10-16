# Hệ thống quản lý và in chứng từ chất thải nguy hại

Ứng dụng web hỗ trợ quản lý khách hàng, danh mục chất thải, chứng từ thu gom và in chứng từ chất thải nguy hại (CTNH). Giao diện được thiết kế để sử dụng trên máy tính và hỗ trợ in theo khổ A4.

## Tính năng

- Đăng nhập, quản lý tài khoản và phân quyền theo nghiệp vụ.
- Quản lý thông tin khách hàng và tra cứu thông tin doanh nghiệp theo mã số thuế.
- Nhập danh sách khách hàng từ Excel.
- Quản lý danh mục chất thải và chất thải theo từng khách hàng.
- Tạo, cập nhật và theo dõi chứng từ thu gom CTNH.
- In chứng từ theo khách hàng hoặc in hàng loạt chứng từ.
- Chia danh sách chất thải thành các trang in, tối đa 10 dòng mỗi trang.
- Xem báo cáo, lịch thu gom và lịch sử in.
- Có hướng dẫn thiết lập máy in và giấy phôi.

## Công nghệ

- PHP 7.4 trở lên
- Apache (có thể chạy bằng XAMPP)
- KRUD API để lưu trữ và truy xuất dữ liệu
- Bootstrap 5, JavaScript và CSS
- VietQR API để tra cứu thông tin doanh nghiệp theo mã số thuế

Ứng dụng không sử dụng cơ sở dữ liệu MySQL cài tại máy. Dữ liệu được gửi tới KRUD API cấu hình trong ứng dụng. Cần kết nối Internet để dùng API và tải các tài nguyên giao diện bên ngoài.

## Cài đặt chạy local bằng XAMPP

1. Cài đặt XAMPP có PHP 7.4 trở lên và bật Apache.
2. Clone hoặc giải nén repository vào thư mục `C:\xampp\htdocs\ctnh_management_system`.
3. Mở `api\includes\config.php` và kiểm tra các cấu hình:
   - `KRUD_API_URL`: địa chỉ KRUD API của đơn vị.
   - `KRUD_JS_URL` và `LIST_JS_URL`: thư viện JavaScript của KRUD API.
   - `KRUD_TENANT_ORIGIN`: origin của tenant nếu máy chủ API yêu cầu.
   - `APP_ROOT_URL`: URL gốc của ứng dụng trên máy chủ web.
4. Đảm bảo PHP đã bật extension cURL.
5. Truy cập `http://localhost/ctnh_management_system/`. Trang gốc sẽ chuyển đến ứng dụng trong thư mục `api`.
6. Nếu máy chủ KRUD chưa có tài khoản người dùng, mở `http://localhost/ctnh_management_system/api/setup_api.php` để tạo tài khoản quản trị.
7. Đăng nhập bằng thông tin quản trị vừa tạo.

Trang khởi tạo chỉ tạo tài khoản quản trị khi API chưa có người dùng. Không có mật khẩu mặc định. Khi tài khoản đầu tiên được tạo, ứng dụng có thể tạo dữ liệu mẫu cho các bảng nghiệp vụ còn trống.

## Sử dụng

1. Đăng nhập bằng tài khoản được cấp.
2. Tạo hoặc nhập khách hàng, sau đó khai báo chất thải của từng khách hàng.
3. Tạo chứng từ thu gom và khai báo các chất thải, số lượng tương ứng.
4. Chọn chức năng in từ danh sách khách hàng, trang chất thải khách hàng hoặc trang in hàng loạt.
5. Trong hộp thoại in, chọn đúng máy in và khổ giấy. Để căn phôi, xem mục **Hướng dẫn in** trong ứng dụng.

Quyền truy cập các chức năng được kiểm soát theo vai trò và phân quyền tài khoản. Liên hệ quản trị viên nếu tài khoản không thấy chức năng cần sử dụng.

## Cấu trúc dự án

```text
ctnh_management_system/
├── api/
│   ├── includes/       # Cấu hình, xác thực và kết nối KRUD API
│   ├── modules/        # Mẫu in chứng từ
│   ├── pages/          # Các trang nghiệp vụ
│   ├── index.php       # Đăng nhập
│   └── setup_api.php   # Khởi tạo tài khoản quản trị
├── assets/
│   └── css/            # Giao diện ứng dụng
├── js/                 # JavaScript dùng chung
├── index.html          # Chuyển hướng vào ứng dụng
└── README.md
```

## Lưu ý khi triển khai

- Cấu hình URL và tenant phù hợp môi trường trước khi triển khai.
- Chỉ dùng HTTPS khi đưa ứng dụng ra Internet.
- Không commit mật khẩu, token, thông tin đăng nhập hoặc dữ liệu khách hàng thật lên repository.
- Dữ liệu khách hàng và chứng từ được lưu trên KRUD API; cần được cấp quyền truy cập API tương ứng.
- Thông tin lấy từ dịch vụ tra cứu mã số thuế chỉ nên dùng để tham khảo và cần được đối chiếu trước khi phát hành chứng từ.
- In thử trên giấy nháp để căn lề và vị trí trước khi in lên phôi chính thức. Kết quả căn chỉnh phụ thuộc máy in, driver và kích thước giấy thực tế.

## Giấy phép

Chưa khai báo giấy phép phân phối trong repository. Vui lòng liên hệ chủ sở hữu repository trước khi sao chép, phân phối hoặc sử dụng lại dự án ngoài phạm vi được cho phép.
