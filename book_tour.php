<?php
session_start();
require_once 'db_connect.php';
require_once 'vendor/autoload.php'; // Подключение автозагрузчика Composer

// Импортируем необходимые классы
use YooKassa\Client;
use YooKassa\Request\Payments\CreatePaymentRequest;

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
               cat.category_name, 
               t.tour_price
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
        $total_amount = 0; // Для хранения общей суммы бронирования

        // Расчет общей суммы бронирования
        for ($i = 1; $i <= $num_people; $i++) {
            $lgot = $_POST["lgot_$i"];

            // Расчет скидки для текущего человека
            $discount = 0;
            switch ($lgot) {
                case 'Пенсионер':
                    $discount = 0.10;
                    break;
                case 'Инвалид':
                    $discount = 0.20;
                    break;
                case 'Ребенок':
                    $discount = 0.15;
                    break;
            }
            $person_amount = $tour['tour_price'] * (1 - $discount);
            $total_amount += $person_amount;
        }

        // Создаем платеж через ЮКасса
        try {
            // Создаем объект CreatePaymentRequest
            $paymentRequest = new \YooKassa\Request\Payments\CreatePaymentRequest();
            $paymentRequest->setAmount([
                'value' => number_format($total_amount, 2, '.', ''),
                'currency' => 'RUB'
            ]);
            $paymentRequest->setConfirmation([
                'type' => 'redirect',
                'return_url' => 'https://cool-suits-rescue.loca.lt/account.php'
            ]);
            $paymentRequest->setDescription("Бронирование тура №$tour_id");
            $paymentRequest->setCapture(true);

            // Создаем платеж через ЮКасса
            $shopId = '1076262';
            $secretKey = 'test_FtTauE3OdWtEgj_c4kSr1--_jcPVcfg6htDvpyTInQw';
            $client = new Client();
            $client->setAuth($shopId, $secretKey); // Инициализация клиента ЮКасса
            $response = $client->createPayment($paymentRequest);

            // Получаем статус платежа из ответа ЮКассы
            $payment_status = $response->getStatus();

            $stmt = $conn->prepare("
                INSERT INTO payments (
                    yookassa_payment_id,
                    payment_amount,
                    payment_date,
                    payment_status
                ) VALUES (?, ?, NOW(), ?)
            ");
            

            if (!$stmt) {
                die('Ошибка подготовки запроса: ' . $conn->error);
            }
            $stmt->bind_param('sds', $response->getId(), $total_amount, $payment_status);
            $stmt->execute();

            // Получаем ID платежа
            $payment_id = $stmt->insert_id;
        } catch (\Exception $e) {
            die('Ошибка при создании платежа: ' . $e->getMessage());
        }

            $conn->begin_transaction(); // НАЧАЛИ ТРАНЗАКЦИЮ

            $errors = [];
            $passengers = [];

            for ($i = 1; $i <= $num_people; $i++) {
                $family = trim($_POST["family_$i"] ?? '');
                $name = trim($_POST["name_$i"] ?? '');
                $lastname = trim($_POST["lastname_$i"] ?? '');
                $seria_doc = trim($_POST["seria_doc_$i"] ?? '');
                $num_doc = trim($_POST["num_doc_$i"] ?? '');
                $lgot = $_POST["lgot_$i"];

                // Проверки
                if (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $family)) {
                    $errors[] = "Ошибка: Фамилия для человека $i должна начинаться с заглавной буквы и содержать только русские буквы.";
                }
                if (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $name)) {
                    $errors[] = "Ошибка: Имя для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
                }
                if ($lastname && !preg_match('/^[А-ЯЁ][а-яё]*$/u', $lastname)) {
                    $errors[] = "Ошибка: Отчество для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
                }
                if (empty($family) || empty($name)) {
                    $errors[] = "Не заполнены фамилия и/или имя для человека $i.";
                }
                if (strlen($seria_doc) !== 4 || !ctype_digit($seria_doc)) {
                    $errors[] = "Некорректная серия паспорта для человека $i.";
                }
                if (strlen($num_doc) !== 6 || !ctype_digit($num_doc)) {
                    $errors[] = "Некорректный номер паспорта для человека $i.";
                }
                if (!in_array($lgot, ['Нет льготной категории', 'Пенсионер', 'Инвалид', 'Ребенок'])) {
                    $errors[] = "Некорректная льготная категория для человека $i.";
                }

                $passengers[] = [
                    'family' => $family,
                    'name' => $name,
                    'lastname' => $lastname,
                    'seria_doc' => $seria_doc,
                    'num_doc' => $num_doc,
                ];
            }

            // Проверяем на одинаковых пассажиров ПЕРЕД вставкой в БД
            foreach ($passengers as $index => $passenger) {
                foreach ($passengers as $innerIndex => $innerPassenger) {
                    if ($index < $innerIndex) { // Только вперед
                        if (
                            $passenger['family'] === $innerPassenger['family'] &&
                            $passenger['name'] === $innerPassenger['name'] &&
                            $passenger['lastname'] === $innerPassenger['lastname'] &&
                            $passenger['seria_doc'] === $innerPassenger['seria_doc'] &&
                            $passenger['num_doc'] === $innerPassenger['num_doc']
                        ) {
                            $errors[] = "Ошибка: Пассажир №" . ($index + 1) . " совпадает с пассажиром №" . ($innerIndex + 1) . ".";
                        }
                    }
                }
            }

            if (!empty($errors)) {
                $conn->rollback(); // ОТКАТ транзакции, если ошибки
                $error_message = implode('<br>', $errors);
            } else {
                // Теперь можно вставлять
                foreach ($passengers as $i => $passenger) {
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
                            booking_status,
                            booking_payment_id
                        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $status = 'pending';
                    if ($payment_status === 'succeeded') {
                        $status = 'confirmed';
                    } elseif ($payment_status === 'canceled' || $payment_status === 'failed') {
                        $status = 'invalid';
                    }

                    $stmt->bind_param(
                        'iisssssssss',
                        $user_id,
                        $tour_id,
                        $passenger['family'],
                        $passenger['name'],
                        $passenger['lastname'],
                        $passenger['seria_doc'],
                        $passenger['num_doc'],
                        $_POST["lgot_" . ($i+1)],
                        $group,
                        $status,
                        $response->getId()
                    );
                    if (!$stmt->execute()) {
                        $errors[] = "Ошибка при создании бронирования для пассажира " . ($i+1) . ": " . $conn->error;
                        break;
                    }
                }

                if (!empty($errors)) {
                    $conn->rollback(); // Откатываем все добавления, если ошибка при вставке
                    $error_message = implode('<br>', $errors);
                } else {
                    $conn->commit(); // Все успешно
                    header("Location: " . $response->getConfirmation()->getConfirmationUrl());
                    exit;
                }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<section class="main">
    <h2>Бронирование тура: <?= htmlspecialchars($tour['tour_name']) ?></h2>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?= $error_message ?></p>
    <?php endif; ?>

    <form method="post" id="bookingForm">
        <div id="passengersContainer">
            <!-- Сюда будут добавляться пассажиры -->
        </div>

        <input type="hidden" name="num_people" id="num_people" value="1">

        <button type="button" onclick="addPassenger()">Добавить пассажира</button>
        <button type="submit">Перейти к оплате</button>
    </form>

    <script>
        // Счётчик пассажиров
        let passengerCount = 0;

        // Функция добавления пассажира
        function addPassenger(filledData = {}) {
            passengerCount++;

            const container = document.getElementById('passengersContainer');

            const passengerDiv = document.createElement('div');
            passengerDiv.classList.add('passenger');
            passengerDiv.setAttribute('id', 'passenger_' + passengerCount);
            passengerDiv.innerHTML = `
                <h3>Пассажир №${passengerCount}</h3>
                    <label>Фамилия: <input type="text" name="family_${passengerCount}" value="${filledData.family || ''}" required></label><br>
                    <label>Имя: <input type="text" name="name_${passengerCount}" value="${filledData.name || ''}" required></label><br>
                    <label>Отчество: <input type="text" name="lastname_${passengerCount}" value="${filledData.lastname || ''}"></label><br>
                    <label>Серия паспорта: <input type="text" name="seria_doc_${passengerCount}" maxlength="4" required></label><br>
                    <label>Номер паспорта: <input type="text" name="num_doc_${passengerCount}" maxlength="6" required></label><br>
                    <label>Льготная категория:
                        <select name="lgot_${passengerCount}">
                            <option value="Нет льготной категории">Нет льготной категории</option>
                            <option value="Пенсионер">Пенсионер</option>
                            <option value="Инвалид">Инвалид</option>
                            <option value="Ребенок">Ребенок</option>
                        </select>
                    </label><br>
                    <button type="button" onclick="removePassenger(${passengerCount})">Удалить пассажира</button>
            `;

            container.appendChild(passengerDiv);

            // Обновляем скрытое поле количества человек
            document.getElementById('num_people').value = passengerCount;
        }

        // Функция удаления пассажира
        function removePassenger(id) {
            const passengerDiv = document.getElementById('passenger_' + id);
            if (passengerDiv) {
                passengerDiv.remove();
                passengerCount--;

                // Переобновляем все пассажиры по порядку
                reindexPassengers();
            }
        }

        // Функция переиндексации полей после удаления
        function reindexPassengers() {
            const container = document.getElementById('passengersContainer');
            const passengers = container.querySelectorAll('.passenger');
            passengerCount = 0;
            passengers.forEach((passenger, index) => {
                passengerCount++;
                passenger.id = 'passenger_' + passengerCount;
                passenger.querySelector('h3').textContent = 'Пассажир №' + passengerCount;

                const inputs = passenger.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.name.includes('family_')) input.name = 'family_' + passengerCount;
                    if (input.name.includes('name_')) input.name = 'name_' + passengerCount;
                    if (input.name.includes('lastname_')) input.name = 'lastname_' + passengerCount;
                    if (input.name.includes('seria_doc_')) input.name = 'seria_doc_' + passengerCount;
                    if (input.name.includes('num_doc_')) input.name = 'num_doc_' + passengerCount;
                    if (input.name.includes('lgot_')) input.name = 'lgot_' + passengerCount;
                });

                const removeButton = passenger.querySelector('button[onclick^="removePassenger"]');
                removeButton.setAttribute('onclick', `removePassenger(${passengerCount})`);
            });

            document.getElementById('num_people').value = passengerCount;
        }

        // При загрузке страницы добавляем одного пассажира
        window.onload = function() {
            addPassenger();
        };
    </script>
</section>
<?php include 'footer.php'; ?>
</body>
</html>