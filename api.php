<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = new PDO(
        "pgsql:host=db.qytfioinfkwjeclsddhj.supabase.co;port=6543;dbname=postgres;sslmode=require",
        "postgres",
        "Quocthaidz123@",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]
    );

    // Counter
    if (isset($_GET['action']) && $_GET['action'] === 'count') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM waitlist");
        echo json_encode(["count" => (int)$stmt->fetchColumn()]);
        exit;
    }

    // Join Waitlist
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim(strtolower($input['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["success" => false, "message" => "Email không hợp lệ"]);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM waitlist WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            echo json_encode(["success" => true, "already" => true]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO waitlist (email, ip, invited) VALUES (?, ?, false)");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

        echo json_encode(["success" => true, "message" => "Đăng ký thành công!"]);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Yêu cầu không hợp lệ"]);

} catch (PDOException $e) {
    error_log("Supabase Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Lỗi kết nối database. Vui lòng thử lại."
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Lỗi hệ thống"]);
}
?>