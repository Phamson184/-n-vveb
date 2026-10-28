const sql = require('mssql');

const config = {
    user: 'sa', password: 'P@ssw0rd123', server: 'localhost', database: 'QuanLyNhaThuocDB',
    options: { encrypt: true, trustServerCertificate: true }
};

async function checkSchema() {
    try {
        let pool = await sql.connect(config);
        console.log('🔍 ĐANG QUÉT CẤU TRÚC CƠ SỞ DỮ LIỆU...\n');
        
        let result = await pool.request().query(`
            SELECT 
                TABLE_NAME AS Bang, 
                COLUMN_NAME AS TenCot, 
                DATA_TYPE AS KieuDuLieu
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME IN ('LO_THUOC', 'THUOC', 'CHI_TIET_PHIEU_NHAP')
            ORDER BY TABLE_NAME, ORDINAL_POSITION
        `);
        
        console.table(result.recordset);
        process.exit(0);
    } catch (err) {
        console.error('❌ Lỗi:', err.message);
        process.exit(1);
    }
}

checkSchema();
