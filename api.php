<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

$databasePath = __DIR__ . '/storage/legend.sqlite';
if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0775, true);
}

$db = new PDO('sqlite:' . $databasePath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE IF NOT EXISTS courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    grade TEXT NOT NULL,
    price INTEGER NOT NULL,
    color TEXT NOT NULL
)');
$db->exec('CREATE TABLE IF NOT EXISTS enrollments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT "pending",
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, course_id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(course_id) REFERENCES courses(id)
)');

$courseCount = (int) $db->query('SELECT COUNT(*) FROM courses')->fetchColumn();
if ($courseCount === 0) {
    $seed = $db->prepare('INSERT INTO courses (slug, title, grade, price, color) VALUES (?, ?, ?, ?, ?)');
    $seed->execute(['blue-lightning', 'كورس البرق الأزرق', 'ثالثة ثانوي', 450, 'blue']);
    $seed->execute(['treasure', 'كورس الكنز', 'ثانية ثانوي', 390, 'gold']);
    $seed->execute(['start', 'كورس البداية', 'أولى ثانوي', 350, 'red']);
}

function respond(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function currentUser(PDO $db): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($route === '/api/courses' && $method === 'GET') {
    respond(['courses' => $db->query('SELECT id, slug, title, grade, price, color FROM courses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($route === '/api/auth/register' && $method === 'POST') {
    $data = body();
    $name = trim((string)($data['name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        respond(['message' => 'اكتب الاسم والبريد الصحيح وكلمة مرور من 6 أحرف على الأقل.'], 422);
    }
    try {
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int)$db->lastInsertId();
        respond(['user' => currentUser($db)]);
    } catch (PDOException) {
        respond(['message' => 'هذا البريد مسجّل بالفعل.'], 409);
    }
}

if ($route === '/api/auth/login' && $method === 'POST') {
    $data = body();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim((string)($data['email'] ?? '')))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify((string)($data['password'] ?? ''), $user['password_hash'])) {
        respond(['message' => 'البريد أو كلمة المرور غير صحيحة.'], 401);
    }
    $_SESSION['user_id'] = (int)$user['id'];
    respond(['user' => currentUser($db)]);
}

if ($route === '/api/auth/logout' && $method === 'POST') {
    session_destroy();
    respond(['ok' => true]);
}

if ($route === '/api/me' && $method === 'GET') {
    $user = currentUser($db);
    if (!$user) respond(['user' => null]);
    $stmt = $db->prepare('SELECT c.title, c.grade, c.price, e.status FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = ? ORDER BY e.created_at DESC');
    $stmt->execute([$user['id']]);
    $user['enrollments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respond(['user' => $user]);
}

if ($route === '/api/enrollments' && $method === 'POST') {
    $user = currentUser($db);
    if (!$user) respond(['message' => 'سجّل دخولك أولاً لإتمام الحجز.'], 401);
    $slug = (string)(body()['course'] ?? '');
    $course = $db->prepare('SELECT id, title FROM courses WHERE slug = ?');
    $course->execute([$slug]);
    $course = $course->fetch(PDO::FETCH_ASSOC);
    if (!$course) respond(['message' => 'الكورس غير موجود.'], 404);
    $enroll = $db->prepare('INSERT OR IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $enroll->execute([$user['id'], $course['id']]);
    respond(['message' => 'تم حجز ' . $course['title'] . ' بنجاح. هنتواصل معاك لإتمام الدفع.']);
}

respond(['message' => 'المسار غير موجود.'], 404);
