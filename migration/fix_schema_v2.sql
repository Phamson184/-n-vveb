USE PharmaPro_v2;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Expiry' AND object_id = OBJECT_ID('InventoryBatches'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Expiry
    ON InventoryBatches (ExpiryDate)
    INCLUDE (DrugID, BatchCode, CurrentStock)
    WHERE CurrentStock > 0 AND IsLocked = 0;
END
GO

IF OBJECT_ID('DispenseBatchDetails', 'U') IS NULL
BEGIN
    CREATE TABLE DispenseBatchDetails (
        DispenseID INT IDENTITY(1,1) PRIMARY KEY,
        ItemID INT NOT NULL FOREIGN KEY REFERENCES PrescriptionItems(ItemID),
        BatchID INT NOT NULL FOREIGN KEY REFERENCES InventoryBatches(BatchID),
        DispensedQty INT NOT NULL CHECK (DispensedQty > 0),
        UnitPrice DECIMAL(18,2) NOT NULL,
        SubTotal AS (DispensedQty * UnitPrice) PERSISTED,
        DispensedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END
GO
