USE PharmaPro_v2;
GO

DECLARE @TableName NVARCHAR(128);
DECLARE @IndexName NVARCHAR(128);
DECLARE @Frag FLOAT;
DECLARE @SQL NVARCHAR(MAX);

DECLARE cur CURSOR FOR
SELECT OBJECT_NAME(ips.object_id) AS TableName,
       i.name AS IndexName,
       ips.avg_fragmentation_in_percent AS Frag
FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
WHERE i.name IS NOT NULL
  AND OBJECT_NAME(ips.object_id) IN ('Drugs', 'InventoryBatches', 'Prescriptions', 'PrescriptionItems', 'ControlledDrugLogs')
  AND ips.avg_fragmentation_in_percent >= 5.0;

OPEN cur;
FETCH NEXT FROM cur INTO @TableName, @IndexName, @Frag;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF @Frag >= 30.0
        SET @SQL = N'ALTER INDEX [' + @IndexName + N'] ON [' + @TableName + N'] REBUILD;';
    ELSE
        SET @SQL = N'ALTER INDEX [' + @IndexName + N'] ON [' + @TableName + N'] REORGANIZE;';

    PRINT N'Executing (' + CAST(ROUND(@Frag, 2) AS NVARCHAR(10)) + N'% frag): ' + @SQL;
    EXEC sp_executesql @SQL;

    FETCH NEXT FROM cur INTO @TableName, @IndexName, @Frag;
END

CLOSE cur;
DEALLOCATE cur;
GO