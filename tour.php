<?php
session_start();
require_once 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Получаем ID тура
$tour_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tour_id <= 0) {
    die('Некорректный ID тура.');
}

// Загружаем данные о туре
$sql = "SELECT t.*, 
               dc.city_name AS departure_city_name, 
               ac.city_name AS arrival_city_name, 
               cat.category_name, (SELECT AVG(review_rating) 
                FROM reviews 
                WHERE review_tour_id = t.tour_id) AS avg_rating,
               h.*  
        FROM tours t
        JOIN cities dc ON t.tour_departure_city_id = dc.city_id
        JOIN cities ac ON t.tour_arrival_city_id = ac.city_id
        JOIN categories cat ON t.tour_category_id = cat.category_id
        JOIN hotels h ON t.tour_hotel_id = h.hotel_id
        WHERE t.tour_id = $tour_id";

$tour = $conn->query($sql)->fetch_assoc();

if (!$tour) {
    die('Тур не найден.');
}
// Получаем достопримечательности для города отеля
$landmarks_sql = "SELECT landmark_name, landmark_latitude, landmark_longitude 
                  FROM landmarks 
                  WHERE landmark_city_id = " . intval($tour['hotel_city_id']);
$landmarks = $conn->query($landmarks_sql);
$landmarks_data = [];
if ($landmarks->num_rows > 0) {
    while ($landmark = $landmarks->fetch_assoc()) {
        $landmarks_data[] = [
            'name' => $landmark['landmark_name'],
            'latitude' => $landmark['landmark_latitude'],
            'longitude' => $landmark['landmark_longitude']
        ];
    }
}
// Проверяем, находится ли тур в избранном
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$is_in_wishlist = false;

if ($user_id) {
    $check_query = $conn->query("SELECT * FROM wishlist WHERE wishlist_user_id = $user_id AND wishlist_tour_id = $tour_id");
    $is_in_wishlist = ($check_query && $check_query->num_rows > 0);
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <title><?= htmlspecialchars($tour['tour_name']) ?> | World Travel</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://api-maps.yandex.ru/2.1/?apikey=a639577d-1ce4-4c2b-91d9-7c72a8aa6d4a&lang=ru_RU" type="text/javascript"></script>
    <script src="scripts.js"></script>
</head>
<body>
<?php include 'header.php'; ?>
<section class="main">
    <a href="catalog.php" class="back-link">← Назад к каталогу</a>
    <div class="tour-info">                
                <div class="slider-container slider-container-tour-info">
                    <div class="slides">
                        <?php
                        $tour_id = intval($tour['tour_id']);
                        $images = $conn->query("SELECT tour_image_path FROM tour_images WHERE tour_image_tour_id = $tour_id");
                        while ($img = $images->fetch_assoc()): ?>
                            <div class="slide">
                                <img src="<?= htmlspecialchars($img['tour_image_path']) ?>" alt="Фото тура" width="200">
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="slider-controls">
                        <button class="prev-slide">&#10094;</button>
                        <button class="next-slide">&#10095;</button>
                    </div>
                </div>
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
                            <p>Описание: <?= htmlspecialchars($tour['tour_description']) ?></p>
                        </div>
                        <div><?php 
                        if (!empty($tour['category_name'])) {
                             echo '<p>Категория: ' . htmlspecialchars($tour['category_name']) . '</p>';
                        }
                        ?>
                        <a href="reviews.php?id=<?= $tour['tour_id'] ?>"><p>
                            <?php if ($tour['avg_rating'] !== null): ?>
                                <i class="fa-solid fa-star" style="color: gold;"></i>
                                <?= number_format($tour['avg_rating'], 2) ?>
                            <?php else: ?>
                                <i class="fa-solid fa-star" style="color: gold;"></i>
                                Нет оценок
                            <?php endif; ?>
                        </p></a>
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
                        <div>
                            <button class="btn-wishlist <?= $is_in_wishlist ? 'added' : '' ?> tour" data-tour-id="<?= htmlspecialchars($tour['tour_id']) ?>">
                                <i class="fa-solid fa-heart"></i>
                            </button>   
                            <a href="book_tour.php?id=<?= $tour['tour_id'] ?>" class="btn-book tour">Забронировать тур</a>
                        </div>
                    </div>
                    <div class="tour-card-info">
                        <div>
                            <h3>
                                <?= htmlspecialchars($tour['hotel_name']) ?>
                                <?= number_format($tour['hotel_star_rating']) ?>
                                <i class="fa-solid fa-star" style="color: gold;"></i>
                            </h3>
                        </div>
                        <div>
                            <p><?= htmlspecialchars($tour['arrival_city_name']) ?>, <?= htmlspecialchars($tour['hotel_address']) ?></p> 
                        </div>
                        <div>
                            <p><?= htmlspecialchars($tour['hotel_description']) ?></p>
                        </div>
                                             

                        <div>
                            <button class="btn-wishlist <?= $is_in_wishlist ? 'added' : '' ?> tour" data-tour-id="<?= htmlspecialchars($tour['tour_id']) ?>">
                                <i class="fa-solid fa-heart"></i>
                            </button> 
                            <a href="book_tour.php?id=<?= $tour['tour_id'] ?>" class="btn-book tour">Забронировать тур</a>
                        </div>
                        
                    </div>
                    <div class="slider-container slider-container-tour-info">
                        <div class="slides">
                            <?php
                            $hotel_id = intval($tour['tour_hotel_id']);
                            $hotel_images = $conn->query("SELECT hotel_image_path FROM hotel_images WHERE hotel_image_hotel_id = $hotel_id");
                            while ($img = $hotel_images->fetch_assoc()): ?>
                                <div class="slide">
                                    <img src="<?= htmlspecialchars($img['hotel_image_path']) ?>" alt="Фото тура" width="200">
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="slider-controls">
                            <button class="prev-slide">&#10094;</button>
                            <button class="next-slide">&#10095;</button>
                        </div>
                    </div>
                    <div id="map" style="width: 96%; height: 400px; margin:auto; margin-top: 30px;"></div>

    <script>
        ymaps.ready(init);

        function init() {
            // Получаем координаты отеля из PHP
            const hotelLatitude = <?= json_encode($tour['hotel_latitude']) ?>;
            const hotelLongitude = <?= json_encode($tour['hotel_longitude']) ?>;

            // Инициализация карты с центром на отеле
            const map = new ymaps.Map("map", {
                center: [hotelLatitude, hotelLongitude], // Центр карты - координаты отеля
                zoom: 12 // Более детальный масштаб
            });

            // Добавляем метку отеля
            const hotelPlacemark = new ymaps.Placemark(
                [hotelLatitude, hotelLongitude],
                {
                    balloonContent: "<strong><?= htmlspecialchars($tour['hotel_name']) ?></strong>"
                },
                {
                    preset: 'islands#blueIcon' // Синяя иконка для отеля
                }
            );
            map.geoObjects.add(hotelPlacemark);

            // Данные о достопримечательностях (передаются из PHP)
            const landmarks = <?= json_encode($landmarks_data) ?>;

            // Добавляем достопримечательности на карту
            landmarks.forEach(landmark => {
                const placemark = new ymaps.Placemark(
                    [landmark.latitude, landmark.longitude],
                    {
                        balloonContent: `<strong>${landmark.name}</strong>`
                    },
                    {
                        preset: 'islands#redIcon' // Красная иконка для достопримечательностей
                    }
                );
                map.geoObjects.add(placemark);
            });
        }
    </script>
            </div>
            <script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function () {
                    // Находим все кнопки "добавить в избранное"
                    const wishlistButtons = document.querySelectorAll(".btn-wishlist");

                    if (wishlistButtons.length > 0) {
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

                                        // Обновляем стиль только для текущей кнопки
                                        if (isAdded) {
                                            this.classList.add('added');
                                        } else {
                                            this.classList.remove('added');
                                        }

                                        // Если нужно обновить другие кнопки с тем же data-tour-id
                                        const buttonsToUpdate = document.querySelectorAll(`.btn-wishlist[data-tour-id="${tourId}"]`);
                                        buttonsToUpdate.forEach(btn => {
                                            if (isAdded) {
                                                btn.classList.add('added');
                                            } else {
                                                btn.classList.remove('added');
                                            }
                                        });

                                    } else {
                                        alert(data.message || 'Ошибка при обновлении избранного');
                                    }
                                })
                                .catch(error => {
                                    console.error('Ошибка:', error);
                                });
                            });
                        });
                    }
                });
            </script>
           
</section>
<?php include 'footer.php'; ?>

</body>
</html>
