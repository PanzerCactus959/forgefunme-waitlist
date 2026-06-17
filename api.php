<?php
/**
 * FORGE Waitlist API
 * Endpoints:
 *   POST /api.php  { "action": "join", "email": "user@gmail.com" }
 *   GET  /api.php?action=count
 *
 * Setup:
 *  1. Import schema below into your MySQL database
 *  2. Fill in DB credentials in the CONFIG section
 *  3. Upload index.html + api.php to the same folder on your server
 */

// ─────────────────────────────────────────
//  CONFIG  ← edit these
// ─────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'forge_waitlist');   // your database name
define('DB_USER', 'root');             // your MySQL username
define('DB_PASS', '');                 // your MySQL password

// Allowed origins (add your domain here)
define('ALLOWED_ORIGIN', '*');
// ─────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

// ── DB connection ──
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME);
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
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(255) NOT NULL UNIQUE,
            ip         VARCHAR(64)  DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            invited    TINYINT(1)   NOT NULL DEFAULT 0,
            notes      TEXT         DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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

// ── Route ──
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

            // Validate
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(['success' => false, 'message' => 'Invalid email address.'], 422);
            }

            // Optional: only allow Gmail
            // if (!str_ends_with($email, '@gmail.com')) {
            //     json_out(['success' => false, 'message' => 'Only Gmail addresses accepted.'], 422);
            // }

            // Check duplicate
            $check = $db->prepare('SELECT id FROM waitlist WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                // Already on list – return success silently (don't leak info)
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
    // Never leak DB details in production
    error_log('FORGE API error: ' . $e->getMessage());
    json_out(['success' => false, 'message' => 'Server error. Please try again later.'], 500);
}
