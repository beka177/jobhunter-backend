<?php
require_once 'db.php';

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Check if we already seeded
    $stmt = $pdo->query("SELECT count(*) as c FROM users WHERE email LIKE 'seed%@example.com'");
    $res = $stmt->fetch();
    if ($res['c'] > 0) {
        echo json_encode(["status" => "error", "message" => "Database already seeded."]);
        exit;
    }

    $basePassword = password_hash('password', PASSWORD_DEFAULT);

    $cities = ['Астана', 'Алматы', 'Шымкент', 'Караганда', 'Актобе'];
    $genders = ['male', 'female'];
    
    // Seed Employers
    $employers = [];
    for ($i = 1; $i <= 5; $i++) {
        $name = "Компания {$i}";
        $email = "seed_emp{$i}@example.com";
        $role = 'employer';
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $basePassword, $role]);
        $employers[] = $pdo->lastInsertId();
    }

    // Seed Vacancies
    $jobTitles = ['Менеджер по продажам', 'Web-программист', 'Дизайнер', 'Бухгалтер', 'Маркетолог'];
    foreach ($employers as $emp_id) {
        for ($j = 0; $j < 3; $j++) {
            $title = $jobTitles[array_rand($jobTitles)] . " (Seed)";
            $desc = "Требуется опытный специалист. Условия отличные, офис в центре.";
            $salary = rand(200, 800) * 1000 . " тг";
            $image = "https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=400&q=80";
            $city = $cities[array_rand($cities)];
            
            $stmt = $pdo->prepare("INSERT INTO vacancies (employer_id, title, description, salary, image, city) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$emp_id, $title, $desc, $salary, $image, $city]);
        }
    }

    // Seed Seekers & Resumes
    $firstNamesM = ['Азамат', 'Данияр', 'Арман', 'Руслан', 'Тимур', 'Ерлан'];
    $firstNamesF = ['Айгерим', 'Динара', 'Мадина', 'Алия', 'Сауле', 'Асель'];
    $surnames = ['Оспанов', 'Жумабаев', 'Иванов', 'Смагулов', 'Абдрахманов', 'Ким'];
    $professions = ['Frontend Developer', 'Менеджер проектов', 'Аналитик данных', 'Специалист по кадрам', 'Инженер', 'Преподаватель'];
    $skills = ['React, JS, CSS', 'Excel, 1C, Аналитика', 'Python, SQL, Pandas', 'Управление людьми, Agile', 'AutoCAD, ЧПУ', 'Английский, IELTS'];
    $eduLevels = ['Среднее', 'Среднее специальное', 'Высшее', 'Магистратура'];

    for ($i = 1; $i <= 15; $i++) {
        $gender = $genders[array_rand($genders)];
        $fName = $gender == 'male' ? $firstNamesM[array_rand($firstNamesM)] : $firstNamesF[array_rand($firstNamesF)];
        $lName = $surnames[array_rand($surnames)];
        $lName = $gender == 'female' ? $lName . 'а' : $lName;
        $name = "$fName $lName";
        $email = "seed_seeker{$i}@example.com";
        $role = 'seeker';
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $basePassword, $role]);
        $user_id = $pdo->lastInsertId();

        // Create Resume
        $prof = $professions[array_rand($professions)];
        $skill = $skills[array_rand($skills)];
        $city = $cities[array_rand($cities)];
        $edu = $eduLevels[array_rand($eduLevels)];
        $year = rand(2010, 2025);
        $phone = "+7 (777) " . rand(100,999) . "-" . rand(10,99) . "-" . rand(10,99);
        $bday = rand(1980, 2000) . "-" . rand(1, 12) . "-" . rand(1, 28);
        
        // ensure resumes table exists logic is likely handled, but we insert
        $stmt = $pdo->prepare("
            INSERT INTO resumes (
                user_id, surname, first_name, gender, city, phone, birthday, 
                citizenship, work_permit, profession, education_level, 
                education_institution, education_year, skills
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $lName, $fName, $gender, $city, $phone, $bday, 
            'Казахстан', 'Казахстан', $prof, $edu, 'НАО Университет', $year, $skill
        ]);
    }

    echo json_encode(["status" => "success", "message" => "Added $i seekers and 15 vacancies to the database."]);
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        echo json_encode(["status" => "error", "message" => "Tables are not fully created yet. Please register an account first so tables build up."]);
    } else {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
