const sql = require('mssql');
const { faker } = require('@faker-js/faker/locale/vi');

const config = {
    user: 'sa', password: 'P@ssw0rd123', server: 'localhost', database: 'QuanLyNhaThuocDB',
    options: { encrypt: true, trustServerCertificate: true }
};

async function seedMasterData() {
    try {
        let pool = await sql.connect(config);
        console.log('✅ Kết nối CSDL thành công!');

        // 1. Tạo Nhà Cung Cấp
        console.log('🔄 Đang tạo Nhà cung cấp...');
        let maNCC = 'NCC' + faker.string.numeric(3);
        await pool.request()
            .input('MaNCC', sql.VarChar(20), maNCC)
            .input('TenNCC', sql.NVarChar(255), faker.company.name())
            .input('GiayPhepKD', sql.VarChar(50), faker.string.alphanumeric(10))
            .query(`IF NOT EXISTS (SELECT 1 FROM NHA_CUNG_CAP WHERE MaNCC = @MaNCC) 
                    INSERT INTO NHA_CUNG_CAP (MaNCC, TenNCC, GiayPhepKD) VALUES (@MaNCC, @TenNCC, @GiayPhepKD)`);

        // 2. Tạo Nhân Viên (Dược sĩ) bằng Stored Procedure (Rule 15)
        console.log('🔄 Đang tạo Dược sĩ (Test băm mật khẩu)...');
        let maNV = 'NV' + faker.string.numeric(3);
        await pool.request()
            .input('MaNhanVien', sql.VarChar(20), maNV)
            .input('HoTen', sql.NVarChar(100), faker.person.fullName())
            .input('ChucVu', sql.NVarChar(50), 'Dược sĩ đại học')
            .input('ChungChi', sql.VarChar(50), 'CCHN-' + faker.string.numeric(5))
            .input('Username', sql.VarChar(50), faker.internet.username().substring(0, 20))
            .input('PlainPassword', sql.VarChar(100), '123456')
            .execute('sp_CreateNhanVien');

        // 3. Lấy 1 Mã Nhóm Thuốc đã có sẵn từ bước trước
        let nhomResult = await pool.request().query('SELECT TOP 1 MaNhom FROM NHOM_THUOC');
        let maNhom = nhomResult.recordset[0].MaNhom;

        // 4. Tạo Thuốc
        console.log('🔄 Đang tạo Danh mục Thuốc...');
        let maThuoc = 'T' + faker.string.numeric(4);
        await pool.request()
            .input('MaThuoc', sql.VarChar(20), maThuoc)
            .input('MaNhom', sql.VarChar(20), maNhom)
            .input('MaNCC', sql.VarChar(20), maNCC)
            .input('TenThuoc', sql.NVarChar(255), faker.science.chemicalElement().name + ' ' + faker.number.int({min: 100, max: 500}) + 'mg')
            .input('SoDangKy', sql.VarChar(50), 'VD-' + faker.string.numeric(4))
            .input('HoatChat', sql.NVarChar(255), 'Paracetamol')
            .input('QuyCach', sql.NVarChar(100), 'Hộp 10 vỉ')
            .input('LoaiThuoc', sql.Bit, 0)
            .query(`INSERT INTO THUOC (MaThuoc, MaNhom, MaNCC, TenThuoc, SoDangKy, HoatChat, QuyCach, LoaiThuoc) 
                    VALUES (@MaThuoc, @MaNhom, @MaNCC, @TenThuoc, @SoDangKy, @HoatChat, @QuyCach, @LoaiThuoc)`);

        console.log('✅ HOÀN TẤT! Dữ liệu lõi đã sẵn sàng.');
        process.exit(0);
    } catch (err) {
        console.error('❌ Lỗi:', err.message);
        process.exit(1);
    }
}

seedMasterData();
