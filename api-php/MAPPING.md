# Ánh xạ backend Node.js → PHP (đến tiến độ hiện tại)

## 1. Bảng đối chiếu

| Node (`api/server.js`) | PHP (`api-php/`) |
|---|---|
| `express()`, `app.listen(3000)` | `index.php` – chạy `php -S localhost:3000 index.php` (giữ cổng 3000) |
| `app.use(cors())` | 4 dòng `header('Access-Control-...')` + trả 204 cho `OPTIONS` trong `index.php` |
| `app.use(express.json())` | `$body = json_decode(file_get_contents('php://input'), true)` |
| `new sql.ConnectionPool(config)` | `config.php` → hàm `db()` dùng PDO + `pdo_sqlsrv` |
| `app.get('/api/thuoc/danhsach', ...)` | `handlers.php` → `getDanhSachThuoc()` + dòng trong `$routes` |
| `app.get('/api/kho/danhsach', ...)` | `handlers.php` → `getDanhSachKho()` + dòng trong `$routes` |
| `pool.request().query(\`...\`)` | `db()->query("...")->fetchAll(PDO::FETCH_ASSOC)` |
| `res.json(result.recordset)` | `echo json_encode($rows, JSON_UNESCAPED_UNICODE)` |
| `res.status(500).json({error: err.message})` | `catch` → `http_response_code(500)` + `{"error": ...}` |

**Giữ nguyên:** URL, cổng 3000, tên trường JSON, câu SQL → các trang `index/products/inventory.html` không phải sửa gì (trừ 1 dòng của `pos.html`, mục 3).

**Khác biệt đã xử lý:** Node trả ngày dạng ISO (`2027-04-26T00:00:00.000Z`) và số nguyên dạng số; PDO trả ngày dạng chuỗi và số có thể là chuỗi → `isoDate()` và `(int)` trong `handlers.php` ép về đúng định dạng cũ.

## 2. Cài driver SQL Server cho PHP (bước dễ vướng nhất)

CSDL của nhóm là **SQL Server**, nên PHP cần extension `pdo_sqlsrv` (XAMPP/Laragon **không** có sẵn):
1. Cài *ODBC Driver 18 for SQL Server* của Microsoft.
2. Tải *Microsoft Drivers for PHP for SQL Server*, chọn file `php_pdo_sqlsrv_<phiên bản PHP>_<ts|nts>_x64.dll` khớp phiên bản PHP của bạn (`php -v`; XAMPP thường là *ts*), chép vào thư mục `ext/`.
3. Thêm vào `php.ini`: `extension=php_pdo_sqlsrv_82_ts_x64.dll` (đổi tên đúng file), rồi chạy `php -m` kiểm tra thấy `pdo_sqlsrv`.
4. Điền mật khẩu vào `config.php` rồi chạy `php -S localhost:3000 index.php`.

Cần PHP ≥ 8.0. Bản này mình đã kiểm tra cú pháp, routing, CORS và định dạng ngày; **chưa chạy được với SQL Server thật của bạn**, nên lần chạy đầu bạn hãy mở `http://localhost:3000/api/thuoc/danhsach` để xem kết quả.

## 3. Phát hiện khi đọc code (cần nhóm biết)

1. **`pos.html` gọi sai URL.** Nó gọi `/api/thuoc` nhưng server chỉ có `/api/thuoc/danhsach` (đã sửa 1 dòng trong `pos.html` kèm theo).
2. **Bảng `THUOC` chưa có cột giá.** POS đang dùng giá mặc định 25.000đ; `products.html` ghi "Liên hệ". Nhóm cần thống nhất thêm cột `GiaBan` (hoặc bảng giá) vào CSDL, mình không tự đổi schema.
3. **Logic nghiệp vụ nằm trong CSDL** (trigger hạn dùng ≥ 6 tháng, khóa lô hết hạn, bắt buộc đơn thuốc, `sp_XuatKho_FEFO`), nên backend PHP chỉ cần gọi, không phải viết lại. Phần chưa làm, để nhóm phát triển tiếp:
   - Thanh toán: gọi `EXEC sp_XuatKho_FEFO @MaThuoc, @SoLuongYeuCau, @MaHoaDon, @DonGia`.
   - Đăng nhập: `sp_CreateNhanVien` lưu hash dạng `sha256(mậtkhẩu+salt):salt` với chuỗi **NVARCHAR (UTF-16LE)**, nên khi kiểm tra bằng PHP phải `hash('sha256', mb_convert_encoding($pass.$salt, 'UTF-16LE', 'UTF-8'))`.
4. **Mật khẩu `sa` đang nằm cứng ở 5 file Node** (server + 4 seeder). Bản PHP gom về `config.php`; nhớ thêm file này vào `.gitignore` hoặc để mỗi người tự điền.
5. `seeder/*.js` là script nạp dữ liệu mẫu chạy một lần, mình **giữ nguyên bằng Node** (không thuộc backend của đồ án). Cần thì mình chuyển sang PHP sau.
