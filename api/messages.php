<?php
require_once 'db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function body() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Найти или создать диалог между соискателем и работодателем.
// Возвращает запись из таблицы conversations.
function findOrCreateConversation($pdo, $seekerId, $employerId, $vacancyId = null) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE seeker_id = ? AND employer_id = ? LIMIT 1");
    $stmt->execute([$seekerId, $employerId]);
    $conv = $stmt->fetch();

    if ($conv) {
        // Обновим vacancy_id, если передали свежий
        if ($vacancyId && (int)$conv['vacancy_id'] !== (int)$vacancyId) {
            $u = $pdo->prepare("UPDATE conversations SET vacancy_id = ? WHERE id = ?");
            $u->execute([$vacancyId, $conv['id']]);
            $conv['vacancy_id'] = $vacancyId;
        }
        return $conv;
    }

    $stmt = $pdo->prepare("INSERT INTO conversations (seeker_id, employer_id, vacancy_id) VALUES (?, ?, ?)");
    $stmt->execute([$seekerId, $employerId, $vacancyId]);
    $id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Проверка, что пользователь — участник диалога. Возвращает 'seeker' / 'employer' или null.
function participantRole($conv, $userId) {
    if ((int)$conv['seeker_id']   === (int)$userId) return 'seeker';
    if ((int)$conv['employer_id'] === (int)$userId) return 'employer';
    return null;
}

// =============================================================
// GET ?action=list&user_id=X  — список диалогов пользователя
// =============================================================
if ($method === 'GET' && $action === 'list') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) respond(['error' => 'user_id required'], 400);

    $sql = "
        SELECT c.id, c.seeker_id, c.employer_id, c.vacancy_id, c.updated_at,
               c.seeker_last_read_at, c.employer_last_read_at,
               us.name AS seeker_name,   us.avatar AS seeker_avatar,
               ue.name AS employer_name, ue.avatar AS employer_avatar,
               v.title AS vacancy_title,
               (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
               (SELECT sender_id FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_sender_id,
               (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_at,
               (
                   SELECT COUNT(*) FROM messages m
                   WHERE m.conversation_id = c.id
                     AND m.sender_id <> ?
                     AND m.created_at > COALESCE(
                         CASE WHEN c.seeker_id = ? THEN c.seeker_last_read_at ELSE c.employer_last_read_at END,
                         '1970-01-01'
                     )
               ) AS unread_count
        FROM conversations c
        JOIN users us ON c.seeker_id   = us.id
        JOIN users ue ON c.employer_id = ue.id
        LEFT JOIN vacancies v ON c.vacancy_id = v.id
        WHERE c.seeker_id = ? OR c.employer_id = ?
        ORDER BY COALESCE(last_message_at, c.created_at) DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    respond($stmt->fetchAll());
}

// =============================================================
// GET ?action=unread&user_id=X — общее число непрочитанных
// =============================================================
if ($method === 'GET' && $action === 'unread') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) respond(['error' => 'user_id required'], 400);

    $sql = "
        SELECT COUNT(*) AS cnt
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.sender_id <> ?
          AND (c.seeker_id = ? OR c.employer_id = ?)
          AND m.created_at > COALESCE(
              CASE WHEN c.seeker_id = ? THEN c.seeker_last_read_at ELSE c.employer_last_read_at END,
              '1970-01-01'
          )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $row = $stmt->fetch();
    respond(['unread' => (int)$row['cnt']]);
}

// =============================================================
// GET ?action=messages&conversation_id=X&user_id=Y
// — сообщения в диалоге, заодно отмечает прочитанным
// =============================================================
if ($method === 'GET' && $action === 'messages') {
    $convId = $_GET['conversation_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    if (!$convId || !$userId) respond(['error' => 'conversation_id and user_id required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();
    if (!$conv) respond(['error' => 'Not found'], 404);

    $role = participantRole($conv, $userId);
    if (!$role) respond(['error' => 'Forbidden'], 403);

    // Отметить прочитанным
    $col = $role === 'seeker' ? 'seeker_last_read_at' : 'employer_last_read_at';
    $u = $pdo->prepare("UPDATE conversations SET $col = NOW() WHERE id = ?");
    $u->execute([$convId]);

    $stmt = $pdo->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.body, m.created_at,
               u.name AS sender_name, u.avatar AS sender_avatar
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$convId]);
    respond($stmt->fetchAll());
}

// =============================================================
// POST ?action=start — найти или создать диалог + (опционально) отправить первое сообщение
// body: { seeker_id, employer_id, vacancy_id?, body? }
// =============================================================
if ($method === 'POST' && $action === 'start') {
    $data = body();
    $seekerId   = $data['seeker_id']   ?? null;
    $employerId = $data['employer_id'] ?? null;
    $vacancyId  = $data['vacancy_id']  ?? null;
    $msg        = trim($data['body'] ?? '');

    if (!$seekerId || !$employerId) respond(['error' => 'seeker_id and employer_id required'], 400);
    if ((int)$seekerId === (int)$employerId) respond(['error' => 'Cannot chat with yourself'], 400);

    $conv = findOrCreateConversation($pdo, $seekerId, $employerId, $vacancyId);

    if ($msg !== '') {
        // Отправитель определяется по тому, кто инициировал (передан в body как seeker_id или employer_id первой ролью).
        // Здесь по соглашению клиента: если приходит из карточки вакансии, инициатор — соискатель.
        // Для корректности используем переданный sender_id, если есть.
        $senderId = $data['sender_id'] ?? $seekerId;
        $role = participantRole($conv, $senderId);
        if (!$role) respond(['error' => 'sender_id not in conversation'], 400);

        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$conv['id'], $senderId, $msg]);
        $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conv['id']]);
    }

    respond(['conversation_id' => (int)$conv['id']]);
}

// =============================================================
// POST ?action=send — отправить сообщение в существующий диалог
// body: { conversation_id, sender_id, body }
// =============================================================
if ($method === 'POST' && $action === 'send') {
    $data = body();
    $convId   = $data['conversation_id'] ?? null;
    $senderId = $data['sender_id']       ?? null;
    $msg      = trim($data['body'] ?? '');

    if (!$convId || !$senderId || $msg === '') respond(['error' => 'conversation_id, sender_id, body required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();
    if (!$conv) respond(['error' => 'Not found'], 404);
    if (!participantRole($conv, $senderId)) respond(['error' => 'Forbidden'], 403);

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
    $stmt->execute([$convId, $senderId, $msg]);
    $msgId = $pdo->lastInsertId();
    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

    $stmt = $pdo->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.body, m.created_at,
               u.name AS sender_name, u.avatar AS sender_avatar
        FROM messages m JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$msgId]);
    respond($stmt->fetch(), 201);
}

respond(['error' => 'Invalid action'], 400);
