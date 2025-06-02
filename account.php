<?php
session_start();
require_once 'db_connect.php';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Перенаправляем на страницу входа
    exit;
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя из базы данных
$user_query = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$user = $user_query->fetch_assoc();

// Обработка обновления данных пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST["full_name"]);
    $email = $conn->real_escape_string($_POST["email"]);
    $phone = $conn->real_escape_string($_POST["phone"]);
    $new_password = $_POST["password"];
    $old_password = $_POST["old_password"];

    // Проверка ФИО
    $name_parts = explode(" ", $full_name);
    if (count($name_parts) < 2) {
        $error_message = "Ошибка: Введите хотя бы фамилию и имя!";
    } else {
        $family = $name_parts[0];
        $name = $name_parts[1];
        $lastname = isset($name_parts[2]) ? $name_parts[2] : null; // Отчество может быть пустым

        // Проверка формата ФИО (только русские буквы)
        if (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $family)) {
            $error = "Ошибка: Фамилия для человека $i должна начинаться с заглавной буквы и содержать только русские буквы.";
        }
        elseif (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $name)) {
            $error = "Ошибка: Имя для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
        }
        elseif ($lastname && !preg_match('/^[А-ЯЁ][а-яё]*$/u', $lastname)) {
            $error = "Ошибка: Отчество для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
        }

        // Проверка старого пароля
        if (!password_verify($old_password, $user['user_password'])) {
            $error_message = "Ошибка: Неверный старый пароль.";
        } elseif (
			!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_])(?=.{6,})(?!.*\s).*$/', $password))
		{
			$error = "Ошибка: Пароль должен содержать минимум 6 символов, включая заглавные и строчные английские буквы, хотя бы один специальный символ, и не должен содержать пробелы.";
		} else {
            // Корректировка номера телефона
            if (preg_match('/^8\d{10}$/', $phone)) {
                // Если номер начинается с 8, заменяем 8 на +7
                $phone = '+7' . substr($phone, 1);
            } elseif (preg_match('/^9\d{9}$/', $phone)) {
                // Если номер начинается с 9, добавляем +7 в начало
                $phone = '+7' . $phone;
            }

            // Проверка формата номера телефона после корректировки
            if (!preg_match('/^\+7\d{10}$/', $phone)) {
                $error_message = "Ошибка: Номер телефона должен быть в формате +7XXXXXXXXXX (12 символов).";
            } else {
                // Проверка уникальности email и телефона
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE (user_email = ? OR user_phonenumber = ?) AND user_id != ?");
                if (!$stmt) {
                    $error_message = "Ошибка запроса (проверка email и телефона): " . $conn->error;
                } else {
                    $stmt->bind_param("ssi", $email, $phone, $user_id);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows > 0) {
                        $error_message = "Ошибка: Такой email или номер телефона уже зарегистрированы.";
                    } else {
                        // Хешируем новый пароль
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        // Обновляем данные пользователя
                        $update_query = "UPDATE users 
                                         SET user_family = ?, user_name = ?, user_lastname = ?, user_email = ?, user_password = ?, user_phonenumber = ? 
                                         WHERE user_id = ?";
                        $stmt = $conn->prepare($update_query);
                        if (!$stmt) {
                            $error_message = "Ошибка при обновлении данных: " . $conn->error;
                        } else {
                            $stmt->bind_param("ssssssi", $family, $name, $lastname, $email, $hashed_password, $phone, $user_id);
                            if ($stmt->execute()) {
                                $success_message = "Данные успешно обновлены!";
                            } else {
                                $error_message = "Ошибка при обновлении данных: " . $stmt->error;
                            }
                        }
                    }
                }
            }
        }
    }
}
$pending = "pending";
// Загружаем историю бронирований
$bookings_query = $conn->query("SELECT b.*, t.*, c.city_name AS arrival_city_name, p.payment_amount AS payment_amount
                                FROM bookings b 
                                JOIN tours t ON b.booking_tour_id = t.tour_id 
                                JOIN cities c ON t.tour_arrival_city_id = c.city_id
                                JOIN payments p ON b.booking_payment_id= p.yookassa_payment_id 
                                WHERE b.booking_user_id = $user_id AND p.payment_status <> '" . $conn->real_escape_string($pending) . "'");

// Загружаем избранные туры
$favorites_query = $conn->query("SELECT t.*, dc.city_name AS departure_city_name, ac.city_name AS arrival_city_name 
                                 FROM wishlist w 
                                 JOIN tours t ON w.wishlist_tour_id = t.tour_id 
                                 JOIN cities dc ON t.tour_departure_city_id = dc.city_id
                                 JOIN cities ac ON t.tour_arrival_city_id = ac.city_id
                                 WHERE w.wishlist_user_id = $user_id");

// Функция для перевода статуса
function translateStatus($status) {
    $statuses = [
        'pending' => ['text' => 'Ожидает оплаты', 'color' => '#FFA500'], // Оранжевый
        'confirmed' => ['text' => 'Подтверждён', 'color' => '#28a745'], // Зелёный
        'invalid' => ['text' => 'Ошибка оплаты', 'color' => '#dc3545'], // Красный
        'default' => ['text' => 'Неизвестный статус', 'color' => '#6c757d'] // Серый
    ];
    return $statuses[$status] ?? $statuses['default'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет | World Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <section class="main">
        <section class="account-page">
            <div class="account-container">
                <!-- Боковое меню -->
                <div class="account-sidebar">
                    <ul>
                        <li><a href="#" onclick="showBlock('bookings')">История бронирований</a></li>
                        <li><a href="#" onclick="showBlock('favorites')">Избранные туры</a></li>
                        <li><a href="#" onclick="showBlock('settings')">Настройки аккаунта</a></li>
                        <li><a href="logout.php">Выйти из аккаунта</a></li>
                    </ul>
                </div>

                <!-- Основное содержимое -->
                <div class="account-content">
                    <!-- История бронирований -->
                    <div id="bookings" class="account-section">
                        <h2>История бронирований</h2>
                        <?php if ($bookings_query->num_rows > 0): ?>
                            <?php
                            // Группируем бронирования по booking_group
                            $grouped_bookings = [];
                            while ($booking = $bookings_query->fetch_assoc()) {
                                $grouped_bookings[$booking['booking_group']][] = $booking;
                            }
                            ?>
                            <ul class="booking-list">
                                <?php foreach ($grouped_bookings as $group_id => $bookings_in_group): ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars($bookings_in_group[0]['tour_name']) ?></strong>
                                            <p>Сумма оплаты: <?= htmlspecialchars($bookings_in_group[0]['payment_amount']) ?> ₽</p>  
                                            <p>Дата бронирования: <?= htmlspecialchars($bookings_in_group[0]['booking_date']) ?></p>
                                        </div>
                                        <div>
                                            <ul>
                                                <?php foreach ($bookings_in_group as $booking): 
                                                    $lgot_display = ($booking['booking_lgot'] == "Нет льготной категории") ? "Без льготы" : $booking['booking_lgot'];?>
                                                    <li class="book-client">
                                                        ФИО: <?= htmlspecialchars($booking['booking_family']) ?> <?= htmlspecialchars($booking['booking_name']) ?> <?= htmlspecialchars($booking['booking_lastname']) ?> -- 
                                                        <?= htmlspecialchars($lgot_display) ?> <br>
                                                        Статус: 
                                                        <?php
                                                        // Получаем перевод статуса
                                                        $statusInfo = translateStatus($booking['booking_status']);
                                                        ?>
                                                        <span style="color: <?= htmlspecialchars($statusInfo['color']) ?>;">
                                                            <?= htmlspecialchars($statusInfo['text']) ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <a href="reviews.php?id=<?= $bookings_in_group[0]['booking_tour_id'] ?>" style="color: black;">Оставить отзыв <i class="fa-solid fa-star" style="color: gold;"></i></a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>У вас пока нет бронирований.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Избранные туры -->
                    <div id="favorites" class="account-section" style="display: none;">
                        <h2>Избранные туры</h2>
                        
                        <?php if ($favorites_query->num_rows > 0): ?>
                            <ul class="favorite-list tour-card">
                                <?php while ($favorite = $favorites_query->fetch_assoc()): ?>
                                    <a href="tour.php?id=<?= $favorite['tour_id'] ?>" class="block-link">
                                        <li class="tour-card-info">
                                            <div>
                                                <h3><?= htmlspecialchars($favorite['tour_name']) ?></h3>
                                                <p><?= number_format($favorite['tour_price'], 2) ?> руб.</p>
                                            </div>
                                            <div>
                                                <p>Туда: <?= htmlspecialchars($favorite['tour_start_date']) ?></p>
                                                <p><?= htmlspecialchars($favorite['departure_city_name']) ?> → <?= htmlspecialchars($favorite['arrival_city_name']) ?></p>
                                                <p>Обратно: <?= htmlspecialchars($favorite['tour_end_date']) ?></p>
                                                <p><?= htmlspecialchars($favorite['arrival_city_name']) ?> → <?= htmlspecialchars($favorite['departure_city_name']) ?></p>
                                            </div>
                                            <div>
                                                <p class="tour-description">Описание: <?= htmlspecialchars($favorite['tour_description']) ?></p>
                                            </div>
                                        </li>
                                    </a>
                                <?php endwhile; ?>
                            </ul>
                    <?php else: ?>
                        <p>У вас пока нет избранных туров.</p>
                    <?php endif; ?>
                    </div>

                    <!-- Настройки аккаунта -->
                    <div id="settings" class="account-section" style="display: none;">
                        <h2>Настройки аккаунта</h2>
                        <?php if (isset($success_message)): ?>
                            <p class="success"><?= $success_message ?></p>
                        <?php elseif (isset($error_message)): ?>
                            <p class="error"><?= $error_message ?></p>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <label for="full_name">ФИО: </label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['user_family'] . ' ' . $user['user_name'] . ' ' . $user['user_lastname']) ?>" required>

                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['user_email']) ?>" required>

                            <label for="phone">Телефон:</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['user_phonenumber']) ?>" required>

                            <label for="old_password">Старый пароль:</label>
                            <input type="password" id="old_password" name="old_password" required>

                            <label for="password">Новый пароль:</label>
                            <input type="password" id="password" name="password" required>

                            <button type="submit" name="update_profile">Сохранить изменения</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </section>
    <script>
        function showBlock(blockId) {
            // Скрываем все блоки
            document.querySelectorAll('.account-section').forEach(section => {
                section.style.display = 'none';
            });

            // Показываем выбранный блок
            const selectedBlock = document.getElementById(blockId);
            if (selectedBlock) {
                selectedBlock.style.display = 'block';
            }
        }

        // По умолчанию показываем первый блок ("История бронирований")
        window.onload = function () {
            showBlock('bookings');
        };
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>