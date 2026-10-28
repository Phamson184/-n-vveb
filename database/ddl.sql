-- ============================================================================
-- DỰ ÁN: CƠ SỞ DỮ LIỆU QUẢN LÝ NHÀ THUỐC BÁN LẺ (ĐẠT CHUẨN GPP)
-- TÁC GIẢ CHUẨN HÓA: SENIOR DATABASE ARCHITECT
-- MỤC ĐÍCH: KỊCH BẢN T-SQL DDL VÀ LẬP TRÌNH BẢO VỆ DỮ LIỆU (15 RULES)
-- ============================================================================

-- CẢNH BÁO: script này XÓA rồi TẠO LẠI toàn bộ CSDL QuanLyNhaThuocDB (mất hết dữ liệu cũ).
-- Chạy lại bao nhiêu lần cũng không lỗi => dùng để "reset" CSDL về trạng thái sạch. Chạy ddl.sql trước, seed.sql sau.
IF DB_ID(N'QuanLyNhaThuocDB') IS NOT NULL
BEGIN
    ALTER DATABASE QuanLyNhaThuocDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE QuanLyNhaThuocDB;
END
GO
CREATE DATABASE QuanLyNhaThuocDB;
GO
USE QuanLyNhaThuocDB;
GO

-- 1. BẢNG NHÓM THUỐC
CREATE TABLE NHOM_THUOC (
    MaNhom VARCHAR(20) NOT NULL,
    TenNhom NVARCHAR(100) NOT NULL,
    MoTa NVARCHAR(500) NULL,
    NgayTao DATETIME NOT NULL CONSTRAINT DF_NHOM_THUOC_NgayTao DEFAULT GETDATE(),
    CONSTRAINT PK_NHOM_THUOC PRIMARY KEY (MaNhom)
);

-- 2. BẢNG NHÀ CUNG CẤP (Rule 13)
CREATE TABLE NHA_CUNG_CAP (
    MaNCC VARCHAR(20) NOT NULL,
    TenNCC NVARCHAR(255) NOT NULL,
    SoDienThoai VARCHAR(15) NULL,
    DiaChi NVARCHAR(500) NULL,
    GiayPhepKD VARCHAR(50) NOT NULL,
    CONSTRAINT PK_NHA_CUNG_CAP PRIMARY KEY (MaNCC)
);

-- 3. BẢNG BÁC SĨ
CREATE TABLE BAC_SI (
    MaBacSi VARCHAR(20) NOT NULL,
    HoTen NVARCHAR(100) NOT NULL,
    NoiCongTac NVARCHAR(255) NULL,
    ChuyenKhoa NVARCHAR(100) NULL,
    CONSTRAINT PK_BAC_SI PRIMARY KEY (MaBacSi)
);

-- 4. BẢNG KHÁCH HÀNG (Rule 9)
CREATE TABLE KHACH_HANG (
    MaKhachHang VARCHAR(20) NOT NULL,
    HoTen NVARCHAR(100) NOT NULL,
    SoDienThoai VARCHAR(15) NULL,
    DiemTichLuy INT NOT NULL CONSTRAINT DF_KHACH_HANG_DiemTichLuy DEFAULT 0,
    LichSuDung NVARCHAR(MAX) NULL,
    CONSTRAINT PK_KHACH_HANG PRIMARY KEY (MaKhachHang),
    CONSTRAINT CK_KHACH_HANG_DiemTichLuy CHECK (DiemTichLuy >= 0)
);

-- 5. BẢNG NHÂN VIÊN / DƯỢC SĨ (Rule 12, Rule 15)
CREATE TABLE NHAN_VIEN (
    MaNhanVien VARCHAR(20) NOT NULL,
    HoTen NVARCHAR(100) NOT NULL,
    ChucVu NVARCHAR(50) NOT NULL,
    ChungChi VARCHAR(50) NULL,
    Username VARCHAR(50) NOT NULL,
    PasswordHash VARCHAR(256) NOT NULL,
    CONSTRAINT PK_NHAN_VIEN PRIMARY KEY (MaNhanVien),
    CONSTRAINT UQ_NHAN_VIEN_ChungChi UNIQUE (ChungChi),
    CONSTRAINT UQ_NHAN_VIEN_Username UNIQUE (Username)
);

-- 6. BẢNG THUỐC (Rule 7)
CREATE TABLE THUOC (
    MaThuoc VARCHAR(20) NOT NULL,
    MaNhom VARCHAR(20) NOT NULL,
    MaNCC VARCHAR(20) NOT NULL,
    TenThuoc NVARCHAR(255) NOT NULL,
    SoDangKy VARCHAR(50) NOT NULL,
    HoatChat NVARCHAR(255) NOT NULL,
    QuyCach NVARCHAR(100) NOT NULL,
    LoaiThuoc BIT NOT NULL,  -- 0: không kê đơn, 1: thuốc kê đơn
    GiaBan DECIMAL(18,0) NOT NULL CONSTRAINT DF_THUOC_GiaBan DEFAULT 0,  -- giá bán lẻ theo quy cách (VNĐ)
    MaThuocQuocGia AS (CAST(SoDangKy AS VARCHAR(50)) + '-' + CAST(QuyCach AS VARCHAR(50))) PERSISTED,
    CONSTRAINT CK_THUOC_GiaBan CHECK (GiaBan >= 0),
    CONSTRAINT PK_THUOC PRIMARY KEY (MaThuoc),
    CONSTRAINT FK_THUOC_NHOM_THUOC FOREIGN KEY (MaNhom) REFERENCES NHOM_THUOC(MaNhom),
    CONSTRAINT FK_THUOC_NHA_CUNG_CAP FOREIGN KEY (MaNCC) REFERENCES NHA_CUNG_CAP(MaNCC)
);

-- 7. BẢNG ĐƠN THUỐC ĐIỆN TỬ (Rule 14)
CREATE TABLE DON_THUOC (
    MaDonThuoc VARCHAR(50) NOT NULL,
    MaBacSi VARCHAR(20) NOT NULL,
    MaKhachHang VARCHAR(20) NOT NULL,
    NgayKeDon DATE NOT NULL,
    ChanDoan NVARCHAR(500) NULL,
    CONSTRAINT PK_DON_THUOC PRIMARY KEY (MaDonThuoc),
    CONSTRAINT FK_DON_THUOC_BAC_SI FOREIGN KEY (MaBacSi) REFERENCES BAC_SI(MaBacSi) ON DELETE NO ACTION,
    CONSTRAINT FK_DON_THUOC_KHACH_HANG FOREIGN KEY (MaKhachHang) REFERENCES KHACH_HANG(MaKhachHang)
);

-- 8. BẢNG LÔ THUỐC KHO (Rule 3, Rule 4)
CREATE TABLE LO_THUOC (
    MaLo VARCHAR(30) NOT NULL,
    MaThuoc VARCHAR(20) NOT NULL,
    SoLo VARCHAR(50) NOT NULL,
    NgaySanXuat DATE NOT NULL,
    HanSuDung DATE NOT NULL,
    SoLuongTon INT NOT NULL CONSTRAINT DF_LO_THUOC_SoLuongTon DEFAULT 0,
    CONSTRAINT PK_LO_THUOC PRIMARY KEY (MaLo),
    CONSTRAINT FK_LO_THUOC_THUOC FOREIGN KEY (MaThuoc) REFERENCES THUOC(MaThuoc),
    CONSTRAINT CK_LO_THUOC_SoLuongTon CHECK (SoLuongTon >= 0),
    CONSTRAINT CK_LO_THUOC_DateValidity CHECK (NgaySanXuat < HanSuDung)
);

-- 9. BẢNG PHIẾU NHẬP KHO
CREATE TABLE PHIEU_NHAP (
    MaPhieuNhap VARCHAR(30) NOT NULL,
    MaNCC VARCHAR(20) NOT NULL,
    MaNhanVien VARCHAR(20) NOT NULL,
    NgayNhap DATETIME NOT NULL CONSTRAINT DF_PHIEU_NHAP_NgayNhap DEFAULT GETDATE(),
    TongGiaTri DECIMAL(18, 2) NOT NULL CONSTRAINT DF_PHIEU_NHAP_TongGiaTri DEFAULT 0,
    CONSTRAINT PK_PHIEU_NHAP PRIMARY KEY (MaPhieuNhap),
    CONSTRAINT FK_PHIEU_NHAP_NHA_CUNG_CAP FOREIGN KEY (MaNCC) REFERENCES NHA_CUNG_CAP(MaNCC),
    CONSTRAINT FK_PHIEU_NHAP_NHAN_VIEN FOREIGN KEY (MaNhanVien) REFERENCES NHAN_VIEN(MaNhanVien)
);

-- 10. BẢNG HÓA ĐƠN BÁN HÀNG
CREATE TABLE HOA_DON (
    MaHoaDon VARCHAR(30) NOT NULL,
    MaNhanVien VARCHAR(20) NOT NULL,
    MaKhachHang VARCHAR(20) NOT NULL,
    MaDonThuoc VARCHAR(50) NULL,
    NgayLap DATETIME NOT NULL CONSTRAINT DF_HOA_DON_NgayLap DEFAULT GETDATE(),
    TongTien DECIMAL(18, 2) NOT NULL CONSTRAINT DF_HOA_DON_TongTien DEFAULT 0,
    TrangThaiLT BIT NOT NULL CONSTRAINT DF_HOA_DON_TrangThaiLT DEFAULT 0,
    CONSTRAINT PK_HOA_DON PRIMARY KEY (MaHoaDon),
    CONSTRAINT FK_HOA_DON_NHAN_VIEN FOREIGN KEY (MaNhanVien) REFERENCES NHAN_VIEN(MaNhanVien),
    CONSTRAINT FK_HOA_DON_KHACH_HANG FOREIGN KEY (MaKhachHang) REFERENCES KHACH_HANG(MaKhachHang),
    CONSTRAINT FK_HOA_DON_DON_THUOC FOREIGN KEY (MaDonThuoc) REFERENCES DON_THUOC(MaDonThuoc)
);

-- 11. BẢNG CHI TIẾT PHIẾU NHẬP
CREATE TABLE CHI_TIET_PHIEU_NHAP (
    MaPhieuNhap VARCHAR(30) NOT NULL,
    MaLo VARCHAR(30) NOT NULL,
    SoLuongNhap INT NOT NULL CONSTRAINT CK_CTPN_SoLuongNhap CHECK (SoLuongNhap > 0),
    GiaNhap DECIMAL(18, 2) NOT NULL CONSTRAINT CK_CTPN_GiaNhap CHECK (GiaNhap >= 0),
    CONSTRAINT PK_CHI_TIET_PHIEU_NHAP PRIMARY KEY (MaPhieuNhap, MaLo),
    CONSTRAINT FK_CTPN_PHIEU_NHAP FOREIGN KEY (MaPhieuNhap) REFERENCES PHIEU_NHAP(MaPhieuNhap),
    CONSTRAINT FK_CTPN_LO_THUOC FOREIGN KEY (MaLo) REFERENCES LO_THUOC(MaLo)
);

-- 12. BẢNG CHI TIẾT HÓA ĐƠN
CREATE TABLE CHI_TIET_HOA_DON (
    MaHoaDon VARCHAR(30) NOT NULL,
    MaLo VARCHAR(30) NOT NULL,
    SoLuong INT NOT NULL CONSTRAINT CK_CTHD_SoLuong CHECK (SoLuong > 0),
    DonGia DECIMAL(18, 2) NOT NULL CONSTRAINT CK_CTHD_DonGia CHECK (DonGia >= 0),
    ThanhTien DECIMAL(18, 2) NOT NULL,
    CONSTRAINT PK_CHI_TIET_HOA_DON PRIMARY KEY (MaHoaDon, MaLo),
    CONSTRAINT FK_CTHD_HOA_DON FOREIGN KEY (MaHoaDon) REFERENCES HOA_DON(MaHoaDon),
    CONSTRAINT FK_CTHD_LO_THUOC FOREIGN KEY (MaLo) REFERENCES LO_THUOC(MaLo)
);
GO

-- ============================================================================
-- CÀI ĐẶT 15 RÀNG BUỘC NGHIỆP VỤ (BUSINESS RULES)
-- ============================================================================

-- Rule 1: Expiry >= 6 months
CREATE TRIGGER trg_LoThuoc_CheckMinExpiry
ON LO_THUOC
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM inserted WHERE DATEDIFF(month, GETDATE(), HanSuDung) < 6)
    BEGIN
        RAISERROR (N'Lỗi GPP Rule 1: Thuốc nhập kho phải có hạn sử dụng còn lại tối thiểu 06 tháng!', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

-- Rule 2: Stored Procedure FEFO
CREATE PROCEDURE sp_XuatKho_FEFO
    @MaThuoc VARCHAR(20),
    @SoLuongYeuCau INT,
    @MaHoaDon VARCHAR(30),
    @DonGia DECIMAL(18,2)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @TongTonAvailable INT;
    SELECT @TongTonAvailable = SUM(SoLuongTon) FROM LO_THUOC WHERE MaThuoc = @MaThuoc AND HanSuDung > GETDATE();
    IF (@TongTonAvailable IS NULL OR @TongTonAvailable < @SoLuongYeuCau)
    BEGIN
        RAISERROR(N'Lỗi FEFO Rule 2 & 3: Tồn kho thực tế không đủ!', 16, 1);
        RETURN;
    END
    DECLARE @MaLo VARCHAR(30), @SoLuongTonLo INT, @SoLuongCanXuat INT = @SoLuongYeuCau, @SoLuongXuatLo INT;
    DECLARE cur_FEFO CURSOR LOCAL FAST_FORWARD FOR
        SELECT MaLo, SoLuongTon FROM LO_THUOC WHERE MaThuoc = @MaThuoc AND HanSuDung > GETDATE() AND SoLuongTon > 0 ORDER BY HanSuDung ASC;
    OPEN cur_FEFO;
    FETCH NEXT FROM cur_FEFO INTO @MaLo, @SoLuongTonLo;
    BEGIN TRANSACTION;
    BEGIN TRY
        WHILE @@FETCH_STATUS = 0 AND @SoLuongCanXuat > 0
        BEGIN
            IF (@SoLuongTonLo >= @SoLuongCanXuat) SET @SoLuongXuatLo = @SoLuongCanXuat;
            ELSE SET @SoLuongXuatLo = @SoLuongTonLo;
            UPDATE LO_THUOC SET SoLuongTon = SoLuongTon - @SoLuongXuatLo WHERE MaLo = @MaLo;
            INSERT INTO CHI_TIET_HOA_DON (MaHoaDon, MaLo, SoLuong, DonGia, ThanhTien) VALUES (@MaHoaDon, @MaLo, @SoLuongXuatLo, @DonGia, @SoLuongXuatLo * @DonGia);
            SET @SoLuongCanXuat = @SoLuongCanXuat - @SoLuongXuatLo;
            FETCH NEXT FROM cur_FEFO INTO @MaLo, @SoLuongTonLo;
        END
        CLOSE cur_FEFO; DEALLOCATE cur_FEFO; COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        CLOSE cur_FEFO; DEALLOCATE cur_FEFO; ROLLBACK TRANSACTION; THROW;
    END CATCH
END;
GO

-- Rule 5: Lock Expired Batches
CREATE TRIGGER trg_ChiTietHoaDon_LockExpired
ON CHI_TIET_HOA_DON
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM inserted i JOIN LO_THUOC l ON i.MaLo = l.MaLo WHERE l.HanSuDung <= GETDATE())
    BEGIN
        RAISERROR (N'Lỗi GPP Rule 5: Phong tỏa lô thuốc đã hết hạn!', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

-- Rule 6 & 11: Rx Compliance & Pharmacist Credential Check
CREATE TRIGGER trg_ChiTietHoaDon_RxCompliance
ON CHI_TIET_HOA_DON
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (
        SELECT 1 FROM inserted i
        JOIN LO_THUOC l ON i.MaLo = l.MaLo
        JOIN THUOC t ON l.MaThuoc = t.MaThuoc
        JOIN HOA_DON h ON i.MaHoaDon = h.MaHoaDon
        WHERE t.LoaiThuoc = 1 AND (h.MaDonThuoc IS NULL OR LTRIM(RTRIM(h.MaDonThuoc)) = '')
    )
    BEGIN
        RAISERROR (N'Lỗi QĐ 4041 Rule 6: Thuốc kê đơn bắt buộc phải có Mã đơn thuốc!', 16, 1);
        ROLLBACK TRANSACTION; RETURN;
    END
    IF EXISTS (
        SELECT 1 FROM inserted i
        JOIN LO_THUOC l ON i.MaLo = l.MaLo
        JOIN THUOC t ON l.MaThuoc = t.MaThuoc
        JOIN HOA_DON h ON i.MaHoaDon = h.MaHoaDon
        JOIN NHAN_VIEN nv ON h.MaNhanVien = nv.MaNhanVien
        WHERE t.LoaiThuoc = 1 AND (nv.ChungChi IS NULL OR nv.ChucVu NOT IN (N'Dược sĩ đại học', N'Quản lý chuyên môn'))
    )
    BEGIN
        RAISERROR (N'Lỗi Phân quyền Rule 11: Chỉ Dược sĩ Đại học có CCHN mới được phê duyệt bán thuốc kê đơn!', 16, 1);
        ROLLBACK TRANSACTION; RETURN;
    END
END;
GO

-- Rule 8: Immutable Financial Records
CREATE TRIGGER trg_HoaDon_PreventModification
ON HOA_DON
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM deleted d WHERE d.TrangThaiLT = 1)
    BEGIN
        RAISERROR (N'Lỗi Bảo mật Rule 8: Hóa đơn đã khóa không được phép chỉnh sửa hoặc xóa!', 16, 1);
        ROLLBACK TRANSACTION; RETURN;
    END
END;
GO

-- Rule 10: Sync Invoice Total Amount
CREATE TRIGGER trg_ChiTietHoaDon_SyncTongTien
ON CHI_TIET_HOA_DON
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    SELECT DISTINCT MaHoaDon INTO #AffectedInvoices FROM (SELECT MaHoaDon FROM inserted UNION SELECT MaHoaDon FROM deleted) T;
    UPDATE h SET h.TongTien = ISNULL((SELECT SUM(ct.ThanhTien) FROM CHI_TIET_HOA_DON ct WHERE ct.MaHoaDon = h.MaHoaDon), 0)
    FROM HOA_DON h JOIN #AffectedInvoices a ON h.MaHoaDon = a.MaHoaDon;
    DROP TABLE #AffectedInvoices;
END;
GO

-- Rule 15: Create Password Hash Procedure
CREATE PROCEDURE sp_CreateNhanVien
    @MaNhanVien VARCHAR(20), @HoTen NVARCHAR(100), @ChucVu NVARCHAR(50),
    @ChungChi VARCHAR(50), @Username VARCHAR(50), @PlainPassword VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Salt VARCHAR(36) = NEWID();
    DECLARE @CombinedPassword NVARCHAR(150) = @PlainPassword + @Salt;
    DECLARE @HashedPassword VARCHAR(256);
    SET @HashedPassword = LOWER(CONVERT(VARCHAR(256), HASHBYTES('SHA2_256', @CombinedPassword), 2)) + ':' + @Salt;
    INSERT INTO NHAN_VIEN (MaNhanVien, HoTen, ChucVu, ChungChi, Username, PasswordHash)
    VALUES (@MaNhanVien, @HoTen, @ChucVu, @ChungChi, @Username, @HashedPassword);
END;
GO
