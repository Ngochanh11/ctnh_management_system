<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('documents', 'print') or die('Không có quyền truy cập');
include '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-print me-2"></i>Hướng dẫn cài đặt và in</h1>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Yêu cầu máy in</strong></div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Máy in kim có bộ kéo giấy tractor-feed, hỗ trợ giấy liên tục/đục lỗ và loại phôi nhiều liên đang sử dụng.</li>
            <li>Cài driver đúng model từ nhà sản xuất, kết nối USB hoặc mạng và xác nhận Windows nhận đúng khổ giấy.</li>
            <li>Phôi phải đúng chiều, số liên và kích thước thực tế; không thay thế bằng giấy A4 nếu kích thước khác.</li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Thiết lập trong hộp thoại in</strong></div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Mở chứng từ hoặc danh sách in hàng loạt, sau đó chọn <strong>In</strong>.</li>
            <li>Chọn đúng máy in kim, đường cấp giấy tractor và cỡ giấy tùy chỉnh khớp kích thước phôi đã đo.</li>
            <li>Đặt tỷ lệ <strong>100%</strong> hoặc <strong>Kích thước thực</strong>; tắt đầu trang/chân trang của trình duyệt và không bật “Fit to page”.</li>
            <li>Chọn chiều giấy và lề theo phôi, tắt chế độ nhiều trang trên một tờ; đặt số bản in phù hợp quy trình phôi liên của máy in.</li>
            <li>In thử trên giấy nháp, so vị trí mã/thông tin với phôi thật, điều chỉnh vị trí nạp giấy/độ lệch rồi mới in phôi.</li>
        </ol>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Khi bản in bị lệch hoặc thiếu nội dung</strong></div>
    <div class="card-body">
        <p>Kiểm tra cỡ giấy tùy chỉnh, tỷ lệ 100%, lề, chiều phôi và đường cấp tractor. Nếu lệch đều, chỉnh vị trí bắt đầu trang/độ nạp trên máy in; nếu lệch tăng dần, kiểm tra bước kéo giấy và driver.</p>
        <p class="mb-0">Căn chính xác các trường trên phôi 3 liên cần có bản phôi thật hoặc bản scan đúng tỷ lệ cùng model máy in để đo và chạy thử tại nơi lắp đặt.</p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>