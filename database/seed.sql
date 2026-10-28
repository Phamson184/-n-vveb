-- ============================================================================
-- DỮ LIỆU MẪU cố định cho QuanLyNhaThuocDB (chạy SAU ddl.sql).
-- Ngày sản xuất / hạn dùng tính theo ngày hôm nay => lúc nào chạy cũng qua được Rule 1 (hạn dùng >= 6 tháng).
-- Tài khoản dược sĩ mẫu: username duocsi01 / mật khẩu 123456 (chỉ dùng khi phát triển).
-- ============================================================================
USE QuanLyNhaThuocDB;
GO
SET NOCOUNT ON;
GO

-- 1. Nhóm thuốc
INSERT INTO NHOM_THUOC (MaNhom, TenNhom, MoTa) VALUES
    ('NT001', N'Giảm đau - hạ sốt', N'Thuốc giảm đau, hạ sốt, kháng viêm thông dụng'),
    ('NT002', N'Kháng sinh', N'Thuốc kháng sinh (đa số bán theo đơn)'),
    ('NT003', N'Vitamin và khoáng chất', N'Vitamin, khoáng chất, thực phẩm bổ sung'),
    ('NT004', N'Tiêu hóa', N'Thuốc dạ dày, tiêu chảy, bù nước'),
    ('NT005', N'Hô hấp - ho', N'Thuốc long đờm, giảm ho'),
    ('NT006', N'Dị ứng', N'Thuốc kháng histamin');
GO

-- 2. Nhà cung cấp
INSERT INTO NHA_CUNG_CAP (MaNCC, TenNCC, SoDienThoai, DiaChi, GiayPhepKD) VALUES
    ('NCC001', N'Công ty CP Dược phẩm Minh An', '0280000001', N'12 Nguyễn Văn Cừ, Quận 5, TP.HCM', 'GPKD-0001'),
    ('NCC002', N'Công ty CP Dược Bảo Châu', '0280000002', N'85 Lê Lợi, Quận 1, TP.HCM', 'GPKD-0002'),
    ('NCC003', N'Công ty TNHH Dược Vạn Phúc', '0280000003', N'240 Trần Hưng Đạo, TP. Cần Thơ', 'GPKD-0003'),
    ('NCC004', N'Công ty CP Dược Hòa Bình', '0280000004', N'31 Điện Biên Phủ, TP. Biên Hòa', 'GPKD-0004');
GO

-- 3. Thuốc (LoaiThuoc: 0 = không kê đơn, 1 = kê đơn; GiaBan: VNĐ / 1 đơn vị quy cách)
INSERT INTO THUOC (MaThuoc, MaNhom, MaNCC, TenThuoc, SoDangKy, HoatChat, QuyCach, LoaiThuoc, GiaBan) VALUES
    ('T0001', 'NT001', 'NCC001', N'Paracetamol 500mg', 'VD-27101-17', N'Paracetamol', N'Hộp 10 vỉ x 10 viên', 0, 35000),
    ('T0002', 'NT001', 'NCC001', N'Ibuprofen 400mg', 'VD-27102-18', N'Ibuprofen', N'Hộp 10 vỉ x 10 viên', 0, 52000),
    ('T0003', 'NT001', 'NCC002', N'Aspirin 81mg', 'VD-27103-19', N'Acid acetylsalicylic', N'Hộp 10 vỉ x 10 viên', 0, 45000),
    ('T0004', 'NT001', 'NCC002', N'Diclofenac 50mg', 'VD-27104-20', N'Diclofenac natri', N'Hộp 10 vỉ x 10 viên', 1, 42000),
    ('T0005', 'NT002', 'NCC003', N'Amoxicillin 500mg', 'VD-27105-21', N'Amoxicillin trihydrat', N'Hộp 10 vỉ x 10 viên', 1, 78000),
    ('T0006', 'NT002', 'NCC003', N'Azithromycin 250mg', 'VD-27106-16', N'Azithromycin', N'Hộp 1 vỉ x 6 viên', 1, 65000),
    ('T0007', 'NT002', 'NCC003', N'Cefuroxime 500mg', 'VD-27107-17', N'Cefuroxim axetil', N'Hộp 2 vỉ x 5 viên', 1, 120000),
    ('T0008', 'NT002', 'NCC004', N'Ciprofloxacin 500mg', 'VD-27108-18', N'Ciprofloxacin', N'Hộp 1 vỉ x 10 viên', 1, 55000),
    ('T0009', 'NT002', 'NCC004', N'Metronidazol 250mg', 'VD-27109-19', N'Metronidazol', N'Hộp 10 vỉ x 10 viên', 1, 30000),
    ('T0010', 'NT003', 'NCC001', N'Vitamin C 500mg', 'VD-27110-20', N'Acid ascorbic', N'Lọ 100 viên', 0, 45000),
    ('T0011', 'NT003', 'NCC001', N'Vitamin B1 B6 B12', 'VD-27111-21', N'Thiamin, Pyridoxin, Cyanocobalamin', N'Hộp 10 vỉ x 10 viên', 0, 38000),
    ('T0012', 'NT003', 'NCC002', N'Calci D3', 'VD-27112-16', N'Calci carbonat, Cholecalciferol', N'Lọ 60 viên', 0, 95000),
    ('T0013', 'NT003', 'NCC002', N'Kẽm Gluconat 70mg', 'VD-27113-17', N'Kẽm gluconat', N'Hộp 10 vỉ x 10 viên', 0, 72000),
    ('T0014', 'NT004', 'NCC004', N'Omeprazol 20mg', 'VD-27114-18', N'Omeprazol', N'Hộp 3 vỉ x 10 viên', 1, 40000),
    ('T0015', 'NT004', 'NCC001', N'Berberin 100mg', 'VD-27115-19', N'Berberin clorid', N'Lọ 100 viên', 0, 18000),
    ('T0016', 'NT004', 'NCC003', N'Oresol', 'VD-27116-20', N'Natri clorid, Kali clorid, Natri citrat, Glucose', N'Hộp 20 gói', 0, 52000),
    ('T0017', 'NT004', 'NCC003', N'Diosmectit 3g', 'VD-27117-21', N'Diosmectit', N'Hộp 30 gói', 0, 125000),
    ('T0018', 'NT005', 'NCC002', N'Acetylcystein 200mg', 'VD-27118-16', N'Acetylcystein', N'Hộp 30 gói', 0, 85000),
    ('T0019', 'NT005', 'NCC004', N'Bromhexin 8mg', 'VD-27119-17', N'Bromhexin hydroclorid', N'Hộp 10 vỉ x 10 viên', 0, 25000),
    ('T0020', 'NT006', 'NCC001', N'Loratadin 10mg', 'VD-27120-18', N'Loratadin', N'Hộp 10 vỉ x 10 viên', 0, 28000);
GO

-- 4. Lô thuốc: mỗi thuốc 2 lô. Có 3 lô cận hạn (còn ~7 tháng) và 2 thuốc tồn thấp để demo cảnh báo trên trang Tổng quan
INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLo, NgaySanXuat, HanSuDung, SoLuongTon)
SELECT 'LO' + v.MaThuoc + '-' + CAST(v.STT AS VARCHAR(1)),
       v.MaThuoc,
       'BATCH-' + RIGHT(v.MaThuoc, 4) + '-' + CAST(v.STT AS VARCHAR(1)),
       DATEADD(MONTH, -v.ThangSX, CAST(GETDATE() AS DATE)),
       DATEADD(MONTH,  v.ThangHan, CAST(GETDATE() AS DATE)),
       v.SoLuong
FROM (VALUES
    ('T0001', 1, 8, 16, 5),
    ('T0001', 2, 2, 34, 3),
    ('T0002', 1, 8, 16, 66),
    ('T0002', 2, 2, 34, 138),
    ('T0003', 1, 8, 16, 79),
    ('T0003', 2, 2, 34, 167),
    ('T0004', 1, 8, 16, 92),
    ('T0004', 2, 2, 34, 196),
    ('T0005', 1, 8, 7, 45),
    ('T0005', 2, 2, 34, 105),
    ('T0006', 1, 8, 16, 58),
    ('T0006', 2, 2, 34, 134),
    ('T0007', 1, 8, 16, 71),
    ('T0007', 2, 2, 34, 163),
    ('T0008', 1, 8, 16, 84),
    ('T0008', 2, 2, 34, 192),
    ('T0009', 1, 8, 16, 97),
    ('T0009', 2, 2, 34, 101),
    ('T0010', 1, 8, 7, 50),
    ('T0010', 2, 2, 34, 130),
    ('T0011', 1, 8, 16, 63),
    ('T0011', 2, 2, 34, 159),
    ('T0012', 1, 8, 16, 76),
    ('T0012', 2, 2, 34, 188),
    ('T0013', 1, 8, 16, 89),
    ('T0013', 2, 2, 34, 97),
    ('T0014', 1, 8, 7, 42),
    ('T0014', 2, 2, 34, 126),
    ('T0015', 1, 8, 16, 9),
    ('T0015', 2, 2, 34, 6),
    ('T0016', 1, 8, 16, 68),
    ('T0016', 2, 2, 34, 184),
    ('T0017', 1, 8, 16, 81),
    ('T0017', 2, 2, 34, 93),
    ('T0018', 1, 8, 16, 94),
    ('T0018', 2, 2, 34, 122),
    ('T0019', 1, 8, 16, 47),
    ('T0019', 2, 2, 34, 151),
    ('T0020', 1, 8, 16, 60),
    ('T0020', 2, 2, 34, 180)
) AS v (MaThuoc, STT, ThangSX, ThangHan, SoLuong);
GO

-- 5. Nhân viên: tạo bằng stored procedure để mật khẩu được băm + salt (Rule 15)
EXEC sp_CreateNhanVien @MaNhanVien = 'NV001', @HoTen = N'Trần Thị Thu Hà', @ChucVu = N'Dược sĩ đại học',
     @ChungChi = 'CCHN-00001', @Username = 'duocsi01', @PlainPassword = '123456';
GO

-- 6. Khách hàng và bác sĩ
INSERT INTO KHACH_HANG (MaKhachHang, HoTen, SoDienThoai, DiemTichLuy) VALUES
    ('KH001', N'Nguyễn Thị Lan',  '0900000001', 120),
    ('KH002', N'Trần Văn Minh',   '0900000002', 45),
    ('KH003', N'Lê Thị Hoa',      '0900000003', 0),
    ('KH004', N'Phạm Quốc Bảo',   '0900000004', 300),
    ('KH005', N'Võ Thị Mai',      '0900000005', 15);
INSERT INTO BAC_SI (MaBacSi, HoTen, NoiCongTac, ChuyenKhoa) VALUES
    ('BS001', N'BS. Lê Quốc Hùng',   N'Bệnh viện Đa khoa Thành phố', N'Nội tổng quát'),
    ('BS002', N'BS. Nguyễn Thu Trang', N'Phòng khám Đa khoa An Phúc',  N'Nhi khoa'),
    ('BS003', N'BS. Phạm Đức Long',   N'Bệnh viện Hô hấp Trung ương',  N'Hô hấp');
GO

-- Kiểm tra nhanh: số dòng của từng bảng sau khi nạp
SELECT 'NHOM_THUOC' AS Bang, COUNT(*) AS SoDong FROM NHOM_THUOC UNION ALL
SELECT 'NHA_CUNG_CAP', COUNT(*) FROM NHA_CUNG_CAP UNION ALL
SELECT 'THUOC', COUNT(*) FROM THUOC UNION ALL
SELECT 'LO_THUOC', COUNT(*) FROM LO_THUOC UNION ALL
SELECT 'NHAN_VIEN', COUNT(*) FROM NHAN_VIEN UNION ALL
SELECT 'KHACH_HANG', COUNT(*) FROM KHACH_HANG UNION ALL
SELECT 'BAC_SI', COUNT(*) FROM BAC_SI;
GO
