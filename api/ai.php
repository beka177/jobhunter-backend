<?php
// Прокси к Google Gemini API. Скрывает API-ключ от фронтенда.
require_once 'db.php';
require_once '_secrets.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$messages = $data['messages'] ?? [];
$userRole = $data['user_role'] ?? null;
$lang     = $data['lang'] ?? 'ru';
if ($lang !== 'kk') $lang = 'ru';

if (!is_array($messages) || count($messages) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'messages required']);
    exit;
}

// Системная инструкция: контекст про сайт и роль пользователя.
if ($lang === 'kk') {
    $systemPrompt = "Сен — JobSearch сайтының ИИ-көмекшісісің (Қазақстанда жұмыс іздеу). " .
        "**Қазақ тілінде** қысқа әрі нақты жауап бер. " .
        "3–5 сөйлемге сыйғызуға тырыс, ұзын жауаптарды абзацтарға бөл.\n\n" .
        "JobSearch сайты туралы:\n" .
        "• Үш рөл: жұмыс іздеуші, жұмыс беруші, әкімші.\n" .
        "• Жұмыс іздеуші: вакансия іздейді, түйіндеме толтырады, өтінім береді, таңдаулыларға қосады, жұмыс берушілермен жазысады.\n" .
        "• Жұмыс беруші: вакансия жариялайды және өңдейді, үміткерлер каталогын қарайды, өтінімдерді қабылдайды/бас тартады, үміткерлерге жазады.\n" .
        "• Қазақстан қалалары: Астана, Алматы, Шымкент, Қарағанды, Ақтөбе, Тараз, Павлодар, Өскемен, Семей.\n" .
        "• Вакансия бетінде «Өтінім беру», «Жұмыс берушіге жазу», «Таңдаулыларға» батырмалары бар.\n" .
        "• Барлық хабарландырулар оң жоғары бұрышта тост ретінде көрінеді.\n" .
        "• Қараңғы тақырып навбардағы күн/ай белгішесімен ауыстырылады.\n" .
        "• Жұмыс іздеушілер мен жұмыс берушілер арасындағы хабарламалар — навбардағы конверт белгішесі.\n\n" .
        "Сайтқа қатысты емес сұрақтар (мысалы, ауа-райы немесе саясат) — әңгімені сыпайы түрде жұмыс пен сайтқа қайтар.\n" .
        "Жоқ функцияларды ойдан шығарма (мысалы, видеоқоңыраулар, сайт ішіндегі тест тапсырмалары — олар жоқ).";

    if ($userRole) {
        $roleHuman = ['seeker' => 'жұмыс іздеуші', 'employer' => 'жұмыс беруші', 'admin' => 'әкімші'][$userRole] ?? null;
        if ($roleHuman) {
            $systemPrompt .= "\n\nАғымдағы пайдаланушы $roleHuman ретінде кірген — соны ескер және осы рөлге сай кеңес бер.";
        }
    }
} else {
    $systemPrompt = "Ты — ИИ-помощник сайта JobSearch (поиск работы в Казахстане). " .
        "Отвечай **на русском языке**, кратко и по делу. " .
        "Старайся помещаться в 3–5 предложений, разбивай длинные ответы на абзацы.\n\n" .
        "О сайте JobSearch:\n" .
        "• Три роли: соискатель, работодатель, администратор.\n" .
        "• Соискатель: ищет вакансии, заполняет резюме, откликается, сохраняет в избранное, переписывается с работодателями.\n" .
        "• Работодатель: публикует и редактирует вакансии, просматривает каталог соискателей, получает и обрабатывает отклики (Принять / Отклонить), пишет кандидатам.\n" .
        "• Города Казахстана: Астана, Алматы, Шымкент, Караганда, Актобе, Тараз, Павлодар, Оскемен, Семей.\n" .
        "• На странице вакансии есть кнопки «Откликнуться», «Написать работодателю», «В избранное».\n" .
        "• Все уведомления приходят в виде тостов в правом верхнем углу.\n" .
        "• Тёмная тема переключается иконкой солнца/луны в навбаре.\n" .
        "• Сообщения между соискателями и работодателями — иконка-конверт в навбаре.\n\n" .
        "Если спрашивают НЕ про сайт (например, про погоду или политику) — вежливо верни тему к работе и сайту.\n" .
        "Не выдумывай функции, которых нет (например, видеозвонки, тестовые задания внутри сайта — этого нет).";

    if ($userRole) {
        $roleHuman = ['seeker' => 'соискатель', 'employer' => 'работодатель', 'admin' => 'администратор'][$userRole] ?? null;
        if ($roleHuman) {
            $systemPrompt .= "\n\nТекущий пользователь авторизован как $roleHuman — учитывай это и давай советы под эту роль.";
        }
    }
}

// Преобразуем историю в формат Gemini.
$contents = [];
foreach ($messages as $m) {
    $role = ($m['role'] ?? 'user') === 'model' ? 'model' : 'user';
    $text = trim($m['text'] ?? '');
    if ($text === '') continue;
    $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
}

if (count($contents) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'no valid messages']);
    exit;
}

$payload = [
    'contents' => $contents,
    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 800,
    ],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . GEMINI_API_KEY;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'curl: ' . $err]);
    exit;
}

if ($code !== 200) {
    http_response_code($code);
    echo $response;
    exit;
}

$decoded = json_decode($response, true);
$reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

if ($reply === '') {
    http_response_code(502);
    echo json_encode(['error' => 'empty response', 'raw' => $decoded]);
    exit;
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
