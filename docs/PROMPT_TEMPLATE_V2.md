# Mẫu lệnh gửi AI làm giao diện PharmaPro v2.0

## Cách dùng (khoảng 2 phút mỗi lần)

1. Mở một cuộc trò chuyện **mới** với AI.
2. **Đính kèm:** `docs/AI_CONTEXT_V2.md`, `frontend/css/theme.css`, `frontend/_template.html`, các file `docs/api-samples/*.json` của API trang bạn dùng (tên file ghi ở cuối mỗi lệnh), và file trang hiện tại nếu đã có.
3. Dán lệnh của đúng trang bạn được giao ở mục 2.
4. Mở `http://localhost:8082/<ten-trang>.html` và thử bấm thật với backend đang chạy. Có lỗi thì dán nguyên thông báo trong Console (F12) cho AI, không tự đoán.

Đăng nhập thử bằng `duocsi01` / `123456`. Muốn thử vai trò khác, nhờ trưởng nhóm cấp tài khoản.

---

## 1. Lệnh khung (dùng cho mọi trang)

```
Bạn là lập trình viên frontend của dự án PharmaPro v2.0. Tôi đính kèm AI_CONTEXT_V2.md (quy tắc và hợp đồng API),
theme.css, _template.html và các file JSON mẫu của API. Tuân thủ nghiêm ngặt AI_CONTEXT_V2.md.

TRANG CẦN LÀM: <tên file>
MỤC TIÊU: <1 câu>
TÍNH NĂNG BẮT BUỘC:
- <tính năng 1>
- <tính năng 2>
API DÙNG (số ở mục 6): <ví dụ 13, 12, 11>

Yêu cầu chung:
- Bắt đầu từ _template.html; chỉ dùng class có trong theme.css, JS thuần, chỉ dùng thư viện ở mục 3.1.
- Mọi số và cờ từ API là CHUỖI: dùng num() và flag(), không viết if (d.IsControlled). Dữ liệu giả cũng phải đúng kiểu chuỗi.
- Không đoán tên trường: chỉ dùng tên trong AI_CONTEXT_V2.md hoặc file JSON mẫu; thiếu thì ghi vào mục "Cần API".
- Xử lý 401, 403, 422, lỗi mạng. Mỗi khối dữ liệu tự xử lý 403 riêng, không làm vỡ cả trang.
- Trả về FILE HTML HOÀN CHỈNH, không rút gọn. Sau code, liệt kê ngắn: (a) cách tôi kiểm thử từng tính năng,
  (b) mục "Cần API" nếu thấy hợp đồng thiếu gì.
```

---

## 2. Lệnh soạn sẵn cho từng thành viên

### Thành viên 2 – Lâm sàng: `patients.html`, `pos.html`

> Làm `patients.html` trước (đơn giản hơn, và `pos.html` cần bệnh nhân để kê đơn).

**`patients.html` – Hồ sơ dược bạ 360°** (API 2, 11, 12, 13; mẫu: `catalogs-init.json`, `patients-360.json`)
```
TRANG CẦN LÀM: patients.html
MỤC TIÊU: tạo bệnh nhân và xem hồ sơ dược bạ 360° gồm thận, dị ứng, lịch sử đơn.
TÍNH NĂNG BẮT BUỘC:
1. Modal thêm bệnh nhân (POST /clinical/patients): PatientCode, FullName, Phone (10 số, bắt đầu bằng 0), DateOfBirth, Gender (M/F),
   Weight_Kg, SerumCreatinine, PatientType (Thường / VIP / Bệnh mạn tính), ChronicCondition. Kiểm tra bắt buộc nhập, ô sai tô viền đỏ.
2. Ô nhập PatientID (số) hoặc đọc từ ?id= trên địa chỉ để xem hồ sơ (GET /clinical/patients/360):
   - Thẻ calculated_CrCl (mL/phút): dưới 30 badge đỏ "Suy thận nặng - cần giảm liều", 30 đến 60 badge vàng, trên 60 badge xanh.
   - Bảng dị ứng allergies_INN và form thêm dị ứng (POST /clinical/allergies): chọn hoạt chất từ active_ingredients của
     GET /catalogs/init, mức Severity "Hard Stop" (đỏ) hoặc "Soft Stop" (vàng), ReactionNote.
   - Bảng prescription_history: mã đơn 14 ký tự, chẩn đoán, trạng thái; bấm vào mã đơn thì mở prescriptions.html?id=<PrescriptionID>.
3. Sau khi thêm bệnh nhân hoặc dị ứng thành công thì tải lại hồ sơ. Hiển thị rõ PatientID sau khi tạo để dùng ở pos.html.
Tự kiểm tra: dị ứng Hard Stop hiện đỏ; tạo bệnh nhân xong thấy PatientID; vai trò không đủ quyền thì báo 403 gọn gàng.
```

**`pos.html` – Kê đơn và bán hàng, tích hợp CDSS, thuốc thay thế, OCR** (API 3, 4, 15, 19; mẫu: `inventory-drugs.json`, `inventory-substitutes.json`)
```
TRANG CẦN LÀM: pos.html (nếu đã có bản cũ, nâng cấp từ đó theo hợp đồng v2).
MỤC TIÊU: lập đơn thuốc nhanh bằng bàn phím; hệ thống cảnh báo an toàn lâm sàng; đơn tạo ra ở trạng thái Pending.
TÍNH NĂNG BẮT BUỘC:
1. Tải thuốc từ GET /inventory/drugs. Ô tìm kiếm theo DrugName và IngredientName; phím tắt F1 tìm thuốc, F3 tạo đơn, F4 xóa giỏ
   (luôn preventDefault). Chọn thuốc thì thêm vào giỏ (mảng JS), tăng/giảm số lượng, không vượt CurrentStock. Badge "Kê đơn"
   nếu flag(IsPrescriptionOnly), badge đỏ "Kiểm soát đặc biệt" nếu flag(IsControlled). Tổng tiền cộng bằng num(CurrentPrice).
2. Thuốc thay thế: khi CurrentStock bằng 0 hoặc người dùng bấm "Thuốc thay thế", gọi GET /inventory/drugs/substitutes?DrugID=...
   và mở modal liệt kê thuốc (tên, giá, tồn, FurthestExpiryDate) kèm price_window; bấm chọn thì đổi vào giỏ.
3. Thông tin đơn: PatientID (số, kèm liên kết mở patients.html?id=...), Diagnosis, đơn mạn tính (IsChronic, ChronicDurationDays 30/90),
   ControlledType (C mặc định; giỏ có thuốc kiểm soát đặc biệt thì bắt buộc chọn N hoặc H). Mỗi dòng có DosageInstruction.
4. Gửi POST /prescriptions (ghép items: DrugID, PrescribedQty, DosageInstruction, OverrideReason). Xử lý kết quả:
   - 422 với e.data.blocked_by === "HARD_STOP": modal cảnh báo ĐỎ liệt kê safety_report.hard_stops, khóa nút lưu, không cho vượt.
   - 422 với "SOFT_STOP": khung VÀNG liệt kê safety_report.soft_stops; mỗi thuốc bị cảnh báo có ô nhập OverrideReason bắt buộc,
     rồi cho gửi lại.
   - 200: báo thành công kèm NationalCode và nút/liên kết sang prescriptions.html?id=<PrescriptionID>; xóa giỏ.
5. Nút "Nhập từ OCR": modal nhập bảng nhiều dòng (drug_name, concentration, quantity, dosage_instruction, confidence_score), có thể
   dán JSON. Gửi POST /prescriptions/ocr-map, hiển thị mapped_pos_items; dòng needs_yellow_highlight === true tô vàng (tr-warn) và phải
   được tích xác nhận thủ công; dòng đã tích mới được "Thêm vào giỏ".
Tự kiểm tra: thử F1/F3/F4; thử một đơn bị HARD_STOP và một đơn SOFT_STOP (nhờ trưởng nhóm cung cấp bệnh nhân và thuốc để tái hiện);
thử đơn có MORPHIN10 bắt buộc chọn ControlledType N hoặc H.
```

---

### Thành viên 3 – Kho và thanh tra: `inventory.html`, `products.html`, `audit.html`

**`inventory.html` – Lô cận date, nhập lô, sổ thuốc kiểm soát đặc biệt** (API 3, 6, 7, 8, 9; mẫu: `inventory-expiring.json`, `inventory-controlled-logs.json`, `inventory-drugs.json`)
```
TRANG CẦN LÀM: inventory.html
MỤC TIÊU: theo dõi lô sắp hết hạn, nhập lô, và quản lý sổ thuốc kiểm soát đặc biệt.
TÍNH NĂNG BẮT BUỘC:
1. Tab 1 "Lô cận date" (GET /inventory/expiring-batches?days=): bộ chọn 30 / 60 / 90 ngày; bảng BatchCode, DrugName, ExpiryDate,
   DaysRemaining, CurrentStock. Tô đỏ dòng có DaysRemaining <= 30 (tr-danger), tô vàng 31 đến 90 (tr-warn). Hiện đếm số lô mỗi mức.
2. Modal "Nhập lô mới" (POST /inventory/batches): chọn thuốc từ GET /inventory/drugs; BatchCode, ManufactureDate, ExpiryDate (phải lớn hơn
   ngày sản xuất), Quantity, ImportPrice. Nếu thuốc có flag(IsControlled) thì hiện thêm ô bắt buộc HandoverReportCode (Biên bản giao nhận).
   Lỗi 422 CONTROLLED_DRUG_PROTOCOL hiện nguyên văn. Nút này chỉ hiện với ADMIN, WAREHOUSE (data-roles).
3. Tab 2 "Sổ thuốc kiểm soát đặc biệt" (GET /inventory/controlled-logs): bảng TransactionType, DrugName, BatchCode, QuantityChange,
   CabinetPhysicalStock, SystemStock, HandoverReportCode, NationalCode, PerformedBy, RecordedAt. Dòng có CabinetPhysicalStock khác SystemStock
   tô đỏ và hiện cảnh báo "Lệch số liệu".
4. Nút "Kiểm kê tủ khóa" (POST /inventory/controlled-audit): chọn thuốc và lô, nhập số tồn thực tế CabinetPhysicalStock và
   HandoverReportCode; hiện kết quả đối chiếu và tải lại sổ.
Tự kiểm tra: đổi bộ lọc ngày thấy số dòng đổi; nhập lô thuốc thường thành công; nhập lô MORPHIN10 mà thiếu mã biên bản thì báo lỗi.
```

**`products.html` – Danh mục thuốc và ma trận tương tác DDI** (API 2, 3, 5; mẫu: `catalogs-init.json`, `inventory-drugs.json`)
```
TRANG CẦN LÀM: products.html
MỤC TIÊU: tra cứu danh mục thuốc, thêm thuốc mới, xem ma trận tương tác thuốc.
TÍNH NĂNG BẮT BUỘC:
1. Tải GET /inventory/drugs và GET /catalogs/init. Bảng: DrugCode, DrugName, IngredientName, FormName, Concentration, DeptName,
   CurrentPrice (money(num(...))), CurrentStock. Ô tìm kiếm và lọc theo khoa (departments) và hoạt chất.
2. Badge: đỏ "Kiểm soát đặc biệt" nếu flag(IsControlled); vàng "Kê đơn" nếu flag(IsPrescriptionOnly); cảnh báo tồn thấp nếu num(CurrentStock) < 30.
3. Modal thêm thuốc (POST /inventory/drugs) với ô chọn lấy từ active_ingredients, dosage_forms, departments; DrugCode, DrugName,
   Concentration, Manufacturer, IsPrescriptionOnly, IsControlled, MinSafeCrCl, CurrentPrice. Kiểm tra bắt buộc nhập và giá lớn hơn 0.
   Nút này chỉ hiện với ADMIN, WAREHOUSE (data-roles); lỗi 409 (trùng mã) hiện nguyên văn.
4. Tab "Ma trận tương tác (DDI)": hiển thị interactions từ /catalogs/init, tô đỏ mức Contraindicated, vàng mức Moderate, có ô lọc theo hoạt chất.
Tự kiểm tra: badge hiện đúng cho MORPHIN10; tài khoản PHARMACIST không thấy nút Thêm thuốc; tab DDI lọc đúng.
```

**`audit.html` – Thanh tra lịch sử (Temporal Tables)** (API 21; mẫu: `audit-InventoryBatches.json` và các file `audit-*.json`)
```
TRANG CẦN LÀM: audit.html
MỤC TIÊU: truy vết ai sửa gì, lúc nào, và xem dữ liệu tại một thời điểm trong quá khứ.
TÍNH NĂNG BẮT BUỘC:
1. Bộ lọc: chọn bảng (InventoryBatches, Prescriptions, Patients, PatientAllergies, ControlledDrugLogs) và ô thời điểm as_of
   (định dạng YYYY-MM-DD HH:MM:SS; để trống thì xem 50 biến động gần nhất). Kiểm tra định dạng as_of trước khi gửi.
2. Nút "Truy xuất lịch sử" (GET /audit/as-of?table=&as_of=): dựng bảng ĐỘNG từ khóa của records[0] vì mỗi bảng có cột khác nhau.
   Làm nổi bật cột người thực hiện (ModifiedByName, EmployeeCode nếu có) và hiệu lực thời gian (SysStartTime, SysEndTime) bằng màu nền riêng.
   Hiển thị số bản ghi; không có dữ liệu thì hiện trạng thái rỗng.
3. Mọi giá trị chèn vào bảng phải qua esc(); ô ngày giờ định dạng dd/mm/yyyy HH:mm.
Tự kiểm tra: đổi bảng thì cột đổi theo; để trống as_of vẫn ra danh sách; 403 báo gọn gàng.
```

---

### Thành viên 4 – Vòng đời đơn và tổng quan: `prescriptions.html`, `index.html`

**`prescriptions.html` – Thẩm định ký số, xuất kho FEFO, hoàn tất, Zalo ZNS** (API 14, 16, 17, 18, 20; mẫu: `prescriptions-list.json`, `prescriptions-detail.json`)
```
TRANG CẦN LÀM: prescriptions.html
MỤC TIÊU: đưa đơn thuốc đi hết vòng đời Pending → Validated → Dispensed → Completed (hoặc Rejected).
TÍNH NĂNG BẮT BUỘC:
1. Bộ lọc "Tất cả" và 5 trạng thái Pending, Validated, Dispensed, Completed, Rejected (GET /prescriptions?Status=). Đọc ?status= trên
   địa chỉ để chọn sẵn bộ lọc. Bảng: NationalCode, PatientName, Diagnosis, Status (chip màu), TotalAmount, CreatedAt.
2. Nút "Chi tiết" (GET /prescriptions?PrescriptionID=): modal gồm thông tin đơn, danh sách thuốc kê (PrescribedQty, DosageInstruction) và
   bảng lô đã xuất tách dòng FEFO (dispensed_batches: BatchCode, ExpiryDate, DispensedQty, SubTotal). Đọc ?id= trên địa chỉ để tự mở modal đơn đó.
3. Nút theo đúng trạng thái và vai trò (data-roles), sau mỗi thao tác tải lại danh sách:
   - Pending: "Thẩm định và ký số" (ADMIN, PHARMACIST): form ClinicalInterventionNote, DigitalSignature (bắt buộc, ví dụ VNPT_CA_SHA256_DUOCSI01),
     chọn Validated hoặc Rejected (POST /prescriptions/validate).
   - Validated: "Xuất kho FEFO" (POST /prescriptions/dispense). Nếu NationalCode kết thúc bằng -N hoặc -H bắt buộc hiện ô HandoverReportCode
     trước khi gửi. Sau khi xuất, hiện kết quả tách dòng từ dispense_details (DrugName, TotalDispensed, LineSplitCount, Batches).
   - Dispensed: "Hoàn tất thanh toán" (POST /prescriptions/complete), hiện EarnedLoyaltyPoints.
   - Dispensed hoặc Completed: "Gửi Zalo ZNS" (POST /smartcare/send-zns), hiện JourneyToken và FollowUpDays.
     Ghi chú trên giao diện rằng chữ ký số và ZNS là mô phỏng.
4. Lỗi 422 CONTROLLED_DRUG_PROTOCOL và các lỗi khác hiện nguyên văn câu thông báo; nút đang xử lý phải bị khóa để tránh bấm hai lần.
Tự kiểm tra: đi hết một đơn từ Pending đến Completed; thử xuất kho đơn MORPHIN10 khi bỏ trống mã biên bản; tài khoản không đủ quyền không thấy nút tương ứng.
```

**`index.html` – Tổng quan điều hành** (API 7, 10, 14, 22; mẫu: `prescriptions-list.json`, `inventory-expiring.json`, `reports-department-consumption.json`, `smartcare-logs.json`)
```
TRANG CẦN LÀM: index.html (đã có khung bản v1, nâng cấp theo v2; đính kèm index.html hiện tại nếu có).
MỤC TIÊU: bảng điều hành cho nhà thuốc GPP và Hội đồng thuốc.
TÍNH NĂNG BẮT BUỘC:
1. Khi mở trang gọi song song 4 API bằng Promise.allSettled, mỗi khối tự hiện lỗi hoặc 403 riêng:
   - GET /prescriptions: thẻ tổng số đơn, số đơn Pending (bấm vào mở prescriptions.html?status=Pending), số đơn Completed.
   - GET /inventory/expiring-batches?days=90: thẻ số lô cận date và danh sách lô cần ưu tiên xả (tô đỏ nếu DaysRemaining <= 30).
   - GET /reports/department-consumption: biểu đồ cột Chart.js 4.4.1 so sánh TotalDispensedValue theo DeptName
     (và ControlledQtyDispensed nếu thể hiện được).
   - GET /smartcare/logs: danh sách tin chăm sóc Zalo ZNS gần nhất với trạng thái SENT, DELIVERED, READ.
2. Số liệu tính bằng num(); tiền dùng money(). Biểu đồ phải hủy (destroy) và vẽ lại đúng khi tải lại dữ liệu.
Tự kiểm tra: tắt API này thì chỉ khối đó báo lỗi; tài khoản không có quyền ở khối nào thì khối đó hiện "Không có quyền xem" thay vì vỡ trang.
```

---

## 3. Danh sách tự kiểm tra trước khi tạo Pull Request

- [ ] Trang mở được ở `http://localhost:8082`, Console (F12) không có lỗi đỏ.
- [ ] Chỉ sửa file của mình; khung chung và khối hàm dùng chung vẫn nguyên vẹn.
- [ ] Không có `if (d.IsControlled)` hay phép cộng trực tiếp trên chuỗi; đã dùng `flag()` và `num()`.
- [ ] Đã thử các nhánh lỗi: 401, 403, 422, 409, tắt backend.
- [ ] Không còn mã màu gõ tay; mọi dữ liệu chèn vào trang qua `esc()`.
- [ ] Bạn tự giải thích được từng đoạn code mình nộp (giảng viên có thể hỏi riêng từng người).
