<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

try {
    $email = trim(strtolower($_POST['email'] ?? json_decode(file_get_contents('php://input'), true)['email'] ?? ''));

    if (empty($email)) {
        throw new Exception("Không nhận được email");
    }

    // Kết nối Supabase
    $pdo = new PDO(
        "pgsql:host=db.qytfioinfkwjeclsddhj.supabase.co;port=5432;dbname=postgres",
        "postgres",
        "Quocthaidz123@",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Test query đơn giản
    $stmt = $pdo->prepare("INSERT INTO waitlist (email, ip) VALUES (?, ?) RETURNING id");
    $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    echo json_encode([
        "success" => true,
        "message" => "Đăng ký thành công!"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Lỗi: " . $e->getMessage()
    ]);
}
?>