<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Lấy email từ JSON (Frontend gửi JSON)
$data = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($data['email'] ?? ''));

if (empty($email)) {
    echo json_encode(["success" => false, "message" => "Email không được để trống"]);
    exit;
}

try {
    // ==================== KẾT NỐI SUPABASE ====================
    $pdo = new PDO(
        "pgsql:host=db.qytfioinfkwjeclsddhj.supabase.co;port=5432;dbname=postgres",
        "postgres",
        "Quocthaidz123@",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]
    );

    // Kiểm tra email đã tồn tại chưa
    $check = $pdo->prepare("SELECT id FROM waitlist WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->rowCount() > 0) {
        echo json_encode(["success" => true, "already" => true, "message" => "Email đã đăng ký"]);
        exit;
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO waitlist (email, ip) VALUES (?, ?)");
    $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    echo json_encode([
        "success" => true,
        "message" => "Đăng ký thành công!"
    ]);

} catch (PDOException $e) {
    error_log("Supabase Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Lỗi kết nối database. Vui lòng thử lại."
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi: " . $e->getMessage()
    ]);
}
?>