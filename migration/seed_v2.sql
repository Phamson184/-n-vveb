USE PharmaPro_v2;
GO

-- 1. Nạp 5 Vai trò RBAC chuẩn bệnh viện / nhà thuốc GPP
INSERT INTO Roles (RoleCode, RoleName) VALUES
('ADMIN',      N'Quản trị hệ thống / Trưởng khoa Dược'),
('DOCTOR',     N'Bác sĩ kê đơn'),
('PHARMACIST', N'Dược sĩ lâm sàng thẩm định & bán thuốc'),
('CASHIER',    N'Thu ngân quầy POS'),
('WAREHOUSE',  N'Thủ kho quản lý lô FEFO');
GO

-- 2. Nạp 3 Tài khoản mẫu (Mật khẩu đều là: 123456 -> SHA256: 8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92)
INSERT INTO Users (EmployeeCode, FullName, RoleID, LicenseNumber, Username, PasswordHash) VALUES
('NV001', N'Phạm Trường Sơn', 1, 'CCHN-ADMIN-01', 'admin',    '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92'),
('NV002', N'Trần Thị Thu Hà', 3, 'CCHN-DS-001',   'duocsi01', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92'),
('NV003', N'Lê Minh Hoàng',   4, NULL,            'thungan01','8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92');
GO

-- 3. Nạp Khoa phòng & Dạng bào chế
INSERT INTO Departments (DeptCode, DeptName) VALUES
('HOHAP',   N'Khoa Hô hấp'),
('TIMMACH', N'Khoa Tim mạch'),
('NOITIET', N'Khoa Nội tiết');

INSERT INTO DosageForms (FormName, RouteOfAdmin) VALUES
(N'Viên nén',      N'Đường uống'),
(N'Viên nang cứng', N'Đường uống'),
(N'Dung dịch tiêm', N'Tiêm tĩnh mạch');
GO

-- 4. Nạp Hoạt chất chuẩn INN & Ma trận tương tác DDI chống chỉ định mẫu
INSERT INTO ActiveIngredients (IngredientName, DefaultDeptID, MaxDailyDoseMg) VALUES
(N'Paracetamol',    1, 4000.00),
(N'Amoxicillin',    1, 3000.00),
(N'Clarithromycin', 1, 1000.00),
(N'Simvastatin',    2, 40.00);

-- Cặp tương tác chống chỉ định kinh điển: Clarithromycin + Simvastatin (nguy cơ tiêu cơ vân)
INSERT INTO Interactions (IngredientA_ID, IngredientB_ID, Severity, ClinicalEffect, ManagementAdvice) VALUES
(3, 4, 'Contraindicated', N'Tăng nồng độ Simvastatin huyết tương gây tiêu cơ vân cấp tính.', N'Chống chỉ định phối hợp tuyệt đối. Đổi sang nhóm Statin khác hoặc kháng sinh nhóm khác.');
GO
