const sql = require('mssql');
const { faker } = require('@faker-js/faker/locale/vi');

// Cấu hình chuẩn cho môi trường Web Development (Dùng tài khoản sa)
const config = {
    user: 'sa',
    password: 'P@ssw0rd123',
    server: 'localhost',
    database: 'QuanLyNhaThuocDB',
    options: {
        encrypt: true, // Bắt buộc cho SQL Server 2022
        trustServerCertificate: true // Bỏ qua lỗi SSL ở local
    }
};

async function seedData() {
    try {
        console.log('🔄 Đang kết nối bằng tài khoản sa qua cổng 1433...');
        let pool = await sql.connect(config);
        console.log('✅ Kết nối thành công mạng TCP/IP!');

        console.log('🔄 Đang sinh tự động 5 Nhóm Thuốc...');
        for (let i = 1; i <= 5; i++) {
            let maNhom = 'NT' + faker.string.numeric(3);
            let tenNhom = faker.commerce.department() + ' Y Tế';
            let moTa = faker.lorem.sentence();
            
            await pool.request()
                .input('MaNhom', sql.VarChar(20), maNhom)
                .input('TenNhom', sql.NVarChar(100), tenNhom)
                .input('MoTa', sql.NVarChar(500), moTa)
                .query(`
                    IF NOT EXISTS (SELECT 1 FROM NHOM_THUOC WHERE MaNhom = @MaNhom)
                    INSERT INTO NHOM_THUOC (MaNhom, TenNhom, MoTa) 
                    VALUES (@MaNhom, @TenNhom, @MoTa)
                `);
        }
        console.log('✅ Đã insert dữ liệu mẫu vào bảng NHOM_THUOC thành công!');
        process.exit(0);
    } catch (err) {
        console.error('❌ Lỗi kết nối hoặc Insert:', err.message);
        process.exit(1);
    }
}

seedData();
