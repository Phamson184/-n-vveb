<?php
// Mỗi hàm = nội dung bên trong một app.get(...) của server.js. Câu SQL giữ NGUYÊN (vẫn là T-SQL).
// Muốn thêm API mới: viết thêm 1 hàm ở đây + 1 dòng trong bảng $routes ở index.php.

// Node (mssql) trả cột DATE dạng ISO "2027-04-26T00:00:00.000Z".
// Hàm này tạo đúng định dạng đó để frontend (new Date(...)) chạy y như cũ.
function isoDate($v): ?string {
    if ($v === null) return null;
    $d = $v instanceof DateTimeInterface ? $v : new DateTime((string)$v);
    return $d->format('Y-m-d') . 'T00:00:00.000Z';
}

// GET /api/thuoc/danhsach : danh sách thuốc kèm tổng tồn kho
function getDanhSachThuoc(): array {
    $sql = "
        SELECT
            t.MaThuoc,
            t.SoDangKy,
            t.TenThuoc,
            t.HoatChat,
            t.QuyCach,
            n.TenNhom,
            COALESCE(SUM(l.SoLuongTon), 0) AS TongTonKho
        FROM THUOC t
        LEFT JOIN NHOM_THUOC n ON t.MaNhom = n.MaNhom
        LEFT JOIN LO_THUOC l ON t.MaThuoc = l.MaThuoc
        GROUP BY
            t.MaThuoc, t.SoDangKy, t.TenThuoc, t.HoatChat, t.QuyCach, n.TenNhom
    ";
    $rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['TongTonKho'] = (int)$r['TongTonKho']; // driver PHP có thể trả số dạng chuỗi
    }
    return $rows;
}

// GET /api/kho/danhsach : danh sách chi tiết các lô thuốc, hạn dùng gần nhất lên đầu
function getDanhSachKho(): array {
    $sql = "
        SELECT
            l.MaLo, l.SoLo, l.SoLuongTon, l.HanSuDung, l.NgaySanXuat,
            t.TenThuoc, t.SoDangKy, t.QuyCach
        FROM LO_THUOC l
        JOIN THUOC t ON l.MaThuoc = t.MaThuoc
        ORDER BY l.HanSuDung ASC
    ";
    $rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['SoLuongTon']  = (int)$r['SoLuongTon'];
        $r['HanSuDung']   = isoDate($r['HanSuDung']);
        $r['NgaySanXuat'] = isoDate($r['NgaySanXuat']);
    }
    return $rows;
}
