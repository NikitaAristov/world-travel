<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>О Нас | World Travel</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://api-maps.yandex.ru/2.1/?apikey=a639577d-1ce4-4c2b-91d9-7c72a8aa6d4a&lang=ru_RU" type="text/javascript"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <section class="main">
    	<div class="container">
	        <!-- О нас -->
	        <section class="about-section">
	            <img src="img/office.jpeg" alt="World Travel">
	            <div class="about-text">
	                <h2>О нас</h2>
	                <p>Мы — турагенство "World Travel", основанное в 2015 году в Екатеринбурге. Наша миссия — помочь вам открыть красоту России, организовав незабываемые путешествия по всем уголкам нашей великой страны.</p>
	                <p>Мы специализируемся на индивидуальных и групповых турах, экскурсиях, а также экстремальных маршрутах для любителей приключений. Наша команда профессионалов всегда готова предложить вам уникальные маршруты и поддержку на всех этапах путешествия.</p>
	            </div>
	        </section>

	        <!-- Контакты -->
	        <section class="contacts">
	            <div class="contact-info">
	            	<div class="contact-element">
	            		<h2>Контакты</h2>

		                	<div>
			                    <p><i class="fas fa-map-marker-alt"></i> Адрес: г. Екатеринбург, ул. Машиностроителей, д. 11</p>
			                    <p><i class="fas fa-phone"></i> Телефон: +7 (343) 123-45-67</p>
			                    <p><i class="fas fa-envelope"></i> Email: info@world-travel.ru</p>
			                </div>
			                <div>
			                    <p><i class="fas fa-clock"></i> Часы работы:</p>
			                    <p>Пн-Пт: 9:00 - 18:00</p>
			                    <p>Сб: 10:00 - 15:00</p>
			                    <p>Вс: выходной</p>
			                </div>
			        </div>
			        <div class="contact-element">
			        	<h2>Наш офис на карте</h2>
				        <div class="map" id="map" style="width:100%; height: 300px;"></div>
				            <script>
				                ymaps.ready(function () {
				                    var myMap = new ymaps.Map('map', {
				                        center: [56.8863, 60.6013], // Координаты Екатеринбурга
				                        zoom: 14
				                    });

				                    var myPlacemark = new ymaps.Placemark([56.8863, 60.6013], {
				                        hintContent: 'Турагенство "Россия Открывает"',
				                        balloonContent: 'Адрес: г. Екатеринбург, ул. Машиностроителей, д. 11'
				                    });

				                    myMap.geoObjects.add(myPlacemark);
				                });
				            </script>	
				    </div>    
		            
	            </div>
	        </section>


	        <!-- Отзывы -->
	        <section class="reviews">
	            <h2>Отзывы наших клиентов</h2>
	            <div class="review-card">
	                <p>Отличное агентство! Организовали тур в Байкал, все прошло безупречно. Спасибо за заботу и внимание к деталям!</p>
	                <span>— Анна Иванова</span>
	            </div>
	            <div class="review-card">
	                <p>Благодаря 'World Travel' я посетил Камчатку. Поразительная природа и профессиональная организация тура оставили неизгладимое впечатление!</p>
	                <span>— Михаил Петров</span>
	            </div>
	            <div class="review-card">
	                <p>Хочу поблагодарить команду агентства за прекрасный отдых в Карелии. Все было продумано до мелочей!</p>
	                <span>— Елена Сидорова</span>
	            </div>
	        </section>
	    </div>
    </section>
    <?php include 'footer.php'; ?>
</body>
</html>