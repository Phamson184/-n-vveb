const sql = require('mssql');
const { faker } = require('@faker-js/faker/locale/vi');

const config = {
    user: 'sa', password: 'P@ssw0rd123', server: 'localhost', database: 'QuanLyNhaThuocDB',
    options: { encrypt: true, trustServerCertificate: true }
};

async function seedBatches() {
    try {
        let pool = await sql.connect(config);
        console.log('✅ Đã kết nối CSDL để nhập Lô Thuốc!');

        const thuocResult = await pool.request().query('SELECT MaThuoc FROM THUOC');
        const danhSachThuoc = thuocResult.recordset;

        if(danhSachThuoc.length === 0) {
            console.log('❌ Không có thuốc nào trong DB! Hãy chạy lại file seed_master.js trước.');
            process.exit(1);
        }

        console.log('\n👉 [Test Rule 1]: Thử nhập lô thuốc cận date (Hạn sử dụng còn 5 tháng)...');
        try {
            let dateHanSuDungLoi = new Date();
            dateHanSuDungLoi.setDate(dateHanSuDungLoi.getDate() + 150); // 5 tháng

            await pool.request()
                .input('MaLo', sql.VarChar(20), 'LO_TEST_LOI')
                .input('MaThuoc', sql.VarChar(20), danhSachThuoc[0].MaThuoc)
                .input('SoLuong', sql.Int, 100)
                .input('NgaySanXuat', sql.Date, new Date())
                .input('HanSuDung', sql.Date, dateHanSuDungLoi)
                .query(`INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLuong, NgaySanXuat, HanSuDung)
                        VALUES (@MaLo, @MaThuoc, @SoLuong, @NgaySanXuat, @HanSuDung)`);
            
            console.log('❌ THẤT BẠI: Đáng lẽ CSDL phải chặn lô thuốc này lại!');
        } catch (err) {
            console.log('✅ CSDL ĐÃ CHẶN THÀNH CÔNG! Lỗi DB trả về: ' + err.message);
        }

        console.log('\n🔄 Đang nhập hàng loạt các lô thuốc hợp lệ vào kho...');
        for (let thuoc of danhSachThuoc) {
            for (let i = 1; i <= 2; i++) {
                let maLo = 'LO' + faker.string.numeric(5);
                let ngaySX = new Date();
                ngaySX.setMonth(ngaySX.getMonth() - faker.number.int({min: 1, max: 5}));
                
                let hanSD = new Date(ngaySX);
                hanSD.setFullYear(hanSD.getFullYear() + (i === 1 ? 1.5 : 3)); 

                await pool.request()
                    .input('MaLo', sql.VarChar(20), maLo)
                    .input('MaThuoc', sql.VarChar(20), thuoc.MaThuoc)
                    .input('SoLuong', sql.Int, faker.number.int({ min: 50, max: 200 }))
                    .input('NgaySanXuat', sql.Date, ngaySX)
                    .input('HanSuDung', sql.Date, hanSD)
                    .query(`INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLuong, NgaySanXuat, HanSuDung)
                            VALUES (@MaLo, @MaThuoc, @SoLuong, @NgaySanXuat, @HanSuDung)`);
            }
        }

        console.log('\n✅ HOÀN TẤT! Đã bơm đủ dữ liệu Kho để test thuật toán POS.');
        process.exit(0);
    } catch (err) {
        console.error('❌ Lỗi hệ thống:', err.message);
        process.exit(1);
    }
}

seedBatches();
