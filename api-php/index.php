<?php
// 1. XỬ LÝ CORS HEADERS (Cho phép kết nối từ Frontend 8082)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

// 2. NẠP KẾT NỐI CSDL
require_once __DIR__ . '/config.php';

// 3. XỬ LÝ ROUTING
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim(rtrim($path, '.'), '/');

if (PHP_SAPI !== 'cli-server') {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
}

$key = $_SERVER['REQUEST_METHOD'] . ' ' . $path;

// 4. MẢNG CÁC ROUTE API HỆ THỐNG
$routes = [
    // --- NHÁNH 1: QUẢN LÝ THUỐC (DANH MỤC) ---
    'GET /api/thuoc/danhsach' => function() use ($conn) {
        $sql = "SELECT T.*, ISNULL(SUM(L.SoLuongTon), 0) AS TongTonKho 
                FROM THUOC T 
                LEFT JOIN LO_THUOC L ON T.MaThuoc = L.MaThuoc 
                GROUP BY T.MaThuoc, T.MaNhom, T.MaNCC, T.TenThuoc, T.SoDangKy, T.HoatChat, T.QuyCach, T.LoaiThuoc, T.GiaBan, T.MaThuocQuocGia";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) return ["error" => sqlsrv_errors()];
        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $data[] = $row; }
        return $data;
    },

    'GET /api/thuoc/chitiet' => function() use ($conn) {
        $maThuoc = $_GET['id'] ?? '';
        $sql = "SELECT * FROM THUOC WHERE MaThuoc = ?";
        $stmt = sqlsrv_query($conn, $sql, [$maThuoc]);
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: ['error' => 'Không tìm thấy thuốc'];
    },

    'POST /api/thuoc/them' => function($body) use ($conn) {
        $sql = "INSERT INTO THUOC (MaThuoc, MaNhom, MaNCC, TenThuoc, SoDangKy, HoatChat, QuyCach, LoaiThuoc, GiaBan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $body['MaThuoc'], $body['MaNhom'], $body['MaNCC'], 
            $body['TenThuoc'], $body['SoDangKy'], $body['HoatChat'], 
            $body['QuyCach'], $body['LoaiThuoc'], $body['GiaBan']
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) return ["error" => sqlsrv_errors()];
        return ["success" => true, "message" => "Thêm thuốc thành công"];
    },

    'PUT /api/thuoc/sua' => function($body) use ($conn) {
        $sql = "UPDATE THUOC SET TenThuoc = ?, MaNhom = ?, MaNCC = ?, SoDangKy = ?, HoatChat = ?, QuyCach = ?, LoaiThuoc = ?, GiaBan = ? WHERE MaThuoc = ?";
        $params = [
            $body['TenThuoc'], $body['MaNhom'], $body['MaNCC'], 
            $body['SoDangKy'], $body['HoatChat'], $body['QuyCach'], 
            $body['LoaiThuoc'], $body['GiaBan'], 
            $body['MaThuoc']
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) return ["error" => sqlsrv_errors()];
        return ["success" => true, "message" => "Cập nhật thuốc thành công"];
    },

    'DELETE /api/thuoc/xoa' => function($body) use ($conn) {
        $sql = "DELETE FROM THUOC WHERE MaThuoc = ?";
        $stmt = sqlsrv_query($conn, $sql, [$body['MaThuoc']]);
        if ($stmt === false) return ["error" => sqlsrv_errors()];
        return ["success" => true, "message" => "Xóa thành công"];
    },

    // --- NHÁNH 2: KHO & LÔ THUỐC ---
    'POST /api/kho/nhaplo' => function($body) use ($conn) {
        $maLo        = $body['MaLo'] ?? ('LO' . time());
        $maThuoc     = $body['MaThuoc'] ?? '';
        $soLo        = $body['SoLo'] ?? ('SL-' . time());
        $ngaySanXuat = $body['NgaySanXuat'] ?? null;
        $hanSuDung   = $body['HanSuDung'] ?? null;
        $soLuongTon  = (int)($body['SoLuongTon'] ?? 0);

        if (empty($maThuoc) || $soLuongTon <= 0 || empty($hanSuDung) || empty($ngaySanXuat)) {
            return ["error" => "Thông tin nhập kho không hợp lệ."];
        }

        $sql = "INSERT INTO LO_THUOC (MaLo, MaThuoc, SoLo, NgaySanXuat, HanSuDung, SoLuongTon) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$maLo, $maThuoc, $soLo, $ngaySanXuat, $hanSuDung, $soLuongTon];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) return ["error" => "Không thể thêm lô thuốc mới", "details" => sqlsrv_errors()];

        return ["success" => true, "message" => "Nhập lô thuốc thành công!", "MaLo" => $maLo, "SoLuongTon" => $soLuongTon];
    },

    'GET /api/kho/danhsach' => function() use ($conn) {
        $sql = "SELECT L.MaLo, L.MaThuoc, L.SoLo, L.NgaySanXuat, L.HanSuDung, L.SoLuongTon,
                       T.TenThuoc, T.SoDangKy, T.QuyCach
                FROM LO_THUOC L
                INNER JOIN THUOC T ON L.MaThuoc = T.MaThuoc
                ORDER BY L.HanSuDung ASC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) return ["error" => sqlsrv_errors()];

        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['NgaySanXuat'] instanceof DateTime) $row['NgaySanXuat'] = $row['NgaySanXuat']->format('Y-m-d');
            if ($row['HanSuDung'] instanceof DateTime) $row['HanSuDung'] = $row['HanSuDung']->format('Y-m-d');
            $data[] = $row;
        }
        return $data;
    },

    // --- NHÁNH 3: BÁN HÀNG POS & FEFO TRANSACTION ---
    'POST /api/hoadon/tao' => function($body) use ($conn) {
        $maHoaDon   = $body['MaHoaDon'] ?? ('HD' . time());
        $maNhanVien = $body['MaNhanVien'] ?? 'NV001';
        $maKhachHang= $body['MaKhachHang'] ?? 'KH001';
        $maDonThuoc = $body['MaDonThuoc'] ?? null;
        $gioHang    = $body['GioHang'] ?? [];

        if (empty($maNhanVien) || empty($gioHang)) {
            return ["error" => "Thiếu thông tin Mã nhân viên hoặc Giỏ hàng rỗng."];
        }

        if (sqlsrv_begin_transaction($conn) === false) {
            return ["error" => "Không thể khởi tạo Transaction CSDL", "details" => sqlsrv_errors()];
        }

        try {
            $tongTienHoaDon = 0;

            $sqlHD = "INSERT INTO HOA_DON (MaHoaDon, MaNhanVien, MaKhachHang, MaDonThuoc, NgayLap, TongTien, TrangThaiLT) 
                      VALUES (?, ?, ?, ?, GETDATE(), 0, 0)";
            $stmtHD = sqlsrv_query($conn, $sqlHD, [$maHoaDon, $maNhanVien, $maKhachHang, $maDonThuoc]);
            if ($stmtHD === false) throw new Exception("Lỗi tạo HOA_DON: " . json_encode(sqlsrv_errors()));

            foreach ($gioHang as $item) {
                $maThuoc      = $item['MaThuoc'];
                $soLuongCanto = (int)$item['SoLuong'];
                $donGia       = (float)$item['DonGia'];

                // THUẬT TOÁN FEFO
                $sqlFEFO = "SELECT MaLo, SoLuongTon, HanSuDung 
                            FROM LO_THUOC 
                            WHERE MaThuoc = ? AND SoLuongTon > 0 AND HanSuDung >= GETDATE() 
                            ORDER BY HanSuDung ASC";
                $stmtFEFO = sqlsrv_query($conn, $sqlFEFO, [$maThuoc]);

                $soLuongDaTru = 0;
                while ($lo = sqlsrv_fetch_array($stmtFEFO, SQLSRV_FETCH_ASSOC)) {
                    if ($soLuongDaTru >= $soLuongCanto) break;

                    $soLuongCanLay = $soLuongCanto - $soLuongDaTru;
                    $soLuongLayThucTe = min($lo['SoLuongTon'], $soLuongCanLay);
                    $thanhTienItem = $soLuongLayThucTe * $donGia;
                    $tongTienHoaDon += $thanhTienItem;

                    $sqlCTHD = "INSERT INTO CHI_TIET_HOA_DON (MaHoaDon, MaLo, SoLuong, DonGia, ThanhTien) 
                                VALUES (?, ?, ?, ?, ?)";
                    $stmtCTHD = sqlsrv_query($conn, $sqlCTHD, [$maHoaDon, $lo['MaLo'], $soLuongLayThucTe, $donGia, $thanhTienItem]);
                    if ($stmtCTHD === false) throw new Exception("Lỗi chèn CTHD lô [" . $lo['MaLo'] . "]");

                    $sqlTruKho = "UPDATE LO_THUOC SET SoLuongTon = SoLuongTon - ? WHERE MaLo = ?";
                    $stmtTruKho = sqlsrv_query($conn, $sqlTruKho, [$soLuongLayThucTe, $lo['MaLo']]);
                    if ($stmtTruKho === false) throw new Exception("Lỗi trừ tồn kho lô [" . $lo['MaLo'] . "]");

                    $soLuongDaTru += $soLuongLayThucTe;
                }

                if ($soLuongDaTru < $soLuongCanto) {
                    throw new Exception("Thuốc [$maThuoc] không đủ tổng tồn kho trong các lô còn hạn.");
                }
            }

            $sqlUpdateHD = "UPDATE HOA_DON SET TongTien = ? WHERE MaHoaDon = ?";
            sqlsrv_query($conn, $sqlUpdateHD, [$tongTienHoaDon, $maHoaDon]);

            sqlsrv_commit($conn);
            return [
                "success" => true, 
                "message" => "Tạo hóa đơn FEFO thành công!", 
                "MaHoaDon" => $maHoaDon,
                "TongTien" => $tongTienHoaDon
            ];

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            return ["error" => $e->getMessage()];
        }
    },

    'GET /api/hoadon/danhsach' => function() use ($conn) {
        $tuNgay  = $_GET['tuNgay'] ?? date('Y-m-01');
        $denNgay = $_GET['denNgay'] ?? date('Y-m-d');

        $sql = "SELECT HD.MaHoaDon, HD.NgayLap, HD.TongTien, HD.MaNhanVien,
                       NV.TenNhanVien, KH.TenKhachHang
                FROM HOA_DON HD
                LEFT JOIN NHAN_VIEN NV ON HD.MaNhanVien = NV.MaNhanVien
                LEFT JOIN KHACH_HANG KH ON HD.MaKhachHang = KH.MaKhachHang
                WHERE CAST(HD.NgayLap AS DATE) BETWEEN ? AND ?
                ORDER BY HD.NgayLap DESC";

        $stmt = sqlsrv_query($conn, $sql, [$tuNgay, $denNgay]);
        if ($stmt === false) return ["error" => sqlsrv_errors()];

        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['NgayLap'] instanceof DateTime) $row['NgayLap'] = $row['NgayLap']->format('Y-m-d H:i:s');
            $data[] = $row;
        }
        return $data;
    },

    'GET /api/hoadon/chitiet' => function() use ($conn) {
        $maHD = $_GET['id'] ?? '';
        if (empty($maHD)) return ["error" => "Thiếu MaHoaDon"];

        $sql = "SELECT CT.MaLo, CT.SoLuong, CT.DonGia, CT.ThanhTien,
                       T.TenThuoc, T.QuyCach, L.SoLo, L.HanSuDung
                FROM CHI_TIET_HOA_DON CT
                INNER JOIN LO_THUOC L ON CT.MaLo = L.MaLo
                INNER JOIN THUOC T ON L.MaThuoc = T.MaThuoc
                WHERE CT.MaHoaDon = ?";

        $stmt = sqlsrv_query($conn, $sql, [$maHD]);
        if ($stmt === false) return ["error" => sqlsrv_errors()];

        $details = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['HanSuDung'] instanceof DateTime) $row['HanSuDung'] = $row['HanSuDung']->format('Y-m-d');
            $details[] = $row;
        }
        return $details;
    }
]; // <-- DẤU ĐÓNG MẢNG ĐÃ ĐƯỢC CHUYỂN VỀ ĐÚNG CUỐI CÙNG

// 5. THỰC THI PHẢN HỒI API
if (!isset($routes[$key])) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy API: ' . $key], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    echo json_encode($routes[$key]($body), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>