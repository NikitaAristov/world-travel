<?php
session_start();
require_once 'db_connect.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['user_id'])) {
    die('Вы не авторизованы. Пожалуйста, <a href="login.php">войдите</a>, чтобы забронировать тур.');
}

// Получаем ID тура из GET-параметров
$tour_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tour_id <= 0) {
    die('Некорректный ID тура.');
}

// Получаем данные о туре
$sql = "SELECT t.*, 
               dc.city_name AS departure_city_name, 
               ac.city_name AS arrival_city_name, 
               cat.category_name 
        FROM tours t
        JOIN cities dc ON t.tour_departure_city_id = dc.city_id
        JOIN cities ac ON t.tour_arrival_city_id = ac.city_id
        JOIN categories cat ON t.tour_category_id = cat.category_id
        WHERE t.tour_id = $tour_id";

$tour = $conn->query($sql)->fetch_assoc();

if (!$tour) {
    die('Тур не найден.');
}

// Если форма отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Количество человек
    $num_people = isset($_POST['num_people']) ? intval($_POST['num_people']) : 0;
    if ($num_people <= 0) {
        $error_message = 'Укажите количество человек.';
    } else {
        $user_id = $_SESSION['user_id'];
        if ($num_people > 1) {
        // Определяем группу бронирования
        
        $group_query = $conn->query("SELECT MAX(booking_group) AS max_group FROM bookings WHERE booking_user_id = $user_id");
        $group_result = $group_query->fetch_assoc();
        $group = $group_result['max_group'] ? $group_result['max_group'] + 1 : 1;
        }
        // Список ошибок
        $errors = [];

        for ($i = 1; $i <= $num_people; $i++) {
            $family = trim($_POST["family_$i"] ?? '');
            $name = trim($_POST["name_$i"] ?? '');
            $lastname = trim($_POST["lastname_$i"] ?? '');
            $seria_doc = trim($_POST["seria_doc_$i"] ?? '');
            $num_doc = trim($_POST["num_doc_$i"] ?? '');
            $lgot = $_POST["lgot_$i"];

            // Валидация данных
            if (empty($family) || empty($name) || empty($lastname)) {
                $errors[] = "Не заполнены ФИО для человека $i.";
            }
            if (strlen($seria_doc) !== 4 || !ctype_digit($seria_doc)) {
                $errors[] = "Некорректная серия паспорта для человека $i. Должно быть 4 цифры.";
            }
            if (strlen($num_doc) !== 6 || !ctype_digit($num_doc)) {
                $errors[] = "Некорректный номер паспорта для человека $i. Должно быть 6 цифр.";
            }
            if (!in_array($lgot, ['Нет льготной категории', 'Пенсионер', 'Инвалид', 'Ребенок'])) {
                $errors[] = "Некорректная льготная категория для человека $i.";
            }

            // Если нет ошибок, добавляем запись в базу данных
            if (empty($errors)) {
                $stmt = $conn->prepare("
                    INSERT INTO bookings (
                        booking_user_id, 
                        booking_tour_id, 
                        booking_date, 
                        booking_family, 
                        booking_name, 
                        booking_lastname, 
                        booking_seria_doc, 
                        booking_num_doc, 
                        booking_lgot, 
                        booking_group, 
                        booking_status
                    ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $status = 'pending'; // Статус по умолчанию
                $stmt->bind_param(
                    'iissssssss',
                    $user_id,
                    $tour_id,
                    $family,
                    $name,
                    $lastname,
                    $seria_doc,
                    $num_doc,
                    $lgot,
                    $group,
                    $status
                );

                if (!$stmt->execute()) {
                    $errors[] = "Ошибка при создании бронирования для человека $i: " . $conn->error;
                }
            }
        }

        if (empty($errors)) {
            $success_message = 'Групповое бронирование успешно создано!';
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Бронирование тура | <?= htmlspecialchars($tour['tour_name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<h2>Бронирование тура: <?= htmlspecialchars($tour['tour_name']) ?></h2>

<?php if (isset($error_message)): ?>
    <p style="color: red;"><?= htmlspecialchars($error_message) ?></p>
<?php elseif (isset($success_message)): ?>
    <p style="color: green;"><?= htmlspecialchars($success_message) ?></p>
<?php endif; ?>

<form method="POST" action="">
    <label for="num_people">Количество человек:</label>
    <input type="number" id="num_people" name="num_people" min="1" required><br>

    <button type="button" onclick="generatePeopleFields()">Добавить людей</button>

    <div id="people-container"></div>

    <button type="submit">Забронировать</button>
</form>

<script>
function generatePeopleFields() {
    const count = document.getElementById('num_people').value;
    const container = document.getElementById('people-container');
    container.innerHTML = '';

    for (let i = 1; i <= count; i++) {
        container.innerHTML += `
            <fieldset style="margin-bottom: 20px;">
                <legend>Человек ${i}</legend>
                <label>Фамилия: <input type="text" name="family_${i}" required></label><br>
                <label>Имя: <input type="text" name="name_${i}" required></label><br>
                <label>Отчество: <input type="text" name="lastname_${i}" required></label><br>
                <label>Серия паспорта (4 цифры): <input type="text" name="seria_doc_${i}" maxlength="4" required></label><br>
                <label>Номер паспорта (6 цифр): <input type="text" name="num_doc_${i}" maxlength="6" required></label><br>
                <label>Льготная категория:
                    <select name="lgot_${i}">
                        <option value="Нет льготной категории">Нет льготной категории</option>
                        <option value="Пенсионер">Пенсионер</option>
                        <option value="Инвалид">Инвалид</option>
                        <option value="Ребенок">Ребенок</option>
                    </select>
                </label>
            </fieldset>
        `;
    }
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>