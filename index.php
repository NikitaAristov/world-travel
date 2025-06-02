<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>World Travel - Открой мир заново</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Открой для себя мир заново</h1>
            <p class="lead">Планируйте свой идеальный отпуск с нами</p>
            <div class="search-box">
                <form class="search-form" action="catalog.php" method="GET">
                    <div class="search-form-element duo">
                        <div class="duo-element">
                            <label class="search-form-label">Откуда:</label>
                            <input class = "search-form-city" type="text" id="departure_city" name="departure_city" placeholder="Город отправления" list="city-list" autocomplete="off">
                            <datalist id="city-list">
                                <?php
                                require_once 'db_connect.php'; // Подключение к БД
                                $cities = $conn->query("SELECT city_name FROM cities");
                                while ($row = $cities->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['city_name']) . "'>";
                                }
                                ?>
                            </datalist>
                        </div>
                        <div class="duo-element">
                            <label class="search-form-label">Куда:</label>
                            <input class = "search-form-city" type="text" id="arrival_city" name="arrival_city" placeholder="Город прибытия" list="city-list" autocomplete="off">
                            <datalist id="city-list">
                                <?php
                                require_once 'db_connect.php'; // Подключение к БД
                                $cities = $conn->query("SELECT city_name FROM cities");
                                while ($row = $cities->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['city_name']) . "'>";
                                }
                                ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="search-form-element category">
                        <label class="search-form-label">Категория:</label>
                        <input class = "search-form-category" type="text" id="category" name="category" placeholder="Категория" list="category-list" autocomplete="off">
                        <datalist id="category-list">
                            <?php
                            $categories = $conn->query("SELECT category_name FROM categories");
                            while ($row = $categories->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['category_name']) . "'>";
                            }
                            ?>
                        </datalist>
                    </div>
                    <div class="search-form-element duo">
                        <div class="duo-element">
                            <label class="search-form-label">Дата отправления: </label>
                            <input class="search-form-startdate" type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="duo-element">
                            <label class="search-form-label">Дата возвращения: </label>
                            <input class="search-form-enddate" type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <script type="text/javascript">
                        document.addEventListener("DOMContentLoaded", function () {
                            const startDateInput = document.querySelector('.search-form-startdate');
                            const endDateInput = document.querySelector('.search-form-enddate');

                            // Обновляем минимальную дату для поля endDateInput при изменении startDateInput
                            startDateInput.addEventListener('change', function () {
                                if (startDateInput.value) {
                                    endDateInput.min = startDateInput.value;
                                } else {
                                    endDateInput.min = new Date().toISOString().split('T')[0];
                                }
                            });

                            // Проверяем корректность даты возвращения при завершении ввода
                            endDateInput.addEventListener('blur', function () {
                                if (endDateInput.value && startDateInput.value) {
                                    if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                                        alert('Дата возвращения не может быть раньше даты отправления.');
                                        endDateInput.value = ''; // Очищаем поле даты возвращения
                                    }
                                }
                            });
                        });
                    </script>
                    <!-- Кнопка поиска -->
                    <button class="cta-button" type="submit">Найти туры</button>
                </form>
            </div>
        </div>
    </section>
    <!-- Кнопка для открытия/закрытия чата -->
    <button class="chat-button" id="chatButton">💬</button>

    <!-- Контейнер для чата -->
    <div class="chat-container" id="chatContainer">
        <iframe
            src="https://www.chatbase.co/chatbot-iframe/f-ddPUjVox2W7AxkbdIZP"
            frameborder="0"
        ></iframe>
    </div>


<?php
require_once 'db_connect.php';

$popularCities = [4,5];
$slides = '';

foreach ($popularCities as $city) {
    $stmt = $conn->prepare("
        SELECT city_name 
        FROM cities 
        WHERE city_id = ?
    ");
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $stmt->bind_result($cityName);
    $stmt->fetch();
    $stmt->close();

    // Получаем минимальную цену
    $stmt = $conn->prepare("
        SELECT MIN(tour_price) as min_price 
        FROM tours 
        WHERE tour_arrival_city_id = ?
    ");
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $stmt->bind_result($minPrice);
    $stmt->fetch();
    $stmt->close();

    if (!$minPrice) continue; // Пропускаем, если нет туров

    // Получаем изображение
    $stmt = $conn->prepare("
        SELECT ti.tour_image_path 
        FROM tour_images ti
        JOIN tours t ON ti.tour_image_tour_id = t.tour_id
        WHERE t.tour_arrival_city_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $stmt->bind_result($imageUrl);
    $stmt->fetch();
    $stmt->close();

    if (!$imageUrl) {
        // Если изображения нет — ставим заглушку
        $imageUrl = 'placeholder.jpg';
    }

    // Генерируем HTML
    $slides .= '
        <a href="catalog.php?arrival_city=' . urlencode($cityName) . '" class="slide-link" style="width:100%">
            <div class="slide">
                <img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($cityName) . '">
                <div class="destination-info">
                    <h3>' . htmlspecialchars($cityName) . '</h3>
                    <p>От ' . number_format($minPrice, 0, '', ' ') . ' руб.</p>
                </div>
            </div>
        </a>
    ';
}
?>

<!-- Вставляем слайды -->
<section class="destinations">
    <h2 class="section-title">Популярные направления</h2>
    <div class="slider-container">
        <div class="slides">
            <?= $slides ?>
        </div>
        <div class="slider-controls">
            <button class="prev-slide">&#10094;</button>
            <button class="next-slide">&#10095;</button>
        </div>
    </div>
</section>





    <!-- Features -->
    <section class="features">
        <div class="feature-card">
            <i class="fas fa-shield-alt fa-3x"></i>
            <h3>Безопасные путешествия</h3>
            <p>Гарантия лучших цен и полная безопасность в путешествии</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-globe-americas fa-3x"></i>
            <h3>150+ направлений</h3>
            <p>Путешествуйте по самым популярным местам России и СНГ</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-headset fa-3x"></i>
            <h3>24/7 Поддержка</h3>
            <p>Круглосуточная помощь во время путешествий</p>
        </div>
    </section>
    

    <?php include 'footer.php'; ?>

        <script src="scripts.js"></script> 
    </body>
</html>
