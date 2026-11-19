USE PharmaPro_v2;
DECLARE @nsql NVARCHAR(MAX) = '';
SELECT @nsql += 'ALTER TABLE Patients DROP CONSTRAINT ' + QUOTENAME(name) + ';' FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID('Patients') AND definition LIKE '%PatientType%';
SELECT @nsql += 'ALTER TABLE Patients DROP CONSTRAINT ' + QUOTENAME(dc.name) + ';' FROM sys.default_constraints dc JOIN sys.columns c ON dc.parent_object_id = c.object_id AND dc.parent_column_id = c.column_id WHERE dc.parent_object_id = OBJECT_ID('Patients') AND c.name = 'PatientType';
EXEC sp_executesql @nsql;
ALTER TABLE Patients ALTER COLUMN PatientType NVARCHAR(30) NOT NULL;
ALTER TABLE Patients ADD CONSTRAINT DF_Patients_PatientType DEFAULT N'Thường' FOR PatientType;
ALTER TABLE Patients ADD CONSTRAINT CK_Patients_PatientType CHECK (PatientType IN (N'Thường', N'VIP', N'Bệnh mạn tính'));