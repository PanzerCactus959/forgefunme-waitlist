<?php
/**
 * FORGE Waitlist API - Supabase Version
 */

define('DB_HOST', 'db.qytfioinfkwjeclsddhj.supabase.co');
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'Quocthaidz123@');

// Allowed origins
define('ALLOWED_ORIGIN', '*');

// ─────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); 
    exit;
}

// ── DB connection ──
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";options='--client_encoding=utf8'";
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

// ── Auto-create table if not exists ──
function ensureTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS waitlist (
            id          SERIAL PRIMARY KEY,
            email       VARCHAR(255) UNIQUE NOT NULL,
            ip          VARCHAR(64),
            created_at  TIMESTAMPTZ DEFAULT NOW(),
            invited     BOOLEAN DEFAULT FALSE,
            notes       TEXT
        );
    ");
}

// ── Helpers ──
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            return trim(explode(',', $_SERVER[$k])[0]);
        }
    }
    return 'unknown';
}

// ── Main Logic ──
try {
    ensureTable();
    $db = getDB();

    $method = $_SERVER['REQUEST_METHOD'];

    // GET ?action=count
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'count') {
            $stmt = $db->query('SELECT COUNT(*) AS cnt FROM waitlist');
            $row  = $stmt->fetch();
            json_out(['count' => (int)$row['cnt']]);
        }
        json_out(['error' => 'Unknown action'], 400);
    }

    // POST
    if ($method === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';

        if ($action === 'join') {
            $email = trim(strtolower($body['email'] ?? ''));

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(['success' => false, 'message' => 'Invalid email address.'], 422);
            }

            // Check duplicate
            $check = $db->prepare('SELECT id FROM waitlist WHERE email = ?');
            $check->execute([$email]);
            
            if ($check->fetch()) {
                json_out(['success' => true, 'already' => true,
                          'message' => 'You are already on the waitlist!']);
            }

            // Insert
            $ins = $db->prepare(
                'INSERT INTO waitlist (email, ip) VALUES (?, ?)'
            );
            $ins->execute([$email, getClientIP()]);

            json_out(['success' => true, 'message' => 'Welcome to the waitlist!']);
        }

        json_out(['error' => 'Unknown action'], 400);
    }

    json_out(['error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    error_log('FORGE API error: ' . $e->getMessage());
    json_out(['success' => false, 'message' => 'Server error. Please try again later.'], 500);
}
?>