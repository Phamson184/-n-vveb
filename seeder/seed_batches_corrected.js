const sql = require('mssql');
const { faker } = require('@faker-js/faker/locale/vi');

const config = {
    user: 'sa', password: 'P@ssw0rd123', server: 'localhost', database: 'QuanLyNhaThuocDB',
    options: { encrypt: true, trustServerCertificate: true }
};

async function seedBatchesCorrected() {
    try {
        let pool = await sql.connect(config);
        console.log('✅ Đã kết nối CSDL!');

        const thuocResult = await pool.request().query('SELECT MaThuoc FROM THUOC');
        const danhSachThuoc = thuocResult.recordset;

        console.log('\n👉 [Test Rule 1]: Thử nhập lô thuốc cận date (Hạn sử dụng còn 5 tháng)...');
        try {
            let dateHanSuDungLoi = new Date();
            dateHanSuDungLoi.setDate(dateHanSuDungLoi.getDate() + 150);

            await pool.request()
                .input('MaLo', sql.VarChar(20), 'LO_TEST_LOI')
                .input('MaThuoc', sql.VarChar(20), danhSachThuoc[0].MaThuoc)
                .input('SoLo', sql.VarChar(50), 'BATCH-ERR')
                .input('SoLuongTon', sql.Int, 100)
                .input('NgaySanXuat', sql.Date, new Date())
                .input('HanSuDung', sql.Date, dateHanSuDungLoi)
                .query(`INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLo, SoLuongTon, NgaySanXuat, HanSuDung)
                        VALUES (@MaLo, @MaThuoc, @SoLo, @SoLuongTon, @NgaySanXuat, @HanSuDung)`);
            
            console.log('❌ THẤT BẠI: CSDL không chặn lỗi này!');
        } catch (err) {
            console.log('✅ CSDL ĐÃ CHẶN THÀNH CÔNG! Lỗi DB trả về: ' + err.message);
        }

        console.log('\n🔄 Đang bơm tồn kho hợp lệ vào bảng LO_THUOC...');
        for (let thuoc of danhSachThuoc) {
            for (let i = 1; i <= 2; i++) {
                let maLo = 'LO' + faker.string.numeric(6);
                let soLo = 'BATCH-' + faker.string.alphanumeric(5).toUpperCase();
                let ngaySX = new Date();
                ngaySX.setMonth(ngaySX.getMonth() - faker.number.int({min: 1, max: 5}));
                
                let hanSD = new Date(ngaySX);
                hanSD.setFullYear(hanSD.getFullYear() + (i === 1 ? 1.5 : 3)); 

                await pool.request()
                    .input('MaLo', sql.VarChar(20), maLo)
                    .input('MaThuoc', sql.VarChar(20), thuoc.MaThuoc)
                    .input('SoLo', sql.VarChar(50), soLo)
                    .input('SoLuongTon', sql.Int, faker.number.int({ min: 50, max: 200 }))
                    .input('NgaySanXuat', sql.Date, ngaySX)
                    .input('HanSuDung', sql.Date, hanSD)
                    .query(`INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLo, SoLuongTon, NgaySanXuat, HanSuDung)
                            VALUES (@MaLo, @MaThuoc, @SoLo, @SoLuongTon, @NgaySanXuat, @HanSuDung)`);
            }
        }

        console.log('\n✅ HOÀN TẤT! Kho đã có hàng để bán.');
        process.exit(0);
    } catch (err) {
        console.error('❌ Lỗi:', err.message);
        process.exit(1);
    }
}

seedBatchesCorrected();
