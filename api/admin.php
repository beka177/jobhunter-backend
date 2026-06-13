<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Автоматическое добавление колонки banned_until, если её нет
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME NULL");
} catch (PDOException $e) {
    // Игнорируем ошибку, если колонка уже существует
}

// В реальном приложении здесь должна быть проверка токена/сессии администратора.
// Для упрощения мы просто проверяем переданный admin_id
$admin_id = $_GET['admin_id'] ?? null;
if (!$admin_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($action === 'create_admin') {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            exit;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, $hashedPassword]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
        }
        exit;
    } elseif ($action === 'ban_user') {
        $id = $data['id'] ?? null;
        $duration = $data['duration'] ?? null; // '1_day', '1_week', '1_month', 'permanent', 'unban'
        
        if (!$id || !$duration) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        
        if ($id == $admin_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot ban yourself']);
            exit;
        }

        $banned_until = null;
        if ($duration === '1_day') $banned_until = date('Y-m-d H:i:s', strtotime('+1 day'));
        elseif ($duration === '1_week') $banned_until = date('Y-m-d H:i:s', strtotime('+1 week'));
        elseif ($duration === '1_month') $banned_until = date('Y-m-d H:i:s', strtotime('+1 month'));
        elseif ($duration === 'permanent') $banned_until = '9999-12-31 23:59:59';
        
        $stmt = $pdo->prepare("UPDATE users SET banned_until = ? WHERE id = ?");
        $stmt->execute([$banned_until, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
} elseif ($method === 'GET') {
    if ($action === 'stats') {
        $stats = [];
        $stats['users']        = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['vacancies']    = (int)$pdo->query("SELECT COUNT(*) FROM vacancies")->fetchColumn();
        $stats['applications'] = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

        // Расширенные данные:
        $roleRows = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll();
        $stats['roles'] = ['seeker' => 0, 'employer' => 0, 'admin' => 0];
        foreach ($roleRows as $r) { $stats['roles'][$r['role']] = (int)$r['c']; }

        $statusRows = $pdo->query("SELECT status, COUNT(*) AS c FROM applications GROUP BY status")->fetchAll();
        $stats['application_statuses'] = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($statusRows as $r) { $stats['application_statuses'][$r['status']] = (int)$r['c']; }

        $stats['banned_users']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE banned_until IS NOT NULL AND banned_until > NOW()")->fetchColumn();
        $stats['favorites']      = (int)$pdo->query("SELECT COUNT(*) FROM favorites")->fetchColumn();

        // Чат-метрики (таблицы могут не существовать — оборачиваем в try)
        try {
            $stats['conversations'] = (int)$pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
            $stats['messages']      = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        } catch (PDOException $e) {
            $stats['conversations'] = 0;
            $stats['messages']      = 0;
        }

        // Регистрации за последние 7 дней
        $stats['new_users_7d']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
        $stats['new_vacancies_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM vacancies WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();

        // Топ-5 городов по числу вакансий
        $stats['top_cities'] = $pdo->query("
            SELECT city, COUNT(*) AS cnt
            FROM vacancies
            WHERE city IS NOT NULL AND city <> ''
            GROUP BY city ORDER BY cnt DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Топ-5 работодателей по числу вакансий
        $stats['top_employers'] = $pdo->query("
            SELECT u.id, u.name, COUNT(v.id) AS vacancies_count
            FROM users u
            JOIN vacancies v ON v.employer_id = u.id
            WHERE u.role = 'employer'
            GROUP BY u.id, u.name ORDER BY vacancies_count DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($stats);
    } elseif ($action === 'conversations') {
        try {
            $stmt = $pdo->query("
                SELECT c.id, c.created_at, c.updated_at,
                       c.seeker_id, c.employer_id, c.vacancy_id,
                       us.name AS seeker_name, ue.name AS employer_name,
                       v.title AS vacancy_title,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS messages_count,
                       (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message
                FROM conversations c
                JOIN users us ON c.seeker_id = us.id
                JOIN users ue ON c.employer_id = ue.id
                LEFT JOIN vacancies v ON c.vacancy_id = v.id
                ORDER BY c.updated_at DESC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'users') {
        $stmt = $pdo->query("SELECT id, name, email, role, created_at, banned_until FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'vacancies') {
        $stmt = $pdo->query("SELECT v.id, v.title, v.employer_id, v.created_at, u.name as employer_name FROM vacancies v JOIN users u ON v.employer_id = u.id ORDER BY v.created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'user_conversations') {
        // Все переписки конкретного пользователя (как соискателя или как работодателя)
        $uid = $_GET['user_id'] ?? null;
        if (!$uid) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user_id']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.created_at, c.updated_at,
                       c.seeker_id, c.employer_id, c.vacancy_id,
                       us.name AS seeker_name, ue.name AS employer_name,
                       v.title AS vacancy_title,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS messages_count,
                       (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message
                FROM conversations c
                JOIN users us ON c.seeker_id = us.id
                JOIN users ue ON c.employer_id = ue.id
                LEFT JOIN vacancies v ON c.vacancy_id = v.id
                WHERE c.seeker_id = ? OR c.employer_id = ?
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$uid, $uid]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'conversation_messages') {
        // Все сообщения одной переписки (для просмотра администратором)
        $cid = $_GET['id'] ?? null;
        if (!$cid) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT m.id, m.conversation_id, m.sender_id, m.body, m.type, m.created_at,
                       u.name AS sender_name
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$cid]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'resumes') {
        // Все резюме пользователей (для просмотра администратором)
        try {
            $stmt = $pdo->query("
                SELECT r.id, r.user_id, r.surname, r.first_name, r.patronymic,
                       r.city, r.phone, r.profession,
                       r.education_level, r.education_institution, r.skills, r.updated_at,
                       u.name AS user_name, u.email AS user_email, u.role AS user_role
                FROM resumes r
                JOIN users u ON r.user_id = u.id
                ORDER BY r.updated_at DESC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode([]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($method === 'DELETE') {
    if ($action === 'user') {
        $id = $_GET['id'] ?? null;
        if ($id == $admin_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete yourself']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'vacancy') {
        $id = $_GET['id'] ?? null;
        $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'conversation') {
        $id = $_GET['id'] ?? null;
        try {
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
?>
