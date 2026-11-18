-- ============================================================================
-- KHỞI TẠO CSDL PHARMAPRO_V2 (CHUẨN GPP & LÂM SÀNG)
-- ============================================================================
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'PharmaPro_v2')
BEGIN
    CREATE DATABASE PharmaPro_v2;
END
GO

USE PharmaPro_v2;
GO

-- 1. HỆ THỐNG PHÂN QUYỀN (RBAC) & NHÂN VIÊN
CREATE TABLE Roles (
    RoleID INT IDENTITY(1,1) PRIMARY KEY,
    RoleCode VARCHAR(30) NOT NULL UNIQUE, -- ADMIN, DOCTOR, PHARMACIST, CASHIER, WAREHOUSE
    RoleName NVARCHAR(100) NOT NULL
);

CREATE TABLE Users (
    UserID INT IDENTITY(1,1) PRIMARY KEY,
    EmployeeCode VARCHAR(20) NOT NULL UNIQUE,
    FullName NVARCHAR(100) NOT NULL,
    RoleID INT NOT NULL FOREIGN KEY REFERENCES Roles(RoleID),
    LicenseNumber VARCHAR(50) NULL, -- Chứng chỉ hành nghề (CCHN)
    Username VARCHAR(50) NOT NULL UNIQUE,
    PasswordHash VARCHAR(256) NOT NULL,
    IsActive BIT NOT NULL DEFAULT 1,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

-- 2. DANH MỤC Y KHOA: KHOA PHÒNG, DẠNG BÀO CHẾ, HOẠT CHẤT (INN) & TƯƠNG TÁC (DDI)
CREATE TABLE Departments (
    DepartmentID INT IDENTITY(1,1) PRIMARY KEY,
    DeptCode VARCHAR(20) NOT NULL UNIQUE, -- HOHAP, TIMMACH, NOITIET, UNGBUOU, NHI
    DeptName NVARCHAR(100) NOT NULL
);

CREATE TABLE DosageForms (
    DosageFormID INT IDENTITY(1,1) PRIMARY KEY,
    FormName NVARCHAR(100) NOT NULL UNIQUE, -- Viên nén, Dung dịch tiêm, Siro, Viên nang...
    RouteOfAdmin NVARCHAR(50) NOT NULL      -- Đường uống, Tiêm tĩnh mạch, Dùng ngoài...
);

CREATE TABLE ActiveIngredients (
    IngredientID INT IDENTITY(1,1) PRIMARY KEY,
    IngredientName NVARCHAR(255) NOT NULL UNIQUE, -- Chuẩn tên quốc tế INN
    DefaultDeptID INT NULL FOREIGN KEY REFERENCES Departments(DepartmentID),
    MaxDailyDoseMg DECIMAL(10,2) NULL
);

CREATE TABLE Interactions (
    InteractionID INT IDENTITY(1,1) PRIMARY KEY,
    IngredientA_ID INT NOT NULL FOREIGN KEY REFERENCES ActiveIngredients(IngredientID),
    IngredientB_ID INT NOT NULL FOREIGN KEY REFERENCES ActiveIngredients(IngredientID),
    Severity VARCHAR(30) NOT NULL CHECK (Severity IN ('Contraindicated', 'Major', 'Moderate')), -- Chống chỉ định / Thận trọng
    ClinicalEffect NVARCHAR(500) NOT NULL,
    ManagementAdvice NVARCHAR(500) NOT NULL,
    CONSTRAINT CK_DistinctIngredients CHECK (IngredientA_ID <> IngredientB_ID)
);
GO

-- 3. DANH MỤC BIỆT DƯỢC (DRUGS) & COVERING INDEX CHO SUBSTITUTION ENGINE
CREATE TABLE Drugs (
    DrugID INT IDENTITY(1,1) PRIMARY KEY,
    DrugCode VARCHAR(30) NOT NULL UNIQUE,
    NationalDrugCode VARCHAR(50) NULL,
    DrugName NVARCHAR(255) NOT NULL,
    IngredientID INT NOT NULL FOREIGN KEY REFERENCES ActiveIngredients(IngredientID),
    DosageFormID INT NOT NULL FOREIGN KEY REFERENCES DosageForms(DosageFormID),
    Concentration NVARCHAR(100) NOT NULL, -- Bắt buộc khớp cứng khi thay thế (VD: 500mg)
    Manufacturer NVARCHAR(150) NOT NULL,
    DepartmentID INT NULL FOREIGN KEY REFERENCES Departments(DepartmentID),
    IsPrescriptionOnly BIT NOT NULL DEFAULT 0, -- OTC (0) vs Rx (1)
    IsControlled BIT NOT NULL DEFAULT 0,       -- Thuốc kiểm soát đặc biệt TT 20/2017 (BIT)
    MinSafeCrCl DECIMAL(5,2) NULL DEFAULT 30.00, -- Ngưỡng CrCl an toàn (ml/phút)
    CurrentPrice DECIMAL(18,2) NOT NULL DEFAULT 0,
    CurrentStock INT NOT NULL DEFAULT 0        -- Đồng bộ trong Transaction cùng InventoryBatches
);

-- Covering Index 4 tầng lọc cho Substitution Engine
CREATE NONCLUSTERED INDEX IX_Drugs_Substitution
ON Drugs (IngredientID, DosageFormID, Concentration)
INCLUDE (DrugCode, DrugName, Manufacturer, CurrentPrice, CurrentStock);
GO

-- 4. LÔ HÀNG (INVENTORY BATCHES) - TEMPORAL TABLE & FILTERED INDEX
CREATE TABLE InventoryBatches (
    BatchID INT IDENTITY(1,1) PRIMARY KEY,
    BatchCode VARCHAR(50) NOT NULL UNIQUE,
    DrugID INT NOT NULL FOREIGN KEY REFERENCES Drugs(DrugID),
    ManufactureDate DATE NOT NULL,
    ExpiryDate DATE NOT NULL,
    CurrentStock INT NOT NULL CHECK (CurrentStock >= 0),
    ImportPrice DECIMAL(18,2) NOT NULL,
    IsLocked BIT NOT NULL DEFAULT 0, -- Khóa lô hết hạn / chờ hủy
    ModifiedByUserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    SysStartTime DATETIME2 GENERATED ALWAYS AS ROW START HIDDEN NOT NULL,
    SysEndTime DATETIME2 GENERATED ALWAYS AS ROW END HIDDEN NOT NULL,
    PERIOD FOR SYSTEM_TIME (SysStartTime, SysEndTime)
)
WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.InventoryBatchesHistory));

-- Filtered Index tối ưu quét FEFO & Cron Job cảnh báo < 90 ngày
CREATE NONCLUSTERED INDEX IX_Expiry
ON InventoryBatches (ExpiryDate)
INCLUDE (DrugID, BatchCode, CurrentStock)
WHERE CurrentStock > 0 AND IsLocked = 0;
GO

-- 5. HỒ SƠ DƯỢC BẠ BỆNH NHÂN 360° & DỊ ỨNG INN (TEMPORAL TABLES)
CREATE TABLE Patients (
    PatientID INT IDENTITY(1,1) PRIMARY KEY,
    PatientCode VARCHAR(20) NOT NULL UNIQUE,
    FullName NVARCHAR(100) NOT NULL,
    Phone VARCHAR(15) NOT NULL UNIQUE,
    DateOfBirth DATE NOT NULL,
    Gender CHAR(1) NOT NULL CHECK (Gender IN ('M', 'F')), -- M: Nam (1.0), F: Nữ (0.85 Cockcroft-Gault)
    Weight_Kg DECIMAL(5,2) NOT NULL,
    SerumCreatinine DECIMAL(5,2) NULL, -- Đơn vị mg/dL
    PatientType VARCHAR(20) NOT NULL DEFAULT N'Thường' CHECK (PatientType IN (N'Thường', N'VIP', N'Bệnh mạn tính')),
    ChronicCondition NVARCHAR(255) NULL, -- Đái tháo đường, Tăng huyết áp, Hen suyễn (Phụ lục VII TT 52)
    ZaloUserID VARCHAR(100) NULL,
    Address NVARCHAR(255) NULL,
    LoyaltyPoints INT NOT NULL DEFAULT 0,
    ModifiedByUserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    SysStartTime DATETIME2 GENERATED ALWAYS AS ROW START HIDDEN NOT NULL,
    SysEndTime DATETIME2 GENERATED ALWAYS AS ROW END HIDDEN NOT NULL,
    PERIOD FOR SYSTEM_TIME (SysStartTime, SysEndTime)
)
WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.PatientsHistory));

CREATE TABLE PatientAllergies (
    AllergyID INT IDENTITY(1,1) PRIMARY KEY,
    PatientID INT NOT NULL FOREIGN KEY REFERENCES Patients(PatientID),
    ActiveIngredientID INT NOT NULL FOREIGN KEY REFERENCES ActiveIngredients(IngredientID),
    Severity VARCHAR(20) NOT NULL CHECK (Severity IN ('Hard Stop', 'Soft Stop')),
    ReactionNote NVARCHAR(255) NULL,
    ModifiedByUserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    SysStartTime DATETIME2 GENERATED ALWAYS AS ROW START HIDDEN NOT NULL,
    SysEndTime DATETIME2 GENERATED ALWAYS AS ROW END HIDDEN NOT NULL,
    PERIOD FOR SYSTEM_TIME (SysStartTime, SysEndTime),
    CONSTRAINT UQ_Patient_Ingredient UNIQUE (PatientID, ActiveIngredientID)
)
WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.PatientAllergiesHistory));
GO

-- 6. ĐƠN THUỐC ĐIỆN TỬ QUỐC GIA & CHI TIẾT XUẤT KHO TÁCH DÒNG FEFO
CREATE TABLE Prescriptions (
    PrescriptionID INT IDENTITY(1,1) PRIMARY KEY,
    NationalCode VARCHAR(14) NOT NULL UNIQUE, -- Chuẩn xxxxxyyyyyyy-z (N, H, C)
    PatientID INT NOT NULL FOREIGN KEY REFERENCES Patients(PatientID),
    DoctorID INT NULL FOREIGN KEY REFERENCES Users(UserID),
    PharmacistID INT NULL FOREIGN KEY REFERENCES Users(UserID),
    Diagnosis NVARCHAR(500) NULL,
    ClinicalInterventionNote NVARCHAR(500) NULL,
    RequiresCommitteeConsult BIT NOT NULL DEFAULT 0, -- Cờ hội chẩn HĐTDT
    IsChronic BIT NOT NULL DEFAULT 0,
    ChronicDurationDays INT NULL CHECK (ChronicDurationDays IN (30, 90)), -- TT 52/2017/TT-BYT
    Status VARCHAR(20) NOT NULL DEFAULT 'Pending' 
        CHECK (Status IN ('Pending', 'Validated', 'Rejected', 'Dispensed', 'Completed')),
    DigitalSignature NVARCHAR(MAX) NULL, -- Chữ ký số USB Token / Remote Signing
    TotalAmount DECIMAL(18,2) NOT NULL DEFAULT 0,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    ModifiedByUserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    SysStartTime DATETIME2 GENERATED ALWAYS AS ROW START HIDDEN NOT NULL,
    SysEndTime DATETIME2 GENERATED ALWAYS AS ROW END HIDDEN NOT NULL,
    PERIOD FOR SYSTEM_TIME (SysStartTime, SysEndTime)
)
WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.PrescriptionsHistory));

-- Chi tiết dòng thuốc bác sĩ kê / trích xuất từ OCR
CREATE TABLE PrescriptionItems (
    ItemID INT IDENTITY(1,1) PRIMARY KEY,
    PrescriptionID INT NOT NULL FOREIGN KEY REFERENCES Prescriptions(PrescriptionID),
    DrugID INT NOT NULL FOREIGN KEY REFERENCES Drugs(DrugID),
    PrescribedQty INT NOT NULL CHECK (PrescribedQty > 0),
    DosageInstruction NVARCHAR(255) NOT NULL, -- VD: Sáng 1 viên, Tối 1 viên sau ăn
    OcrConfidenceScore DECIMAL(4,3) NULL,     -- NULL nếu nhập tay, 0.000 - 1.000 nếu từ FastAPI OCR
    OverrideReason NVARCHAR(500) NULL         -- Bắt buộc ghi lý do nếu vượt cảnh báo Soft Stop / DDI
);

-- Chi tiết dòng xuất kho tách lô FEFO (Line Splitting)
CREATE TABLE DispenseBatchDetails (
    DispenseID INT IDENTITY(1,1) PRIMARY KEY,
    ItemID INT NOT NULL FOREIGN KEY REFERENCES PrescriptionItems(ItemID),
    BatchID INT NOT NULL FOREIGN KEY REFERENCES InventoryBatches(BatchID),
    DispensedQty INT NOT NULL CHECK (DispensedQty > 0),
    UnitPrice DECIMAL(18,2) NOT NULL,
    SubTotal AS (DispensedQty * UnitPrice) PERSISTED,
    DispensedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

-- 7. SỔ THEO DÕI THUỐC KIỂM SOÁT ĐẶC BIỆT (PHỤ LỤC VIII & IX - TT 20/2017) & ZALO ZNS LOGS
CREATE TABLE ControlledDrugLogs (
    LogID INT IDENTITY(1,1) PRIMARY KEY,
    DrugID INT NOT NULL FOREIGN KEY REFERENCES Drugs(DrugID),
    BatchID INT NOT NULL FOREIGN KEY REFERENCES InventoryBatches(BatchID),
    PrescriptionID INT NULL FOREIGN KEY REFERENCES Prescriptions(PrescriptionID),
    TransactionType VARCHAR(20) NOT NULL CHECK (TransactionType IN ('IMPORT', 'DISPENSE', 'AUDIT')),
    QuantityChange INT NOT NULL,
    CabinetPhysicalStock INT NOT NULL, -- Tồn thực tế trong tủ khóa riêng
    SystemStock INT NOT NULL,
    HandoverReportCode VARCHAR(50) NULL, -- Mã biên bản giao nhận Phụ lục IX
    ModifiedByUserID INT NOT NULL FOREIGN KEY REFERENCES Users(UserID),
    SysStartTime DATETIME2 GENERATED ALWAYS AS ROW START HIDDEN NOT NULL,
    SysEndTime DATETIME2 GENERATED ALWAYS AS ROW END HIDDEN NOT NULL,
    PERIOD FOR SYSTEM_TIME (SysStartTime, SysEndTime)
)
WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.ControlledDrugLogsHistory));

CREATE TABLE ZnsLogs (
    ZnsLogID INT IDENTITY(1,1) PRIMARY KEY,
    PatientID INT NOT NULL FOREIGN KEY REFERENCES Patients(PatientID),
    PrescriptionID INT NULL FOREIGN KEY REFERENCES Prescriptions(PrescriptionID),
    JourneyToken VARCHAR(100) NULL,
    TemplateID VARCHAR(50) NOT NULL,
    Status VARCHAR(30) NOT NULL DEFAULT 'SENT', -- SENT, DELIVERED, READ, FAILED
    WebhookPayload NVARCHAR(MAX) NULL,
    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO