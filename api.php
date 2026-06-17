<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Lấy dữ liệu từ frontend (gửi JSON)
$data = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($data['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Email không hợp lệ"]);
    exit;
}

try {
    $pdo = new PDO(
        "pgsql:host=db.qytfioinfkwjeclsddhj.supabase.co;port=5432;dbname=postgres",
        "postgres",
        "Quocthaidz123@",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8
        ]
    );

    // Kiểm tra email đã tồn tại
    $check = $pdo->prepare("SELECT id FROM waitlist WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        echo json_encode(["success" => true, "already" => true, "message" => "Email đã đăng ký"]);
        exit;
    }

    // Insert dữ liệu
    $stmt = $pdo->prepare("
        INSERT INTO waitlist (email, ip, invited) 
        VALUES (?, ?, false)
    ");
    
    $stmt->execute([
        $email, 
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Đăng ký thành công! 🎉"
    ]);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Lỗi kết nối. Vui lòng thử lại sau."
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Có lỗi xảy ra."
    ]);
}
?>