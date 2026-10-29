# PharmaPro – Ngữ cảnh dự án cho AI

> **Cách dùng:** mỗi lần nhờ AI làm việc, đính kèm 3 thứ: file này, `css/theme.css` và `_template.html` (cùng file trang của bạn nếu đã có).
> Hợp đồng API ở mục 6 do trưởng nhóm quản lý. Muốn đổi API thì sửa mục này trước, rồi mới sửa code.

## 1. Dự án và công nghệ

Web quản lý nhà thuốc bán lẻ theo chuẩn GPP (tên PharmaPro). Backend PHP + SQL Server do trưởng nhóm làm. Frontend dùng **HTML + CSS + JavaScript thuần**, Bootstrap 5 (CDN) và FontAwesome 6. **Không dùng** React, Vue, jQuery hay framework khác.
Chạy bằng Docker: giao diện http://localhost:8080, API http://localhost:3000.

## 2. Thư mục và quyền chỉnh sửa

```
frontend/
  _template.html     khung trang mẫu: copy ra để làm trang mới
  css/theme.css      CSS dùng chung (TV3 phụ trách, sửa qua Pull Request)
  js/chatbot.js      khung chat popup dùng chung (TV4 phụ trách, làm ở giai đoạn 3)
  index.html  pos.html  products.html  inventory.html  customers.html  orders.html  users.html
docs/                AI_CONTEXT.md, PROMPT_TEMPLATE.md
api-php/  database/  docker-compose.yml     của trưởng nhóm (KHÔNG sửa)
```
Mỗi người chỉ sửa file được giao:

| Người | File phụ trách |
|---|---|
| Trưởng nhóm | `api-php/`, `database/`, Docker, duyệt Pull Request, hợp đồng API (file này) |
| TV2 | `pos.html` (kèm thuốc thay thế và quét đơn thuốc OCR ở giai đoạn 3) |
| TV3 | `products.html`, `inventory.html`, `users.html`, và `css/theme.css` |
| TV4 | `customers.html`, `orders.html`, `index.html`, và `js/chatbot.js` |

## 3. Quy tắc code (bắt buộc)

1. Mỗi trang là **một file HTML** có `<style>` và `<script>` riêng. Bắt đầu từ `_template.html`, giữ nguyên sidebar, thanh trên và khối hàm dùng chung (`api`, `esc`, `money`, `fmtDate`, `phoneOk`, `trangThaiLo`, `toast`).
2. Chỉ dùng thư viện trong danh sách ở mục 3.1, đúng phiên bản đã ghi. Muốn dùng thêm thư viện phải được trưởng nhóm duyệt và ghi vào mục 3.1 trước.
3. Màu sắc dùng biến CSS (`var(--brand)`...) và **chỉ những class có thật trong `theme.css` đính kèm**, không tự bịa tên class. Thiếu class thì viết trong `<style>` của trang. Không gõ mã màu trực tiếp. Không tự sửa `theme.css` (nhờ TV3).
4. Mọi dữ liệu chèn vào `innerHTML` phải qua `esc()` để tránh lỗi hiển thị và chèn mã độc.
5. Mọi lời gọi API đi qua hàm `api()`, luôn `try/catch` và báo lỗi bằng `toast(e.message, true)`. Câu lỗi từ máy chủ đã là tiếng Việt, hiển thị nguyên văn.
6. **Dữ liệu giả trước, API thật sau:** để dữ liệu giả trong hằng `MOCK_...`, mỗi API bọc trong một hàm có `USE_MOCK ? MOCK : api(...)` (xem mẫu trong `_template.html`). Khi API sẵn sàng chỉ đổi `USE_MOCK = false`.
7. Chữ trên giao diện bằng tiếng Việt, viết hoa chữ cái đầu câu. Tiền: `35.000đ`. Ngày: `dd/mm/yyyy`.
8. Không dùng `localStorage` cho dữ liệu nghiệp vụ. Không tự thêm endpoint hay cột CSDL: thiếu gì thì ghi vào mục "Cần API" để gửi trưởng nhóm.
9. Khi sửa code, **trả về file hoàn chỉnh**, không viết "phần còn lại giữ nguyên".

### 3.1 Thư viện được phép (nhúng bằng CDN, đúng số phiên bản)

| Thư viện | Phiên bản | Đường dẫn | Dùng ở |
|---|---|---|---|
| Bootstrap CSS + JS | 5.3.0 | `https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css` và `.../dist/js/bootstrap.bundle.min.js` | mọi trang (chủ yếu modal và lưới) |
| FontAwesome | 6.4.0 | `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css` | mọi trang (biểu tượng) |
| Chart.js | 4.4.1 | `https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js` | `index.html` (biểu đồ) |
| Tesseract.js | 5.1.1 | `https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js` | `pos.html` (quét đơn thuốc, giai đoạn 3), ngôn ngữ `vie` |
| Fuse.js | 7.0.0 | `https://cdn.jsdelivr.net/npm/fuse.js@7.0.0/dist/fuse.min.js` | `pos.html` (khớp mờ tên thuốc sau OCR); có thể tự viết hàm thay thế |
| Phông Be Vietnam Pro | – | Google Fonts (đã có trong `_template.html`) | mọi trang |

**Vì sao chọn Tesseract.js cho OCR:** chạy hoàn toàn trên trình duyệt, miễn phí, hỗ trợ tiếng Việt, ba bạn frontend tự làm được mà không cần máy chủ. VietOCR và PaddleOCR chính xác hơn với chữ khó đọc nhưng đều là thư viện Python, phải dựng thêm một dịch vụ phía máy chủ (nặng cho Docker và tăng việc backend), nên **không dùng** trong phạm vi đồ án. Giới hạn cần biết: Tesseract.js đọc tốt chữ in, kém chữ viết tay; lần chạy đầu phải tải dữ liệu tiếng Việt nên chậm. Vì vậy phần demo dùng ảnh đơn thuốc in rõ chữ và luôn có cách nhập tay dự phòng.

**File dùng chung duy nhất ngoài `theme.css`:** `js/chatbot.js` (TV4). Nó phải bọc trong một hàm tự gọi (IIFE) và không khai báo biến toàn cục, để không đụng `const API` của các trang. Mỗi trang chỉ thêm đúng một dòng `<script src="js/chatbot.js"></script>`.

## 4. Giao diện dùng chung (`theme.css`)

- **Nguồn đúng duy nhất là file `theme.css` thật** đính kèm cùng khi hỏi AI. Chỉ dùng class có trong file đó. Khi TV3 sửa `theme.css`, các trang khác kéo bản mới về (`git pull`) rồi kiểm tra lại giao diện.
- Biến màu: `--brand` `--brand-dark` `--brand-soft` (teal chủ đạo), `--ok` `--warn` `--danger` (kèm `-soft`), `--ink` (chữ), `--muted` (chữ phụ), `--line` (viền), `--surface` (nền thẻ), `--bg` (nền trang).
- **Class tối thiểu `theme.css` phải có** để `_template.html` và các trang chạy đúng (TV3 chịu trách nhiệm): `sidebar` `brand` `brand-mark` `nav-group` `nav-item` `store-box` `main` `topbar` `search` `btn-new` `user` `avatar` `page-title` `panel` `panel-head` `tbl` `chip` (`ok` `warn` `danger` `gray`) `toolbar` `btn-brand` `btn-ghost` `btn-danger-soft` `lbl` `inp` (`is-invalid`) `field-error` `form-grid` `tr-danger` `tr-warn` `empty` `toast-x`. Hộp thoại dùng modal Bootstrap, cần được style cho khớp giao diện.

## 5. Quy ước nghiệp vụ (cả nhóm dùng thống nhất)

- **Thuốc kê đơn:** `LoaiThuoc = 1`. Bán phải kèm đơn thuốc, và nhân viên bán phải là dược sĩ đại học có chứng chỉ hành nghề. Vi phạm thì máy chủ trả lỗi, giao diện chỉ hiển thị lỗi đó.
- **Lô thuốc:** chỉ nhập kho được lô còn hạn từ 6 tháng trở lên. Màu trạng thái lô dùng hàm `trangThaiLo()`: **đỏ** = đã hết hạn, **vàng** = còn từ 0 đến 180 ngày, **xanh** = an toàn.
- **Tồn kho thấp:** tổng tồn nhỏ hơn 30 thì hiện cảnh báo vàng.
- **FEFO (xuất lô hạn gần trước):** do máy chủ xử lý, giao diện không chọn lô.
- **Giá:** giao diện chỉ gửi `SoLuong`; máy chủ tự lấy `GiaBan` từ CSDL. Giá hiện trên màn hình chỉ để hiển thị.
- **Số điện thoại:** đúng 10 số và bắt đầu bằng 0, kiểm tra bằng `phoneOk()` (`/^0\d{9}$/`).
- **Khách lẻ:** gửi `MaKhachHang: null`.
- **Giảm giá và hình thức thanh toán:** giao diện POS có hiển thị nhưng CSDL chưa có cột lưu, nên **chưa gửi lên** máy chủ.
- **Hóa đơn đã lập không sửa, không xóa được** (CSDL khóa). Vì vậy "Hủy đơn" ở POS chỉ là bỏ giỏ hàng chưa thanh toán.
- **Phím tắt:** trang POS: `F1` tìm kiếm, `F3` thanh toán, `F4` hủy đơn. Các trang khác: `F1` mở POS. Luôn gọi `e.preventDefault()`.
- **Tính năng phụ (giai đoạn 3, chỉ làm sau khi phần lõi được nghiệm thu):**
  - *Thuốc thay thế:* thuốc cùng `HoatChat` (so sánh không phân biệt hoa thường và dấu), khác `MaThuoc`, `TongTonKho > 0`; hiển thị cả `QuyCach` vì có thể khác hàm lượng. Đây chỉ là gợi ý cho dược sĩ.
  - *Quét đơn thuốc (OCR):* kết quả nhận diện chỉ là **danh sách gợi ý kèm độ khớp**, dược sĩ tích chọn và xác nhận rồi mới thêm vào giỏ, vì nhầm thuốc là rủi ro thật. Ảnh xử lý ngay trên trình duyệt, không gửi lên máy chủ, và chỉ dùng ảnh mẫu không chứa thông tin thật.
  - *Chatbot:* chỉ tra cứu giá và tồn kho, luôn hiển thị dòng lưu ý không thay thế tư vấn y tế.

## 6. Hợp đồng API

**Quy ước chung**
- Địa chỉ gốc `http://localhost:3000`. Gửi và nhận JSON. Tên trường viết đúng như cột CSDL (`TenThuoc`, `SoLuongTon`...).
- Số trả về là **số** (không phải chuỗi). Ngày giờ trả về dạng ISO, đọc bằng `new Date(...)` hoặc `fmtDate()`.
- Kiểu đặt tên: `GET /api/<tên>/danhsach` (lấy danh sách), `POST /api/<tên>/them`, `POST /api/<tên>/sua`, `POST /api/<tên>/xoa`.
- Lỗi: mã HTTP 4xx/5xx kèm `{ "error": "Thông báo tiếng Việt" }`.
- Trạng thái: ✅ đang chạy, ⏳ trưởng nhóm sắp làm (cứ dùng dữ liệu giả đúng hình dạng dưới đây).

| # | Trạng thái | Endpoint | Dùng cho |
|---|---|---|---|
| 1 | ✅ | `GET /api/thuoc/danhsach` | products, pos |
| 2 | ⏳ | `POST /api/thuoc/them`, `/sua`, `/xoa` | products |
| 3 | ⏳ | `GET /api/nhomthuoc/danhsach`, `/api/nhacungcap/danhsach`, `/api/bacsi/danhsach` | ô chọn trong biểu mẫu |
| 4 | ✅ | `GET /api/kho/danhsach` | inventory |
| 5 | ⏳ | `POST /api/kho/nhap` | inventory (nhập kho) |
| 6 | ⏳ | `GET /api/khachhang/danhsach`, `POST /api/khachhang/them`, `/sua`, `/xoa` | customers, pos |
| 7 | ⏳ | `GET /api/nhanvien/danhsach`, `POST /api/nhanvien/them`, `/sua`, `/xoa` | users |
| 8 | ⏳ | `POST /api/dangnhap` | đăng nhập |
| 9 | ⏳ | `POST /api/hoadon/them` (thanh toán), `GET /api/hoadon/danhsach`, `GET /api/hoadon/chitiet?ma=...` | pos, orders |
| 10 | ⏳ | `GET /api/thongke/tongquan`, `/doanhthu7ngay`, `/canhbao`, `/banchay` | index |
| 11 | ⏳ (giai đoạn 3) | `POST /api/chatbot/hoi` | chatbot popup (`js/chatbot.js`) |

### 6.1 Thuốc
(Tính năng *thuốc thay thế* dùng lại API này, không cần endpoint mới.)

`GET /api/thuoc/danhsach` (trường `GiaBan`, `LoaiThuoc`, `MaNhom`, `MaNCC` đang được bổ sung)
```json
[ { "MaThuoc": "T0001", "SoDangKy": "VD-27101-17", "TenThuoc": "Paracetamol 500mg",
    "HoatChat": "Paracetamol", "QuyCach": "Hộp 10 vỉ x 10 viên", "TenNhom": "Giảm đau - hạ sốt",
    "MaNhom": "NT001", "MaNCC": "NCC001", "LoaiThuoc": 0, "GiaBan": 35000, "TongTonKho": 8 } ]
```
`POST /api/thuoc/them` (mã thuốc do máy chủ tự sinh)
```json
{ "MaNhom": "NT001", "MaNCC": "NCC001", "TenThuoc": "Ibuprofen 400mg", "SoDangKy": "VD-27102-18",
  "HoatChat": "Ibuprofen", "QuyCach": "Hộp 10 vỉ x 10 viên", "LoaiThuoc": 0, "GiaBan": 52000 }
```
Trả `201 { "MaThuoc": "T0021" }`. `/sua` gửi thêm `MaThuoc`, trả `{ "ok": true }`. `/xoa` gửi `{ "MaThuoc": "T0021" }`; thuốc đã có lô hoặc hóa đơn thì trả `409 { "error": "..." }`.

Danh mục cho ô chọn: `GET /api/nhomthuoc/danhsach` → `[ { "MaNhom": "NT001", "TenNhom": "Giảm đau - hạ sốt" } ]`, `GET /api/nhacungcap/danhsach` → `[ { "MaNCC": "NCC001", "TenNCC": "Công ty CP Dược phẩm Minh An" } ]`, `GET /api/bacsi/danhsach` → `[ { "MaBacSi": "BS001", "HoTen": "BS. Lê Quốc Hùng", "NoiCongTac": "...", "ChuyenKhoa": "..." } ]`.

### 6.2 Kho
`GET /api/kho/danhsach` (đã sắp theo hạn dùng gần nhất lên đầu)
```json
[ { "MaLo": "LOT0001-1", "SoLo": "BATCH-0001-1", "SoLuongTon": 5, "HanSuDung": "2027-02-28T00:00:00.000Z",
    "NgaySanXuat": "2026-02-28T00:00:00.000Z", "TenThuoc": "Paracetamol 500mg", "SoDangKy": "VD-27101-17",
    "QuyCach": "Hộp 10 vỉ x 10 viên" } ]
```
`POST /api/kho/nhap` (nhập một phiếu gồm nhiều lô; lô hạn dưới 6 tháng bị từ chối với `400`)
```json
{ "MaNCC": "NCC001", "MaNhanVien": "NV001",
  "Lo": [ { "MaThuoc": "T0001", "SoLo": "BATCH-0001-3", "NgaySanXuat": "2026-10-01", "HanSuDung": "2028-10-01",
            "SoLuongNhap": 100, "GiaNhap": 25000 } ] }
```
Trả `201 { "MaPhieuNhap": "PN202610280001", "Lo": [ { "MaLo": "LOT0001-3" } ] }`.

### 6.3 Khách hàng
`GET /api/khachhang/danhsach` (có thể thêm `?sdt=090` để lọc theo số điện thoại)
```json
[ { "MaKhachHang": "KH001", "HoTen": "Nguyễn Thị Lan", "SoDienThoai": "0900000001", "DiemTichLuy": 120 } ]
```
`POST /api/khachhang/them` gửi `{ "HoTen", "SoDienThoai" }` → `201 { "MaKhachHang": "KH006" }`. `/sua` gửi thêm `MaKhachHang`. `/xoa` gửi `{ "MaKhachHang" }`. Khách đã có hóa đơn thì trả `409`.

### 6.4 Nhân viên và đăng nhập
`GET /api/nhanvien/danhsach` (không bao giờ trả mật khẩu)
```json
[ { "MaNhanVien": "NV001", "HoTen": "Trần Thị Thu Hà", "ChucVu": "Dược sĩ đại học", "ChungChi": "CCHN-00001", "Username": "duocsi01" } ]
```
`POST /api/nhanvien/them` gửi `{ "HoTen", "ChucVu", "ChungChi", "Username", "Password" }`. `/sua` gửi thêm `MaNhanVien` (không đổi mật khẩu). `/xoa` gửi `{ "MaNhanVien" }`.
Chức vụ hợp lệ: `Dược sĩ đại học`, `Quản lý chuyên môn`, `Nhân viên bán hàng`. Chỉ hai chức vụ đầu (có chứng chỉ) được bán thuốc kê đơn.
`POST /api/dangnhap` gửi `{ "Username": "duocsi01", "Password": "123456" }` → `{ "MaNhanVien": "NV001", "HoTen": "Trần Thị Thu Hà", "ChucVu": "Dược sĩ đại học" }`; sai thì `401`.

### 6.5 Hóa đơn (bán hàng)
`POST /api/hoadon/them` – thanh toán một giỏ hàng. `DonThuoc` chỉ gửi khi trong giỏ có thuốc kê đơn.
```json
{ "MaNhanVien": "NV001", "MaKhachHang": null,
  "DonThuoc": { "MaBacSi": "BS001", "ChanDoan": "Viêm họng cấp" },
  "Items": [ { "MaThuoc": "T0001", "SoLuong": 2 }, { "MaThuoc": "T0005", "SoLuong": 1 } ] }
```
Trả `201`. Một thuốc có thể bị tách thành nhiều dòng nếu lấy từ nhiều lô (FEFO):
```json
{ "MaHoaDon": "HD202610280001", "TongTien": 148000,
  "ChiTiet": [ { "MaLo": "LOT0001-1", "TenThuoc": "Paracetamol 500mg", "SoLuong": 2, "DonGia": 35000, "ThanhTien": 70000 },
               { "MaLo": "LOT0005-1", "TenThuoc": "Amoxicillin 500mg", "SoLuong": 1, "DonGia": 78000, "ThanhTien": 78000 } ] }
```
Lỗi ví dụ: `400 { "error": "Lỗi QĐ 4041 Rule 6: Thuốc kê đơn bắt buộc phải có Mã đơn thuốc!" }`.
`GET /api/hoadon/danhsach`
```json
[ { "MaHoaDon": "HD202610280001", "NgayLap": "2026-10-28T07:32:00.000Z", "TenKhachHang": "Khách lẻ",
    "TenNhanVien": "Trần Thị Thu Hà", "TongTien": 148000, "MaDonThuoc": null } ]
```
`GET /api/hoadon/chitiet?ma=HD202610280001` → như một phần tử ở trên, thêm mảng `ChiTiet` giống phần trả về của `/hoadon/them`.

### 6.6 Thống kê (trang Tổng quan)
```json
GET /api/thongke/tongquan      { "DoanhThuHomNay": 8450000, "DoanhThuHomQua": 7540000, "SoDonHomNay": 42, "SoKhachHomNay": 18 }
GET /api/thongke/doanhthu7ngay [ { "Ngay": "2026-10-22T00:00:00.000Z", "DoanhThu": 5100000 } ]      (đủ 7 ngày, cũ đến mới)
GET /api/thongke/canhbao       { "SapHetHan":  [ { "MaLo": "LOT0005-1", "SoLo": "BATCH-0005-1", "TenThuoc": "Amoxicillin 500mg", "HanSuDung": "2027-05-28T00:00:00.000Z", "SoLuongTon": 36, "SoNgayConLai": 212 } ],
                                 "SapHetHang": [ { "MaThuoc": "T0001", "TenThuoc": "Paracetamol 500mg", "TongTonKho": 8, "MucToiThieu": 30 } ] }
GET /api/thongke/banchay       [ { "TenThuoc": "Paracetamol 500mg", "SoLuongBan": 64 } ]              (top 5 hôm nay)
```

### 6.7 Chatbot tra cứu (giai đoạn 3)
`POST /api/chatbot/hoi` – nhận một câu hỏi tự nhiên, tra giá và tồn kho theo tên thuốc hoặc hoạt chất.
```json
{ "CauHoi": "paracetamol giá bao nhiêu" }
```
Trả `200` (tối đa 5 gợi ý; không tìm thấy thì `GoiY` là mảng rỗng và `TraLoi` nói rõ không tìm thấy):
```json
{ "TraLoi": "Paracetamol 500mg giá 35.000đ (Hộp 10 vỉ x 10 viên), hiện còn 8 hộp.",
  "GoiY": [ { "MaThuoc": "T0001", "TenThuoc": "Paracetamol 500mg", "GiaBan": 35000, "TongTonKho": 8 } ],
  "LuuY": "Chatbot chỉ tra cứu giá và tồn kho, không thay thế tư vấn của dược sĩ hoặc bác sĩ." }
```
Câu hỏi rỗng thì trả `400 { "error": "..." }`. Giao diện luôn hiển thị `LuuY` dưới khung chat.

## 7. Ghi chú cho trưởng nhóm (backend)

1. `HOA_DON.MaKhachHang` là **NOT NULL**, nên cần sẵn một khách lẻ (ví dụ `KH000`) trong CSDL; khi nhận `MaKhachHang: null` thì dùng mã đó.
2. Không tin giá từ giao diện: lấy `GiaBan` trong CSDL rồi truyền vào `sp_XuatKho_FEFO` (`@DonGia`).
3. `DonThuoc` có kèm hóa đơn: tạo dòng `DON_THUOC` rồi `HOA_DON` trong cùng một giao dịch (khóa ngoại yêu cầu đơn thuốc phải có trước).
4. `GiamGia` và `HinhThucThanhToan` chưa có cột trong CSDL, hợp đồng này cố ý không nhận. Nhóm muốn lưu thì thêm cột rồi cập nhật mục 6.5.
5. Trả số thực dạng số, ngày giờ dạng ISO (`...T...Z`): cột `DECIMAL`/`DATETIME` của driver PHP mặc định trả chuỗi nên cần ép kiểu như đã làm với `isoDate()`.
6. `MucToiThieu` là hằng số 30 trong code máy chủ.
7. Chatbot mức 1 không gọi mô hình AI: tìm bằng `LIKE` trên `TenThuoc` và `HoatChat` (chuẩn hóa bỏ dấu nếu làm được), ghép câu trả lời từ dữ liệu tìm thấy, và luôn trả kèm `LuuY`. Nên bỏ qua các câu hỏi về liều dùng hoặc chẩn đoán, trả lời rằng chatbot chỉ tra cứu giá và tồn kho.

## 8. Quy trình 3 bước cho mỗi trang

1. **Giao diện tĩnh + dữ liệu giả:** dựng bố cục từ `_template.html`, dữ liệu giả đúng hình dạng ở mục 6 (`USE_MOCK = true`).
2. **Làm chức năng chạy được với dữ liệu giả:** tìm kiếm, thêm/sửa/xóa, kiểm tra biểu mẫu, đổi màu trạng thái... Thử kỹ ở bước này vì chưa phụ thuộc máy chủ.
3. **Nối API:** khi trưởng nhóm báo endpoint đã xong, đổi `USE_MOCK = false`, thử lại, báo lỗi (kèm nội dung lỗi trong Console) nếu có.

## 9. Git tối thiểu

```
git pull
git checkout -b ten-ban/ten-trang        # ví dụ: an/pos
# ...làm việc, rồi:
git add frontend/ten-file-cua-ban.html
git commit -m "pos: them gio hang"
git push -u origin ten-ban/ten-trang      # sau đó tạo Pull Request trên GitHub, trưởng nhóm duyệt
```
Mỗi người một file, không sửa file của người khác. Muốn đổi `theme.css` hay `_template.html` thì nhờ trưởng nhóm.
