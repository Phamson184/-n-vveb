# Mẫu lệnh gửi AI (Prompt Template)

## Cách dùng (khoảng 2 phút mỗi lần)

1. Mở một cuộc trò chuyện **mới** với AI.
2. **Đính kèm:** `docs/AI_CONTEXT.md`, `frontend/css/theme.css`, `frontend/_template.html`, và file trang của bạn nếu đã có.
3. Dán lệnh của bạn ở mục 2. Làm theo thứ tự ở `AI_CONTEXT.md` mục 8: dữ liệu giả → chạy được → nối API.
4. Mở trang trên http://localhost:8080 và thử bấm thật. Có lỗi thì dán nguyên thông báo trong Console (F12) cho AI, không tự đoán.

Các lệnh có nhãn **[Giai đoạn 3]** chỉ dùng sau khi phần lõi đã được trưởng nhóm nghiệm thu.

---

## 1. Lệnh khung (dùng cho mọi trang)

```
Bạn là lập trình viên frontend của dự án PharmaPro. Tôi đính kèm AI_CONTEXT.md (quy tắc, thư viện được phép
và hợp đồng API), theme.css (CSS dùng chung) và _template.html (khung trang). Hãy tuân thủ nghiêm ngặt AI_CONTEXT.md.

TRANG CẦN LÀM: <tên file, ví dụ customers.html>
MỤC TIÊU: <1 câu>
TÍNH NĂNG:
- <tính năng 1>
- <tính năng 2>
API DÙNG (mục trong AI_CONTEXT.md): <ví dụ 6.3>

Yêu cầu:
- Bắt đầu từ _template.html, chỉ dùng class có trong theme.css, JS thuần, chỉ dùng thư viện ở mục 3.1.
- Giai đoạn này: USE_MOCK = true, dữ liệu giả đúng tên trường và kiểu như trong hợp đồng API.
- Trả về FILE HTML HOÀN CHỈNH, không rút gọn. Sau code, liệt kê ngắn: (a) cách tôi kiểm thử từng tính năng,
  (b) mục "Cần API" nếu thấy hợp đồng thiếu thứ gì.
```

---

## 2. Lệnh soạn sẵn cho từng thành viên

### Thành viên 2 – `pos.html` (Bán hàng)

> `pos.html` đã có khung và phần lớn xử lý giỏ hàng do trưởng nhóm làm sẵn. Đính kèm file hiện tại.

**Giai đoạn 1–2: hoàn thiện màn hình bán hàng**
```
TRANG CẦN LÀM: pos.html (đã có sẵn, hãy nâng cấp, không viết lại từ đầu). Đính kèm pos.html hiện tại.
MỤC TIÊU: màn hình thu ngân thao tác nhanh, không tải lại trang, thanh toán gửi được lên máy chủ.
TÍNH NĂNG:
- Ô tìm kiếm thuốc (tên, hoạt chất) hoạt động trơn tru; chọn thuốc thì thêm vào giỏ hàng (mảng JS thuần).
- Tăng/giảm số lượng, tự cộng tổng tiền (từ cột GiaBan). Không cho số lượng vượt tồn kho hiển thị (TongTonKho).
- Phím tắt bằng event listener: F1 tìm kiếm, F3 thanh toán, F4 hủy đơn (bỏ giỏ hàng, có hỏi xác nhận). Luôn preventDefault.
- Tìm khách theo số điện thoại (kiểm tra phoneOk) hoặc để "Khách lẻ".
- Khi giỏ có thuốc kê đơn (LoaiThuoc = 1): bắt buộc chọn bác sĩ và nhập chẩn đoán, thiếu thì không cho thanh toán.
- Kết xuất giỏ hàng thành đúng object JSON của POST /api/hoadon/them (chỉ gửi MaThuoc và SoLuong), sẵn sàng fetch POST.
  Thanh toán xong thì hiện mã hóa đơn, xóa giỏ. Với USE_MOCK = true thì giả lập kết quả trả về.
- Là phần UX trọng tâm: luồng bán hàng chỉ bằng bàn phím phải mượt (gõ tên → Enter thêm vào giỏ → F3 thanh toán).
API DÙNG: 6.1 (danh sách thuốc), 6.3 (khách hàng), 6.5 (hóa đơn)
```

**[Giai đoạn 3] Thuốc thay thế**
```
TRANG CẦN LÀM: pos.html (đính kèm file hiện tại, chỉ thêm tính năng, không phá phần đã chạy).
TÍNH NĂNG: khi thuốc dược sĩ tìm đang hết hàng (TongTonKho = 0) hoặc số lượng yêu cầu vượt tồn, hiện khung
"Thuốc thay thế" liệt kê các thuốc cùng HoatChat (so sánh không phân biệt hoa thường và dấu), khác MaThuoc,
TongTonKho > 0, sắp theo giá gần nhất. Mỗi dòng hiện TenThuoc, QuyCach (có thể khác hàm lượng), giá, tồn, chip "Kê đơn"
nếu LoaiThuoc = 1. Bấm vào một dòng thì thêm vào giỏ. Ghi chú nhỏ: "Gợi ý để dược sĩ cân nhắc".
Chỉ dùng dữ liệu của GET /api/thuoc/danhsach (đã có HoatChat và TongTonKho), không cần API mới.
API DÙNG: 6.1
```

**[Giai đoạn 3, làm cuối cùng, có thể bỏ] Quét đơn thuốc bằng OCR**
```
TRANG CẦN LÀM: pos.html (đính kèm file hiện tại). Thư viện: Tesseract.js 5.1.1 và Fuse.js 7.0.0 đúng đường dẫn trong
AI_CONTEXT.md mục 3.1.
TÍNH NĂNG: thêm nút "Quét đơn thuốc" mở modal:
1) Chọn ảnh từ máy (input file), xem trước ảnh.
2) Chạy Tesseract.recognize(ảnh, 'vie', { logger }) và hiện thanh tiến trình (lần đầu chậm vì tải dữ liệu tiếng Việt).
3) Tách văn bản thành dòng, chuẩn hóa (bỏ dấu, hạ chữ), khớp mờ từng dòng với danh sách thuốc (Fuse.js, đặt ngưỡng hợp lý).
4) Hiện bảng "Nhận diện được": tên thuốc khớp, độ khớp (%), ô tích chọn. Dược sĩ kiểm tra và bấm "Thêm vào giỏ" cho các dòng đã
   tích. KHÔNG tự thêm vào giỏ; dòng độ khớp thấp thì để bỏ chọn mặc định.
5) Có ô nhập tay tên thuốc làm dự phòng khi OCR không đọc được. Ảnh chỉ xử lý trên trình duyệt, không gửi lên máy chủ.
Demo bằng ảnh đơn thuốc in rõ chữ do nhóm chuẩn bị (không chứa thông tin thật), tên thuốc có trong dữ liệu mẫu.
API DÙNG: 6.1
```

---

### Thành viên 3 – `products.html`, `inventory.html`, `users.html` (Kho, nhân sự) và `theme.css`

> Làm lần lượt từng trang, mỗi trang một cuộc trò chuyện mới.

```
TRANG 1: products.html
MỤC TIÊU: quản lý danh mục thuốc.
TÍNH NĂNG:
- Bảng danh sách thuốc (tên, hoạt chất, quy cách, nhóm, giá bán, tồn kho), có ô tìm kiếm và lọc theo nhóm.
- Modal form thêm/sửa thuốc theo phong cách hiện đại của theme.css. Trường: nhóm, nhà cung cấp (ô chọn lấy từ danh mục),
  tên thuốc, số đăng ký, hoạt chất, quy cách, loại (kê đơn hay không), giá bán. Kiểm tra bắt buộc nhập và giá bán >= 0;
  ô sai thì tô viền đỏ và hiện dòng báo lỗi (dùng class có trong theme.css, nếu chưa có thì viết trong <style> của trang).
- Nút xóa có hỏi xác nhận; nếu máy chủ trả lỗi 409 thì hiện nguyên văn câu lỗi.
- Thuốc kê đơn có chip nhỏ "Kê đơn". Tồn kho < 30 thì hiện chip vàng.
API DÙNG: 6.1 (thuốc, gồm thêm/sửa/xóa và danh mục)

TRANG 2: inventory.html
MỤC TIÊU: theo dõi lô thuốc và cảnh báo hạn dùng.
TÍNH NĂNG:
- Bảng các lô (tên thuốc, số lô, ngày sản xuất, hạn dùng, tồn). Viết hàm nhận ngày hết hạn, so với Date.now() để tính số
  ngày còn lại (dùng trangThaiLo có sẵn) rồi đổi chip và tô dòng: Đỏ (hết hạn), Vàng (dưới 6 tháng), Xanh (an toàn).
  Có bộ lọc theo trạng thái và hiển thị số lô mỗi trạng thái.
- Modal form nhập kho: chọn nhà cung cấp, thêm nhiều dòng lô (thuốc, số lô, ngày sản xuất, hạn dùng, số lượng, giá nhập).
  Kiểm tra phía giao diện: hạn dùng > ngày sản xuất, và cảnh báo nếu hạn dùng còn dưới 6 tháng (máy chủ sẽ từ chối).
API DÙNG: 6.1 (thuốc, để chọn), 6.2 (kho)

TRANG 3: users.html
MỤC TIÊU: quản lý nhân viên.
TÍNH NĂNG: bảng nhân viên (mã, họ tên, chức vụ, chứng chỉ hành nghề, tên đăng nhập); modal thêm/sửa (khi thêm có ô mật khẩu,
khi sửa thì không); ô chọn chức vụ theo danh sách trong hợp đồng; nút xóa có xác nhận; hiện chip "Được bán thuốc kê đơn"
cho hai chức vụ có chứng chỉ.
API DÙNG: 6.4
```

**Vai trò thiết kế – rà soát giao diện toàn dự án** (dùng khi sắp tới mốc kết thúc giai đoạn 1 và 2)
```
Tôi đính kèm theme.css và file HTML của một trang (<tên file>). Hãy rà soát độ đồng bộ giao diện: có chỗ nào gõ mã màu
trực tiếp thay vì biến CSS, class không có trong theme.css, khoảng cách và cỡ chữ lệch so với index.html, hay thiếu trạng thái
rỗng/báo lỗi. Trả về danh sách lỗi theo mức độ, kèm đoạn sửa cụ thể. Không viết lại cả trang.
```
> TV3 gửi kết quả rà soát cho chủ trang tự sửa (hoặc tạo Pull Request vào `theme.css` nếu lỗi nằm ở file dùng chung), không sửa thẳng file của người khác.

---

### Thành viên 4 – `customers.html`, `orders.html`, `index.html` và chatbot

> Làm lần lượt từng trang, mỗi trang một cuộc trò chuyện mới.

```
TRANG 1: customers.html
TÍNH NĂNG: bảng khách hàng (mã, họ tên, số điện thoại, điểm tích lũy) có ô tìm theo tên hoặc số điện thoại;
modal form thêm/sửa; nút xóa có xác nhận. Viết hàm kiểm tra số điện thoại bằng Regex JavaScript chuẩn Việt Nam
(chỉ nhận số, đủ 10 số, bắt đầu bằng 0) trước khi cho phép submit, sai thì tô viền đỏ và hiện dòng báo lỗi.
API DÙNG: 6.3

TRANG 2: orders.html
TÍNH NĂNG: danh sách hóa đơn (mã, thời gian, khách, nhân viên, tổng tiền), có lọc theo ngày và tìm theo mã.
Mỗi dòng có nút "Xem": bấm vào hiện modal chi tiết các món trong đơn (tên thuốc, số lượng, đơn giá, thành tiền, tổng cộng).
API DÙNG: 6.5

TRANG 3: index.html (đã có khung do trưởng nhóm làm, đính kèm index.html hiện tại; chỉ thay dữ liệu và biểu đồ)
TÍNH NĂNG: nối số liệu thật cho thẻ doanh thu, danh sách "Cần xử lý trong kho" và "Bán chạy hôm nay".
Thay biểu đồ cột CSS bằng Chart.js 4.4.1 (đường dẫn trong AI_CONTEXT.md mục 3.1): viết hàm nhận mảng JSON [{Ngay, DoanhThu}]
để vẽ biểu đồ cột doanh thu 7 ngày gần nhất.
API DÙNG: 6.6
```

**[Giai đoạn 3] Khung chatbot dùng chung** (file `js/chatbot.js`)
```
Viết file js/chatbot.js: một popup chat nổi ở góc phải dưới, tự tạo giao diện và CSS khi tải (dùng biến màu var(--brand)...
của theme.css). Toàn bộ code bọc trong IIFE, KHÔNG khai báo biến toàn cục (tránh đụng const API của các trang).
TÍNH NĂNG: nút tròn mở/đóng khung chat; danh sách tin nhắn người dùng và bot; ô nhập, Enter để gửi; hiệu ứng "đang trả lời";
gọi POST /api/chatbot/hoi với { CauHoi } rồi hiện TraLoi, và các thẻ gợi ý nhỏ từ GoiY (tên, giá, tồn) có thể bấm để xem;
luôn hiện dòng LuuY dưới khung chat; bắt lỗi mạng bằng thông báo thân thiện. Có cờ MOCK = true với câu trả lời giả đúng hình dạng
hợp đồng (mục 6.7) để làm giao diện trước khi API sẵn sàng. Mỗi trang chỉ cần thêm: <script src="js/chatbot.js"></script>.
API DÙNG: 6.7
```

---

## 3. Danh sách tự kiểm tra trước khi tạo Pull Request

- [ ] Trang mở được trên http://localhost:8080, Console (F12) không có lỗi đỏ.
- [ ] Chỉ sửa file được giao; sidebar và khối hàm dùng chung vẫn nguyên vẹn.
- [ ] Không còn mã màu gõ tay trong CSS riêng; dùng biến `var(--...)`.
- [ ] Dữ liệu chèn vào trang đều qua `esc()`.
- [ ] Bắt lỗi khi API hỏng: có `toast` báo lỗi, trang không trắng.
- [ ] Đã thử với `USE_MOCK = true`; nếu endpoint đã xong thì thử cả `USE_MOCK = false`.
- [ ] Bạn tự giải thích được từng đoạn code mình nộp (giảng viên có thể hỏi riêng từng người).
