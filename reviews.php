<?php
session_start();
require_once 'db_connect.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['user_id'])) {
    die('Вы не авторизованы. Пожалуйста, <a href="login.php">войдите</a>, чтобы оставить отзыв.');
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
    // Получаем данные из формы
    $review_rating = isset($_POST['review_rating']) ? intval($_POST['review_rating']) : 0;
    $review_text = trim($_POST['review_text'] ?? '');

    // Валидация данных
    if ($review_rating < 1 || $review_rating > 5) {
        die('Некорректная оценка. Оценка должна быть от 1 до 5.');
    }
    if (empty($review_text)) {
        die('Текст отзыва не может быть пустым.');
    }

    // Сохраняем отзыв в базу данных
    $stmt = $conn->prepare("
        INSERT INTO reviews (
            review_user_id,
            review_tour_id,
            review_rating,
            review_text,
            reviews_date
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('iids', $_SESSION['user_id'], $tour_id, $review_rating, $review_text);
    if (!$stmt->execute()) {
        die('Ошибка при сохранении отзыва: ' . $conn->error);
    }

    // Перенаправляем обратно на страницу отзывов
    header("Location: reviews.php?id=$tour_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отзывы на тур: <?= htmlspecialchars($tour['tour_name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>
<section class="main reviews-page">
    <h2 class="reviews-title">Отзывы на тур: <?= htmlspecialchars($tour['tour_name']) ?></h2>

    

    <!-- Форма для добавления отзыва -->
    <form class="review-form" method="POST" action="">
        <label class="form-label" for="review_rating">
            Оценка:
            <select class="form-select" name="review_rating" id="review_rating" required>
                <option value="">Выберите оценку</option>
                <option value="1">1 звезда</option>
                <option value="2">2 звезды</option>
                <option value="3">3 звезды</option>
                <option value="4">4 звезды</option>
                <option value="5">5 звезд</option>
            </select>
        </label>

        <label class="form-label" for="review_text">
            Текст отзыва:
            <textarea class="form-textarea" name="review_text" id="review_text" rows="5" cols="30" required></textarea>
        </label>

        <button class="form-button" type="submit">Оставить отзыв</button>
    </form>
    <!-- Отображение существующих отзывов -->
    <ul class="reviews-list">
        <?php
        // Получаем все отзывы для данного тура
        $stmt = $conn->prepare("SELECT * FROM reviews WHERE review_tour_id = ?");
        $stmt->bind_param('i', $tour_id);
        $stmt->execute();
        $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($reviews) > 0) {
            foreach ($reviews as $review) {
                echo '<li class="review-item">';
                echo '<p class="review-date"><i class="fa-solid fa-calendar"></i> ' . date('d.m.Y H:i', strtotime($review['reviews_date'])) . '</p>';
                echo '<p class="review-rating"><i class="fa-solid fa-star"></i> ' . $review['review_rating'] . ' звезд</p>';
                echo '<p class="review-text">' . htmlspecialchars($review['review_text']) . '</p>';
                echo '</li>';
            }
        } else {
            echo '<p class="no-reviews">Еще нет отзывов.</p>';
        }
        ?>
    </ul>
</section>
<?php include 'footer.php'; ?>
</body>
</html>