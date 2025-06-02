<?php
session_start();
require_once 'db_connect.php';

// Функция для проверки корректности даты
function isValidDate($date) {
    $dateParts = explode('-', $date);
    if (count($dateParts) === 3) {
        list($year, $month, $day) = $dateParts;
        return checkdate((int)$month, (int)$day, (int)$year);
    }
    return false;
}

// Включаем вывод ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Получение фильтров из GET-параметров
$category_name = isset($_GET['category']) ? trim($_GET['category']) : '';
$departure_city = isset($_GET['departure_city']) ? trim($_GET['departure_city']) : '';
$arrival_city = isset($_GET['arrival_city']) ? trim($_GET['arrival_city']) : '';
$start_date = isset($_GET['start_date']) && strtotime($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && strtotime($_GET['end_date']) ? $_GET['end_date'] : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$rating_min = isset($_GET['rating_min']) && is_numeric($_GET['rating_min']) ? intval($_GET['rating_min']) : null;

// Обработка параметра features
$selected_features = [];
if (isset($_GET['features'])) {
    if (is_array($_GET['features'])) {
        $selected_features = array_map('intval', $_GET['features']);
    } elseif (is_string($_GET['features'])) {
        $selected_features = array_map('intval', explode(',', $_GET['features']));
    }
}

$current_date = date('Y-m-d');

// Проверка даты отправления
if (!empty($start_date)) {
    if (!isValidDate($start_date)) {
        die('Ошибка: Некорректная дата отправления (используется несуществующая дата).');
    }
    if (strtotime($start_date) < strtotime($current_date)) {
        die('Ошибка: Дата отправления не может быть в прошлом.');
    }
}

// Проверка даты возвращения
if (!empty($end_date)) {
    if (!isValidDate($end_date)) {
        die('Ошибка: Некорректная дата возвращения (используется несуществующая дата).');
    }
    if (strtotime($end_date) < strtotime($current_date)) {
        die('Ошибка: Дата возвращения не может быть в прошлом.');
    }
    if (!empty($start_date) && strtotime($end_date) < strtotime($start_date)) {
        die('Ошибка: Дата возвращения не может быть раньше даты отправления.');
    }
}


// Запрос категорий и городов для фильтра
$categories = $conn->query("SELECT * FROM categories");
$cities = $conn->query("SELECT * FROM cities");

// Формируем SQL-запрос
$sql = "SELECT t.*, 
               dc.city_name AS departure_city_name, 
               ac.city_name AS arrival_city_name, 
               cat.category_name, (SELECT AVG(review_rating) 
                FROM reviews 
                WHERE review_tour_id = t.tour_id) AS avg_rating 
        FROM tours t
        JOIN cities dc ON t.tour_departure_city_id = dc.city_id
        JOIN cities ac ON t.tour_arrival_city_id = ac.city_id
        JOIN categories cat ON t.tour_category_id = cat.category_id
        WHERE 1=1";

// Добавляем условия фильтрации
if (!empty($departure_city)) {
    $sql .= " AND dc.city_name = '" . $conn->real_escape_string($departure_city) . "'";
}
if (!empty($arrival_city)) {
    $sql .= " AND ac.city_name = '" . $conn->real_escape_string($arrival_city) . "'";
}
if (!empty($category_name)) {
    $sql .= " AND cat.category_name = '" . $conn->real_escape_string($category_name) . "'";
}
if (!empty($start_date)) {
    $sql .= " AND t.tour_start_date = '" . $conn->real_escape_string($start_date) . "'";
} else {
    $sql .= " AND t.tour_start_date >= '" . $conn->real_escape_string($current_date) . "'";
}
if (!empty($end_date)) {
    $sql .= " AND t.tour_end_date = '" . $conn->real_escape_string($end_date) . "'";
}
if (!empty($min_price)) {
    $sql .= " AND t.tour_price >= $min_price";
}
if (!empty($max_price)) {
    $sql .= " AND t.tour_price <= $max_price";
}
if (!empty($rating_min) || !empty($rating_max)) {
    $sql .= " AND t.tour_id IN (
        SELECT review_tour_id
        FROM reviews
        GROUP BY review_tour_id
        HAVING AVG(review_rating) >= " . ($rating_min ?? 0) . "
    )";
}
if (!empty($selected_features)) {
    $feature_ids = implode(',', $selected_features);
    $sql .= " AND t.tour_id IN (
        SELECT tour_feature_tour_id
        FROM tour_features
        WHERE tour_feature_id IN ($feature_ids)
        GROUP BY tour_feature_tour_id
        HAVING COUNT(DISTINCT tour_feature_id) = " . count($selected_features) . "
    )";
}

$tours = $conn->query($sql);
if (!$tours) {
    die("Ошибка в SQL-запросе: " . $conn->error . "<br>SQL: " . $sql);
}

// Проверяем, является ли запрос AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Возвращаем только блок с турами
    if ($tours->num_rows > 0): ?>
        <?php while ($tour = $tours->fetch_assoc()): ?>
            <div class="tour-card">                
                <div class="slider-container slider-container-tour">
                    <div class="slides">
                        <?php
                        $tour_id = intval($tour['tour_id']);
                        $images = $conn->query("SELECT tour_image_path FROM tour_images WHERE tour_image_tour_id = $tour_id");
                        while ($img = $images->fetch_assoc()): ?>
                            <div class="slide slide-tour">
                                <img src="<?= htmlspecialchars($img['tour_image_path']) ?>" alt="Фото тура" width="200">
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="slider-controls">
                        <button class="prev-slide">&#10094;</button>
                        <button class="next-slide">&#10095;</button>
                    </div>
                </div>
                <a href="tour.php?id=<?= $tour['tour_id'] ?>" class="block-link">    
                    <div class="tour-card-info">
                            <div>
                            <h3>
                                <?= htmlspecialchars($tour['tour_name']) ?>
                            </h3>
                            <p><?= number_format($tour['tour_price'], 2) ?> руб.</p> 
                        </div>
                        <div>
                            <p>Туда: <?= htmlspecialchars($tour['tour_start_date']) ?></p>
                            <p><?= htmlspecialchars($tour['departure_city_name']) ?> → <?= htmlspecialchars($tour['arrival_city_name']) ?></p>
                        </div>
                        <div>
                            <p>Обратно: <?= htmlspecialchars($tour['tour_end_date']) ?></p>
                            <p><?= htmlspecialchars($tour['arrival_city_name']) ?> → <?= htmlspecialchars($tour['departure_city_name']) ?></p>   
                        </div>                                
                        <div>
                            <p class="tour-description">Описание: <?= htmlspecialchars($tour['tour_description']) ?></p>
                        </div>
                        <div><?php 
                        if (!empty($tour['category_name'])) {
                             echo '<p>Категория: ' . htmlspecialchars($tour['category_name']) . '</p>';
                        }
                        ?>
                        <p>
                            <?php if ($tour['avg_rating'] !== null): ?>
                                <i class="fa-solid fa-star" style="color: gold;"></i>
                            <?= number_format($tour['avg_rating'], 2) ?>
                            <?php else: ?>
                                <i class="fa-solid fa-star" style="color: gold;"></i>
                                Нет оценок
                            <?php endif; ?>
                        </p>
                        </div>
                                
                        <ul class="tour-features">
                            <?php
                            $params = $conn->query("SELECT feature_name FROM features JOIN tour_features ON features.feature_id = tour_features.tour_feature_id WHERE tour_feature_tour_id = $tour_id");
                            if ($params === false) {
                                echo '<li>Ошибка при загрузке характеристик</li>';
                            } elseif ($params->num_rows > 0) {
                                while ($param = $params->fetch_assoc()): ?>
                                    <li><?= htmlspecialchars($param['feature_name']) ?></li>
                                <?php endwhile;
                            }
                            ?>
                        </ul>
                    </div>
                            
                </a>
                <?php
                        // Проверяем, находится ли тур в избранном
                        $tour_id = intval($tour['tour_id']);
                        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        $is_in_wishlist = false;

                        if ($user_id) {
                            $check_query = $conn->query("SELECT * FROM wishlist WHERE wishlist_user_id = $user_id AND wishlist_tour_id = $tour_id");
                            $is_in_wishlist = ($check_query && $check_query->num_rows > 0);
                        }
                        ?>
                        <div style="width: 100%;display: flex; justify-content: space-evenly;">
                            <button class="btn-wishlist <?= $is_in_wishlist ? 'added' : '' ?>" data-tour-id="<?= htmlspecialchars($tour['tour_id']) ?>">
                            <i class="fa-solid fa-heart"></i>
                            </button>
                            <a href="book_tour.php?id=<?= $tour['tour_id'] ?>" class="btn-book">Забронировать тур</a>
                        </div>
                
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="no-tours">Туры не найдены.</p>
    <?php endif;
    exit; // Прерываем выполнение скрипта после возврата данных
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Каталог туров | World Travel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <section class="main">
        <div class="search-box">
            <form method="GET" class="search-form" id="search-form">
                <div class="search-form-element duo">
                    <div class="duo-element">
                        <label class="search-form-label">Откуда:</label>
                        <input placeholder="Город отправления" class="search-form-city" type="text" id="departure_city" name="departure_city" list="city-list" value="<?= htmlspecialchars($departure_city) ?>" required>
                        <datalist id="city-list">
                            <?php while ($row = $cities->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['city_name']) ?>"></option>
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                    <div class="duo-element">
                        <label class="search-form-label">Куда:</label>
                        <input placeholder="Город прибытия" class="search-form-city" type="text" id="arrival_city" name="arrival_city" list="city-list" value="<?= htmlspecialchars($arrival_city) ?>" required>
                        <datalist id="city-list">
                            <?php while ($row = $cities->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['city_name']) ?>"></option>
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                </div>
                <div class="search-form-element category">
                    <label class="search-form-label">Категория:</label>
                    <input placeholder="Категория" class="search-form-category" type="text" id="category" name="category" list="category-list" value="<?= htmlspecialchars($category_name) ?>" required>
                    <datalist id="category-list">
                        <?php while ($row = $categories->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['category_name']) ?>"></option>
                        <?php endwhile; ?>
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
                <button class="cta-button catalog" type="button" id="apply-filters">Применить</button>
                <script type="text/javascript">
                    document.addEventListener('DOMContentLoaded', function () {
                        const applyButton = document.getElementById('apply-filters');
                        if (applyButton) {
                            applyButton.addEventListener('click', function (e) {
                                e.preventDefault();
                                applyFilters();
                            });
                        }
                    });
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
            </form>
        </div>
        <div class="filter-container">
            <!-- Фильтр по цене -->
            <div class="filter-item price-filter close" id="price">
                <h3 class="filter-title" onclick="toggleAndApplyFilter('price')">Цена <i class="fa-solid fa-chevron-down"></i></h3>
                <div class="filter-content">
                    <label>
                        От: <input type="number" name="min_price" id="min-price-input" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
                    </label>
                    <label>
                        До: <input type="number" name="max_price" id="max-price-input" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
                    </label>
                </div>
            </div>
            <!-- Фильтр по оценке -->
            <div class="filter-item rating-filter close" id="rating">
                <h3 class="filter-title" onclick="toggleAndApplyFilter('rating')">Оценка <i class="fa-solid fa-chevron-down"></i></h3>
                <div class="filter-content">
                    <label>
                        Мин: 
                        <select name="rating_min" id="rating-min">
                            <option value="">-</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </label>
                </div>
            </div>
            <!-- Фильтр по дополнительным характеристикам -->
            <div class="filter-item features-filter close" id="features">
                <h3 class="filter-title" onclick="toggleAndApplyFilter('features')">Доп. характеристики <i class="fa-solid fa-chevron-down"></i></h3>
                <div class="filter-content">
                    <?php
                    $features = $conn->query("SELECT * FROM features");
                    while ($feature = $features->fetch_assoc()):
                    ?>
                        <label>
                            <input type="checkbox" name="features[]" value="<?= htmlspecialchars($feature['feature_id']) ?>" <?= isset($_GET['features']) && in_array($feature['feature_id'], $_GET['features']) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($feature['feature_name']) ?>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="filter-item features-filter close" id="clear-filters">
                <h3 class="filter-title">Очистить фильтры </h3>    
            </div>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function () {
                    // Кнопка "Очистить все фильтры"
                    const clearButton = document.getElementById('clear-filters');
                    if (clearButton) {
                        clearButton.addEventListener('click', function () {
                            clearAllFilters();
                        });
                    }
                });

                function clearAllFilters() {
                    // // Очистка полей формы поиска
                    // const departureCityInput = document.getElementById('departure_city');
                    // if (departureCityInput) departureCityInput.value = '';

                    // const arrivalCityInput = document.getElementById('arrival_city');
                    // if (arrivalCityInput) arrivalCityInput.value = '';

                    // const categoryInput = document.getElementById('category');
                    // if (categoryInput) categoryInput.value = '';

                    // const startDateInput = document.querySelector('.search-form-startdate');
                    // if (startDateInput) startDateInput.value = '';

                    // const endDateInput = document.querySelector('.search-form-enddate');
                    // if (endDateInput) endDateInput.value = '';

                    // Очистка фильтра цены
                    const minPriceInput = document.getElementById('min-price-input');
                    if (minPriceInput) minPriceInput.value = '';

                    const maxPriceInput = document.getElementById('max-price-input');
                    if (maxPriceInput) maxPriceInput.value = '';

                    // Очистка фильтра оценки
                    const ratingMinSelect = document.getElementById('rating-min');
                    if (ratingMinSelect) ratingMinSelect.value = '';

                    // Очистка чекбоксов дополнительных характеристик
                    const featureCheckboxes = document.querySelectorAll('input[name="features[]"]');
                    if (featureCheckboxes.length > 0) {
                        featureCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                    }

                    // Закрытие всех открытых фильтров
                    const filterItems = document.querySelectorAll(".filter-item");
                    if (filterItems.length > 0) {
                        filterItems.forEach((item) => {
                            item.classList.remove("open");
                            item.classList.add("close");

                            const arrowIcon = item.querySelector("i");
                            if (arrowIcon) {
                                arrowIcon.classList.remove("rotate");
                            }
                        });
                    }
                    applyFilters();
                }
            </script>
            
        </div>    
        <div class="tour-container">
            <?php if ($tours->num_rows > 0): ?>
                <?php while ($tour = $tours->fetch_assoc()): ?>
                    <div class="tour-card">
                        <div class="slider-container slider-container-tour">
                            <div class="slides">
                                <?php
                                $tour_id = intval($tour['tour_id']);
                                $images = $conn->query("SELECT tour_image_path FROM tour_images WHERE tour_image_tour_id = $tour_id");
                                while ($img = $images->fetch_assoc()): ?>
                                    <div class="slide slide-tour">
                                        <img src="<?= htmlspecialchars($img['tour_image_path']) ?>" alt="Фото тура" width="200">
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="slider-controls">
                                <button class="prev-slide">&#10094;</button>
                                <button class="next-slide">&#10095;</button>
                            </div>
                        </div>
                        <a href="tour.php?id=<?= $tour['tour_id'] ?>" class="block-link">
                            <div class="tour-card-info">
                                <div>
                                    <h3>
                                        <?= htmlspecialchars($tour['tour_name']) ?>
                                    </h3>
                                    <p class="price"><?= number_format($tour['tour_price'], 2) ?> руб.</p> 
                                </div>
                                <div>
                                    <p>Туда: <?= htmlspecialchars($tour['tour_start_date']) ?></p>
                                    <p><?= htmlspecialchars($tour['departure_city_name']) ?> → <?= htmlspecialchars($tour['arrival_city_name']) ?></p>
                                </div>
                                <div>
                                    <p>Обратно: <?= htmlspecialchars($tour['tour_end_date']) ?></p>
                                    <p><?= htmlspecialchars($tour['arrival_city_name']) ?> → <?= htmlspecialchars($tour['departure_city_name']) ?></p>   
                                </div>
                                
                                <div>
                                    <p class="tour-description">Описание: <?= htmlspecialchars($tour['tour_description']) ?></p>
                                </div>
                                <div><?php 
                                if (!empty($tour['category_name'])) {
                                     echo '<p>Категория: ' . htmlspecialchars($tour['category_name']) . '</p>';
                                }
                                ?>
                                <p>
                                    <?php if ($tour['avg_rating'] !== null): ?>
                                        <i class="fa-solid fa-star" style="color: gold;"></i>
                                        <?= number_format($tour['avg_rating'], 2) ?>
                                    <?php else: ?>
                                        <i class="fa-solid fa-star" style="color: gold;"></i>
                                        Нет оценок
                                    <?php endif; ?>
                                </p>
                                </div>
                                
                                <ul class="tour-features">
                                    <?php
                                    $params = $conn->query("SELECT feature_name FROM features JOIN tour_features ON features.feature_id = tour_features.tour_feature_id WHERE tour_feature_tour_id = $tour_id");
                                    if ($params === false) {
                                        echo '<li>Ошибка при загрузке характеристик</li>';
                                    } elseif ($params->num_rows > 0) {
                                        while ($param = $params->fetch_assoc()): ?>
                                            <li><?= htmlspecialchars($param['feature_name']) ?></li>
                                        <?php endwhile;
                                    }
                                    ?>
                                </ul>
                            </div>
                            
                        </a>
                        <?php
                        // Проверяем, находится ли тур в избранном
                        $tour_id = intval($tour['tour_id']);
                        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        $is_in_wishlist = false;

                        if ($user_id) {
                            $check_query = $conn->query("SELECT * FROM wishlist WHERE wishlist_user_id = $user_id AND wishlist_tour_id = $tour_id");
                            $is_in_wishlist = ($check_query && $check_query->num_rows > 0);
                        }
                        ?>
                        <div style="width: 100%;display: flex; justify-content: space-evenly;">
                            <button class="btn-wishlist <?= $is_in_wishlist ? 'added' : '' ?>" data-tour-id="<?= htmlspecialchars($tour['tour_id']) ?>">
                            <i class="fa-solid fa-heart"></i>
                            </button>
                            <a href="book_tour.php?id=<?= $tour['tour_id'] ?>" class="btn-book">Забронировать тур</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-tours">Туры не найдены.</p>
            <?php endif; ?>
        </div>
    </section>  
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            const wishlistButtons = document.querySelectorAll(".btn-wishlist");

            wishlistButtons.forEach(button => {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                const tourId = this.getAttribute("data-tour-id");
                const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;

                if (!userId) {
                    alert("Пожалуйста, авторизуйтесь, чтобы добавить тур в избранное.");
                    return;
                }

                fetch('check_wishlist.php', {
                    method: 'POST',
                    headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                    tour_id: tourId,
                    user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                    const isAdded = data.is_in_wishlist;

                    // Находим элемент с текстом кнопки
                    const buttonText = this.querySelector('.btn-text');

                    if (isAdded) {
                        // Если тур добавлен в избранное, меняем текст и стиль
                        this.classList.add('added');
                    } else {
                        // Если тур удален из избранного, меняем текст и стиль
                        this.classList.remove('added');
                    }
                    } else {
                        alert(data.message || 'Ошибка при обновлении избранного');
                    }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                    });
                    });
                });
            });
    </script>  
    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>
<?php $conn->close(); ?>