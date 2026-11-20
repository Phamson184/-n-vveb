# PharmaPro v2.0 – Ngữ cảnh dự án cho AI

> **Cách dùng:** mỗi lần nhờ AI làm giao diện, đính kèm: file này, `frontend/css/theme.css`, `frontend/_template.html`, **các file mẫu JSON trong `docs/api-samples/` của những API trang bạn dùng**, và file trang hiện tại (nếu có).
> Backend v2.0 đã xong và chạy thật. Muốn đổi API thì trưởng nhóm sửa mục 6 trước rồi mới sửa code.

## 1. Dự án và công nghệ

Web quản lý nhà thuốc bán lẻ và dược lâm sàng theo chuẩn GPP. Backend PHP + SQL Server do trưởng nhóm làm. Frontend dùng **HTML + CSS + JavaScript thuần**, Bootstrap 5, FontAwesome 6. **Không dùng** React, Vue, jQuery.
Chạy bằng Docker: giao diện `http://localhost:8082`, API `http://localhost:3001` (mọi đường dẫn bắt đầu bằng `/api/v2`).
Khác bản v1: có đăng nhập JWT, phân quyền theo vai trò, tên trường tiếng Anh (`DrugName`, `CurrentStock`...), và luồng đơn thuốc 5 trạng thái thay cho thanh toán một bước.

## 2. Thư mục và người phụ trách

```
frontend/
  _template.html      khung trang chuẩn (sidebar, topbar, kiểm tra đăng nhập, hàm dùng chung)
  login.html          đăng nhập, lấy JWT
  index.html          tổng quan
  pos.html            kê đơn và POS
  prescriptions.html  vòng đời đơn thuốc: thẩm định, xuất kho, hoàn tất, Zalo ZNS
  patients.html       hồ sơ dược bạ 360°
  products.html       danh mục thuốc và ma trận tương tác DDI
  inventory.html      lô cận date, nhập lô, sổ thuốc kiểm soát đặc biệt
  audit.html          thanh tra lịch sử (Temporal Tables)
  css/theme.css       CSS dùng chung
docs/                 AI_CONTEXT_V2.md, PROMPT_TEMPLATE_V2.md, api-samples/ (JSON mẫu từ API thật)
api-php/  database/  docker-compose.yml     của trưởng nhóm (KHÔNG sửa)
```

| Người | File phụ trách | Trọng tâm |
|---|---|---|
| Trưởng nhóm | `api-php/`, `database/`, Docker, `login.html`, `_template.html`, `css/theme.css`, `docs/` | Backend đã xong; khung chung; duyệt Pull Request |
| TV2 – Lâm sàng | `pos.html`, `patients.html` | Hồ sơ 360°, kê đơn kèm cảnh báo CDSS, thuốc thay thế, nhập từ OCR |
| TV3 – Kho và thanh tra | `inventory.html`, `products.html`, `audit.html` | Lô cận date, nhập lô, sổ kiểm soát đặc biệt, danh mục + DDI, truy vết lịch sử |
| TV4 – Vòng đời đơn và tổng quan | `prescriptions.html`, `index.html` | Thẩm định ký số, xuất kho FEFO, hoàn tất, ZNS; dashboard |

Mỗi người chỉ sửa file của mình. Cần đổi `theme.css`, `_template.html` hay API thì nhờ trưởng nhóm.

**Trang nào gọi API nào**

| Trang | API (số thứ tự ở mục 6) |
|---|---|
| `pos.html` | 3, 4, 15, 19 |
| `patients.html` | 2, 11, 12, 13 |
| `inventory.html` | 3, 6, 7, 8, 9 |
| `products.html` | 2, 3, 5 |
| `audit.html` | 21 |
| `prescriptions.html` | 14, 16, 17, 18, 20 |
| `index.html` | 7, 10, 14, 22 |

## 3. Quy tắc code (bắt buộc)

1. Mỗi trang là **một file HTML** có `<style>` và `<script>` riêng. Bắt đầu từ `_template.html`, giữ nguyên sidebar, topbar, đoạn kiểm tra đăng nhập và khối hàm dùng chung: `api(method, path, body)`, `esc`, `money`, `num`, `flag`, `fmtDate`, `fmtDateTime`, `getParam`, `hasRole`, `applyRoles`, `toast`.
2. **Mọi giá trị số và cờ từ API đều là CHUỖI**: `"38000.00"`, `"45"`, `"0"`, `"1"` (driver SQL Server của PHP trả như vậy), kể cả mã định danh như `"DrugID": "2"`. Vì vậy:
   - Tính toán và so sánh phải qua `num(v)` (`Number`). Không cộng trực tiếp (`"35000.00" + 1` ra chuỗi nối).
   - Cờ phải qua `flag(v)` (`Number(v) === 1`). **Không viết `if (d.IsControlled)`**: chuỗi `"0"` vẫn là true.
   - Khi gửi lên máy chủ, mã định danh gửi dạng số (`Number(DrugID)`).
3. **Không đoán tên trường.** Chỉ dùng tên trường có trong mục 6 hoặc trong file mẫu `docs/api-samples/*.json`. Thiếu thứ gì thì ghi vào mục "Cần API" gửi trưởng nhóm. Chỉ làm chức năng có trong hợp đồng v2 (không có khách hàng, đơn hàng, chatbot, sửa hay xóa thuốc).
4. **Dữ liệu giả cũng phải giống thật:** nếu dùng `USE_MOCK = true`, dữ liệu giả phải đúng tên trường và đúng kiểu chuỗi như API thật. Làm xong chuyển ngay sang `USE_MOCK = false` và thử với backend đang chạy.
5. Mọi dữ liệu chèn vào `innerHTML` phải qua `esc()`. Tiền: `35.000đ` (`money()`), ngày: `dd/mm/yyyy` (`fmtDate()`).
6. Màu sắc dùng biến CSS (`--brand`, `--ok`, `--warn`, `--danger`...) và **chỉ những class có thật trong `theme.css` đính kèm**. Thiếu class thì viết trong `<style>` của trang. Không sửa `theme.css`.
7. Mọi lời gọi API đi qua `api()`, luôn `try/catch`, báo lỗi bằng `toast(e.message, true)`. `api()` ném `Error` có thêm `e.status` (mã HTTP) và `e.data` (JSON thân phản hồi lỗi), dùng để đọc lỗi 422 có cấu trúc.
8. Khi sửa code, **trả về file hoàn chỉnh**, không viết "phần còn lại giữ nguyên".

### 3.1 Thư viện được phép (bản cố định)

| Thư viện | Phiên bản | Dùng ở |
|---|---|---|
| Bootstrap CSS + JS | 5.3.0 (cdn.jsdelivr.net) | mọi trang (chủ yếu modal) |
| FontAwesome | 6.4.0 (cdnjs) | mọi trang |
| Chart.js | 4.4.1: `https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js` | `index.html` |
| Tesseract.js (tùy chọn) | 5.1.1: `https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js` | `pos.html`, chỉ khi muốn quét ảnh thật |

Thư viện khác phải được trưởng nhóm duyệt và ghi vào bảng này trước.

## 4. Đăng nhập, JWT và phân quyền

- `login.html` gọi `POST /api/v2/auth/login`, lưu `sessionStorage['pp_token']` và `sessionStorage['pp_user']` (JSON của `user`).
- `_template.html` kiểm tra khi tải trang: thiếu `pp_token` thì chuyển sang `login.html`. Topbar hiển thị `FullName` và `RoleCode`.
- `api()` tự gắn `Authorization: Bearer <pp_token>`. Gặp **401**: xóa sessionStorage và chuyển về `login.html`. Gặp **403**: `api()` ném lỗi có `e.status === 403` và `e.message` là câu chuẩn "Bạn không có quyền thực hiện chức năng này!"; trang hiển thị bằng `toast(e.message, true)` như mọi lỗi khác. **Mỗi khối dữ liệu tự xử lý 403 riêng** (ví dụ hiện "Không có quyền xem" trong khối đó), một khối bị chặn không được làm vỡ cả trang.
- Ẩn menu và nút theo vai trò bằng thuộc tính `data-roles="ADMIN,PHARMACIST"`; `_template.html` tự ẩn phần tử nếu `RoleCode` không nằm trong danh sách. **Phần tử tạo bằng JS (`innerHTML`) có `data-roles` thì gọi `applyRoles(container)` sau khi render.** Trang chỉ cho một số vai trò vào thì đặt `<body data-page-roles="ADMIN,PHARMACIST">`, vai trò khác sẽ thấy thông báo không có quyền. Ẩn nút chỉ để gọn giao diện, máy chủ mới là nơi chặn thật.
- Vai trò: `ADMIN`, `DOCTOR`, `PHARMACIST`, `CASHIER`, `WAREHOUSE`.

| Trang (menu) | Vai trò thấy menu (mặc định, trưởng nhóm sửa nếu backend khác) |
|---|---|
| `index`, `products` | tất cả |
| `pos`, `patients` | ADMIN, DOCTOR, PHARMACIST |
| `prescriptions` | ADMIN, DOCTOR, PHARMACIST, CASHIER |
| `inventory` | ADMIN, PHARMACIST, WAREHOUSE |
| `audit` | ADMIN, PHARMACIST |

Nút theo vai trò: tạo bệnh nhân, dị ứng, đơn, OCR = ADMIN, DOCTOR, PHARMACIST · Thẩm định ký số = ADMIN, PHARMACIST · Xuất kho, Hoàn tất, Gửi ZNS = ADMIN, PHARMACIST, CASHIER · Thêm thuốc, Nhập lô = ADMIN, WAREHOUSE · Kiểm kê tủ khóa = ADMIN, PHARMACIST, WAREHOUSE.

## 5. Quy ước nghiệp vụ

**5.1 Vòng đời đơn thuốc (5 trạng thái)**
```
pos.html tạo đơn ──► Pending ──thẩm định, ký số──► Validated ──xuất kho FEFO──► Dispensed ──hoàn tất──► Completed
 (cảnh báo CDSS)        └──► Rejected              (prescriptions.html)         (tách dòng theo lô)     (cộng điểm, gửi ZNS)
```

**5.2 Cảnh báo an toàn lâm sàng (CDSS)** khi `POST /prescriptions` trả **HTTP 422**, đọc `e.data.blocked_by`:
- `"HARD_STOP"` (tương tác chống chỉ định hoặc dị ứng nghiêm trọng): khung cảnh báo **đỏ** liệt kê `e.data.safety_report.hard_stops`, khóa nút lưu, không cho vượt.
- `"SOFT_STOP"` (thận `CrCl` thấp hơn `MinSafeCrCl`, dị ứng nhẹ): khung **vàng** liệt kê `safety_report.soft_stops`; mỗi thuốc bị cảnh báo có ô nhập **`OverrideReason`** (lý do vượt cảnh báo), sau đó gửi lại cùng đơn với `OverrideReason` đã điền.
- Cấu trúc đúng của từng phần tử trong `hard_stops`/`soft_stops` xem file mẫu `docs/api-samples/prescriptions-422-*.json`; không đoán tên trường.

**5.3 Chỉ số thận `calculated_CrCl` (mL/phút):** dưới 30 badge đỏ "Suy thận nặng - cần giảm liều"; 30 đến 60 badge vàng; trên 60 badge xanh.
**5.4 Dị ứng:** `Severity` là `"Hard Stop"` (đỏ) hoặc `"Soft Stop"` (vàng), gắn với hoạt chất (`ActiveIngredientID`).

**5.5 Thuốc thay thế:** khi `CurrentStock` bằng 0 hoặc người dùng muốn đổi thuốc, gọi `GET /inventory/drugs/substitutes?DrugID=`. Máy chủ trả thuốc cùng hoạt chất, dạng bào chế, hàm lượng, giá trong biên độ ±20% (`price_window`), xếp theo hạn dùng xa nhất (`FurthestExpiryDate`). Giao diện chỉ hiển thị và cho chọn.

**5.6 Thuốc kiểm soát đặc biệt (`flag(IsControlled)`):**
- Đơn mang mã quốc gia 14 ký tự (`NationalCode`) có đuôi `-N` (gây nghiện), `-H` (hướng thần), `-C` (thường).
- `ControlledType` gửi lên là `"C"`, `"N"` hoặc `"H"`, mặc định `"C"`. Giỏ có thuốc kiểm soát đặc biệt thì bắt buộc chọn `N` hoặc `H`.
- Nhập lô (`POST /inventory/batches`) và xuất kho (`POST /prescriptions/dispense`) bắt buộc có **`HandoverReportCode`** (mã Biên bản giao nhận) khi liên quan thuốc kiểm soát đặc biệt hoặc đơn đuôi `-N`/`-H`. Bỏ trống thì máy chủ trả 422 `CONTROLLED_DRUG_PROTOCOL`.

**5.7 OCR:** giao diện gửi danh sách dòng (`drug_name`, `concentration`, `quantity`, `dosage_instruction`, `confidence_score`) tới `POST /prescriptions/ocr-map`. Máy chủ ghép với danh mục thuốc và trả `needs_yellow_highlight` (máy chủ tự tính, `confidence_score` dưới 0.85 là `true`). Dòng `true` tô vàng (`tr-warn`) và **dược sĩ phải tích chọn xác nhận** mới được đưa vào giỏ; dòng `false` vẫn cho dược sĩ xem lại trước khi đưa vào. Dữ liệu đầu vào có thể do nhập tay, dán, hoặc Tesseract.js (nếu có).

**5.8 Lô cận date:** `DaysRemaining` ≤ 30 tô đỏ; 31 đến 90 tô vàng. Tồn thấp: `CurrentStock` < 30 hiện cảnh báo vàng.

**5.9 Chữ ký số và Zalo ZNS là mô phỏng cho đồ án:** `DigitalSignature` chỉ là một chuỗi (ví dụ `VNPT_CA_SHA256_DUOCSI01`), `JourneyToken` do máy chủ sinh; không gửi tin nhắn thật.

**5.10 Phím tắt POS:** `F1` tìm thuốc, `F3` tạo đơn, `F4` xóa giỏ. Luôn gọi `e.preventDefault()`.

**5.11 Chuyển trang:** `pos.html` tạo đơn xong dẫn tới `prescriptions.html?id=<PrescriptionID>` (trang tự mở modal chi tiết đơn đó). `index.html` dẫn tới `prescriptions.html?status=Pending` (trang tự chọn bộ lọc). `pos.html` có liên kết `patients.html?id=<PatientID>` (trang tự mở hồ sơ 360°).

## 6. Hợp đồng API v2 (`http://localhost:3001/api/v2`)

**Quy ước chung:** gửi và nhận JSON; ngày dạng `YYYY-MM-DD` (hoặc `YYYY-MM-DD HH:MM:SS`); lỗi là HTTP 400/401/403/404/409/422 kèm `{ "error": "Thông báo tiếng Việt" }`; nhớ mục 3 điều 2 (số và cờ là chuỗi). Dấu † = hợp đồng gốc chưa ghi vai trò hoặc hình dạng, trưởng nhóm xác nhận.

| # | Phương thức và đường dẫn | Vai trò | Trang |
|---|---|---|---|
| 1 | `POST /auth/login` | công khai | login |
| 2 | `GET /catalogs/init` | mọi vai trò | products, patients |
| 3 | `GET /inventory/drugs` | mọi vai trò | pos, products, inventory |
| 4 | `GET /inventory/drugs/substitutes?DrugID=` | mọi vai trò | pos |
| 5 | `POST /inventory/drugs` | ADMIN, WAREHOUSE | products |
| 6 | `POST /inventory/batches` | ADMIN, WAREHOUSE | inventory |
| 7 | `GET /inventory/expiring-batches?days=90` | ADMIN, PHARMACIST, WAREHOUSE | inventory, index |
| 8 | `GET /inventory/controlled-logs` | ADMIN, PHARMACIST, WAREHOUSE | inventory |
| 9 | `POST /inventory/controlled-audit` | ADMIN, PHARMACIST, WAREHOUSE | inventory |
| 10 | `GET /reports/department-consumption` | ADMIN, DOCTOR, PHARMACIST | index |
| 11 | `POST /clinical/patients` | ADMIN, DOCTOR, PHARMACIST | patients |
| 12 | `POST /clinical/allergies` | ADMIN, DOCTOR, PHARMACIST | patients |
| 13 | `GET /clinical/patients/360?PatientID=` | ADMIN, DOCTOR, PHARMACIST | patients |
| 14 | `GET /prescriptions` (`?Status=`, `?PrescriptionID=`) | đăng nhập † | prescriptions, index |
| 15 | `POST /prescriptions` | ADMIN, DOCTOR, PHARMACIST | pos |
| 16 | `POST /prescriptions/validate` | ADMIN, PHARMACIST | prescriptions |
| 17 | `POST /prescriptions/dispense` | ADMIN, PHARMACIST, CASHIER | prescriptions |
| 18 | `POST /prescriptions/complete` | ADMIN, PHARMACIST, CASHIER | prescriptions |
| 19 | `POST /prescriptions/ocr-map` | ADMIN, DOCTOR, PHARMACIST | pos |
| 20 | `POST /smartcare/send-zns` | ADMIN, PHARMACIST, CASHIER | prescriptions |
| 21 | `GET /audit/as-of?table=&as_of=` | ADMIN, PHARMACIST † | audit |
| 22 | `GET /smartcare/logs` † | ADMIN, PHARMACIST, CASHIER † | index |

Phần tử `{...}` trong các ví dụ dưới là cấu trúc dài, xem file mẫu tương ứng trong `docs/api-samples/`.

### 6.1 Đăng nhập và danh mục
`POST /auth/login`
```json
{ "Username": "duocsi01", "Password": "123456" }
```
→ `{ "success": true, "token": "<JWT>", "user": { "UserID": "2", "EmployeeCode": "NV002", "FullName": "Trần Thị Thu Hà", "RoleCode": "PHARMACIST" } }`

`GET /catalogs/init` → `{ "success": true, "departments": [...], "dosage_forms": [...], "active_ingredients": [...], "interactions": [...] }` (mẫu: `catalogs-init.json`; `interactions` có mức `Contraindicated` hoặc `Moderate`)

### 6.2 Thuốc và kho
`GET /inventory/drugs` → `{ "success": true, "data": [ { "DrugID": "2", "DrugCode": "HAPACOL500", "DrugName": "Hapacol 500mg DHG", "Concentration": "500mg", "Manufacturer": "DHG Pharma", "IsPrescriptionOnly": "0", "IsControlled": "0", "MinSafeCrCl": "15.00", "CurrentPrice": "38000.00", "CurrentStock": "45", "IngredientName": "Paracetamol", "FormName": "Viên nén", "DeptName": "Khoa Hô hấp" } ] }`

`GET /inventory/drugs/substitutes?DrugID=1` → `{ "success": true, "target_drug": {...}, "price_window": { "min": 28000, "max": 42000, "tolerance": "±20%" }, "substitutes": [ { "DrugID": "2", "DrugCode": "HAPACOL500", "DrugName": "Hapacol 500mg DHG", "CurrentPrice": "38000.00", "CurrentStock": "45", "FurthestExpiryDate": "2028-11-08" } ] }`

`POST /inventory/drugs`
```json
{ "DrugCode": "AMOX500", "DrugName": "Amoxicillin 500mg", "IngredientID": 2, "DosageFormID": 1, "Concentration": "500mg",
  "Manufacturer": "Imexpharm", "DepartmentID": 1, "IsPrescriptionOnly": 1, "IsControlled": 0, "MinSafeCrCl": 30, "CurrentPrice": 45000 }
```
`POST /inventory/batches` (`HandoverReportCode` bắt buộc nếu thuốc `IsControlled = 1`)
```json
{ "BatchCode": "LOT-2026-09", "DrugID": 2, "ManufactureDate": "2026-05-01", "ExpiryDate": "2028-05-01", "Quantity": 100, "ImportPrice": 28000, "HandoverReportCode": "BBGN-PL9-NHAP-001" }
```
`GET /inventory/expiring-batches?days=90` → `{ "success": true, "threshold_days": 90, "batches": [ { "BatchID": "1002", "BatchCode": "LOT-HAPA-NEAR", "ExpiryDate": "2026-12-14", "CurrentStock": "8", "DaysRemaining": "25", "DrugCode": "HAPACOL500", "DrugName": "Hapacol 500mg DHG" } ] }`

`GET /inventory/controlled-logs` → `{ "success": true, "logs": [ { "LogID": "1", "TransactionType": "DISPENSE", "QuantityChange": "-2", "CabinetPhysicalStock": "18", "SystemStock": "18", "HandoverReportCode": "BBGN-PL9-XUAT-001", "RecordedAt": "...", "DrugName": "Morphin Hydroclorid 10mg/1ml", "BatchCode": "LOT-MORPH-01", "NationalCode": "790019BCFF4C-N", "PerformedBy": "Trần Thị Thu Hà" } ] }`

`POST /inventory/controlled-audit` (kiểm kê tủ khóa, so tồn thực tế với hệ thống)
```json
{ "DrugID": 6, "BatchID": 1007, "CabinetPhysicalStock": 18, "HandoverReportCode": "BBKK-2026-11" }
```
`GET /reports/department-consumption` → `{ "success": true, "department_report": [ { "DeptCode": "HOHAP", "DeptName": "Khoa Hô hấp", "ControlledQtyDispensed": "0", "RegularQtyDispensed": "15", "TotalDispensedValue": "570000.00" } ] }`

### 6.3 Hồ sơ dược bạ 360°
`POST /clinical/patients`
```json
{ "PatientCode": "BN202699", "FullName": "Nguyễn Văn An", "Phone": "0909123456", "DateOfBirth": "1965-05-10", "Gender": "M",
  "Weight_Kg": 62, "SerumCreatinine": 1.2, "PatientType": "Bệnh mạn tính", "ChronicCondition": "Tăng huyết áp" }
```
`POST /clinical/allergies`
```json
{ "PatientID": 7, "ActiveIngredientID": 2, "Severity": "Hard Stop", "ReactionNote": "Sốc phản vệ với nhóm Penicillin" }
```
`GET /clinical/patients/360?PatientID=7` → `{ "success": true, "patient": {...}, "calculated_CrCl": 56.69, "allergies_INN": [...], "prescription_history": [...] }` (mẫu: `patients-360.json`)

### 6.4 Đơn thuốc
`GET /prescriptions` (danh sách; lọc `?Status=Pending|Validated|Dispensed|Completed|Rejected`) → `{ "success": true, "prescriptions": [ { "PrescriptionID": "1", "NationalCode": "790017ED032B-C", "PatientName": "Nguyễn Văn An", "Diagnosis": "Sốt siêu vi", "Status": "Completed", "IsChronic": "0", "TotalAmount": "350000.00", "CreatedAt": "..." } ] }`
`GET /prescriptions?PrescriptionID=1` (chi tiết) → `{ "success": true, "prescription": {...}, "items": [ { "DrugName": "...", "PrescribedQty": "10", "DosageInstruction": "...", "dispensed_batches": [ { "BatchCode": "LOT-DATE-XA", "ExpiryDate": "2028-06-01", "DispensedQty": "10", "SubTotal": "350000.00" } ] } ] }`

`POST /prescriptions` (tạo đơn, trạng thái ban đầu `Pending`; `ControlledType` mặc định `"C"`)
```json
{ "PatientID": 7, "Diagnosis": "Sốt siêu vi", "IsChronic": 1, "ChronicDurationDays": 30, "ControlledType": "C",
  "items": [ { "DrugID": 2, "PrescribedQty": 15, "DosageInstruction": "Sáng 1, Tối 1", "OverrideReason": null } ] }
```
→ `200 { "success": true, "PrescriptionID": 5, "NationalCode": "790010705D45-C", "Status": "Pending", "TotalAmount": 570000 }`. Bị chặn thì `422` (xem mục 5.2).

`POST /prescriptions/validate` (`Decision` là `"Validated"` hoặc `"Rejected"`)
```json
{ "PrescriptionID": 5, "Decision": "Validated", "ClinicalInterventionNote": "Liều phù hợp CrCl", "DigitalSignature": "VNPT_CA_SHA256_DUOCSI01" }
```
`POST /prescriptions/dispense` (`HandoverReportCode` bắt buộc nếu đơn đuôi `-N` hoặc `-H`)
```json
{ "PrescriptionID": 5, "HandoverReportCode": "BBGN-PL9-XUAT-002" }
```
→ `{ "success": true, "Status": "Dispensed", "dispense_details": [ { "DrugName": "Hapacol 500mg DHG", "TotalDispensed": 15, "LineSplitCount": 2, "Batches": [...] } ] }`

`POST /prescriptions/complete` `{ "PrescriptionID": 5 }` → `{ "success": true, "Status": "Completed", "EarnedLoyaltyPoints": 57 }`

`POST /prescriptions/ocr-map`
```json
{ "items": [ { "drug_name": "Paracetamol", "concentration": "500mg", "quantity": 10, "dosage_instruction": "Sáng 1, Tối 1", "confidence_score": 0.96 } ] }
```
→ `{ "success": true, "mapped_pos_items": [ { "matched_drug": {...}, "confidence_score": 0.96, "needs_yellow_highlight": false } ] }`

`POST /smartcare/send-zns` `{ "PrescriptionID": 5 }` → `{ "success": true, "JourneyToken": "ZNS_...", "FollowUpDays": 30, "Status": "SENT" }`
`GET /smartcare/logs` † → danh sách tin gần nhất, `Status` là `SENT`, `DELIVERED` hoặc `READ` (mẫu: `smartcare-logs.json`)

### 6.5 Thanh tra lịch sử (Temporal Tables)
`GET /audit/as-of?table=InventoryBatches` (thêm `&as_of=2026-11-19 06:00:00` để xem trạng thái tại thời điểm đó; bỏ trống thì trả 50 biến động gần nhất). `table` hợp lệ: `InventoryBatches`, `Prescriptions`, `Patients`, `PatientAllergies`, `ControlledDrugLogs`.
→ `{ "success": true, "table": "InventoryBatches", "records": [ { "BatchCode": "...", "CurrentStock": "...", "ModifiedByName": "Trần Thị Thu Hà", "SysStartTime": "...", "SysEndTime": "..." } ] }`. Các bảng có cột khác nhau: dựng bảng động từ khóa của `records[0]`.

## 7. Dữ liệu demo và kiểm thử

- Tài khoản: `duocsi01` / `123456` (vai trò `PHARMACIST`). Chỉ dùng cho môi trường phát triển.
- Mã thuốc đã nạp sẵn: `PARA500`, `HAPACOL500`, `KLACID500`, `ZOCOR20`, `MORPHIN10` (thuốc kiểm soát đặc biệt, đuôi mã đơn `-N`).
- File JSON mẫu của mọi API GET: chạy `docs/get-api-samples.ps1` (trưởng nhóm), lưu ở `docs/api-samples/`. Dùng chúng để biết tên trường thật và làm dữ liệu giả.
- Mã bệnh nhân dùng thử: trưởng nhóm điền vào đây sau khi có danh sách: `PatientID` = …

## 8. Quy trình và Git

1. **Dựng nhanh bằng dữ liệu giả** (`USE_MOCK = true`, 1 đến 2 giờ) rồi **nối API thật** (`USE_MOCK = false`) và thử với backend ở `:3001`.
2. Thử đủ các nhánh lỗi: 401, 403, 422 (HARD_STOP, SOFT_STOP), 409, lỗi mạng.
3. Git: `git pull` → `git checkout -b ten-ban/ten-trang` → làm → `git add frontend/<file-của-bạn>` → `git commit` → `git push -u origin ten-ban/ten-trang` → tạo Pull Request, trưởng nhóm duyệt rồi gộp. Mỗi người một nhóm file nên không xung đột.
4. Trước khi nộp Pull Request: trang mở được ở `http://localhost:8082`, Console (F12) không có lỗi đỏ, bạn tự giải thích được từng đoạn code của mình.
