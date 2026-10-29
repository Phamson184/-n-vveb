## Quy định làm việc

Để bắt đầu làm việc, các bạn mở Terminal/PowerShell trên máy tính và copy-paste tuần tự các lệnh sau.
Bước 1: Kéo mã nguồn từ GitHub
PowerShell
git clone https://github.com/Phamson184/-n-vveb.git
cd -n-vveb

Bước 2: Thiết lập môi trường
PowerShell
Copy-Item -Path .env.example -Destination .env
(Nếu dùng macOS/Linux, thay bằng lệnh: cp .env.example .env)

Bước 3: Chạy máy chủ ảo (Yêu cầu đã mở Docker Desktop)
PowerShell
docker-compose up -d --build

Sau khi terminal chạy xong, mở trình duyệt:
Giao diện Web: http://localhost:8080
Test API: http://localhost:3000/api/thuoc/danhsach

**Vai trò**

| Người | Code | Ngoài code |
|---|---|---|
| Trưởng nhóm (Sơn) | Toàn bộ backend: API, CSDL, Docker, thanh toán FEFO, API chatbot | Giải thích kiến trúc trong báo cáo, trả lời phản biện kỹ thuật khi bảo vệ, duyệt Pull Request |
| TV2 | `pos.html`; giai đoạn 3: thuốc thay thế, quét đơn thuốc OCR | Thiết kế trải nghiệm phím tắt trang bán hàng; slide demo bán hàng |
| TV3 | `products.html`, `inventory.html`, `users.html` | Trưởng nhóm thiết kế: sở hữu `theme.css`, rà soát giao diện toàn dự án; slide quản lý kho |
| TV4 | `customers.html`, `orders.html`, `index.html` (Chart.js); giai đoạn 3: `js/chatbot.js` | Thư ký: gom báo cáo cuối kỳ, làm Slide Master chung |

**Quy tắc**
1. Chỉ sửa file được giao (xem `docs/AI_CONTEXT.md` mục 2). **Không sửa** `docker-compose.yml`, `database/`, `api-php/`.
2. Mỗi người một nhánh (`ten-ban/ten-trang`), xong tạo Pull Request, trưởng nhóm duyệt rồi mới gộp vào `main`. Đừng push thẳng vào `main`.
3. Làm theo 3 bước: dữ liệu giả → chạy được → nối API (đổi `USE_MOCK = false`). Cần API mới hay đổi API thì báo trưởng nhóm, sửa `docs/AI_CONTEXT.md` mục 6 trước.
4. Dùng AI theo `docs/AI_CONTEXT.md` và `docs/PROMPT_TEMPLATE.md`. Mỗi người **phải giải thích được code và slide của mình**. Ghi rõ phần có hỗ trợ AI trong báo cáo nếu môn học yêu cầu.
5. Mọi thay đổi `theme.css` đi qua TV3 bằng Pull Request; giao diện trang nào, người đó tự sửa theo góp ý.
6. Trước mỗi mốc, thử `git clone` sạch trên một máy khác rồi chạy `docker compose up --build` để chắc chắn ai cũng chạy được.

## Lộ trình 4 giai đoạn

| Giai đoạn | Mốc | Việc chính | Điều kiện qua giai đoạn |
|---|---|---|---|
| **1. Nền tảng** | __/__ | TV2–4: dựng giao diện và chức năng bằng dữ liệu giả cho trang của mình. TV3 chốt `theme.css`, TV4 làm Slide Master. Trưởng nhóm: API danh sách, CRUD thuốc, khách hàng, nhân viên, đăng nhập | Mọi trang mở được, đúng theme, Console không lỗi; API đã chạy được liệt kê trong `AI_CONTEXT.md` |
| **2. Nối API & nghiệm thu lõi** | __/__ | Đổi sang API thật. Trưởng nhóm hoàn tất thanh toán FEFO, hóa đơn, thống kê | Chạy được từ đầu đến cuối trên Docker sạch: đăng nhập → bán hàng → trừ kho theo lô → hóa đơn → thống kê |
| **3. Tính năng phụ** | __/__ | Chỉ bắt đầu khi giai đoạn 2 xong. Thứ tự: thuốc thay thế → chatbot → quét đơn thuốc OCR (rủi ro nhất, làm cuối, bỏ được nếu thiếu thời gian) | Mỗi tính năng demo được; tính năng nào chưa ổn thì tắt đi, không làm hỏng phần lõi |
| **4. Hoàn thiện & bảo vệ** | __/__ | Đóng băng code. TV2, TV3 và trưởng nhóm viết phần báo cáo và slide của mình; TV4 tổng hợp báo cáo, hợp nhất slide; cả nhóm diễn tập demo và hỏi đáp | Báo cáo, slide và kịch bản demo hoàn chỉnh; mỗi người trả lời được phần mình |
