document.addEventListener("DOMContentLoaded", function () {
    initializeSliders();
});

function initializeSliders() {
    document.querySelectorAll(".slider-container").forEach(container => {
        const slides = container.querySelectorAll(".slide");
        const prevButton = container.querySelector(".prev-slide");
        const nextButton = container.querySelector(".next-slide");

        if (slides.length === 0) return; // Если нет слайдов, ничего не делаем

        let currentIndex = 0;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.style.display = i === index ? "block" : "none";
            });
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % slides.length;
            showSlide(currentIndex);
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + slides.length) % slides.length;
            showSlide(currentIndex);
        }

        // Назначаем обработчики событий
        prevButton?.addEventListener("click", prevSlide);
        nextButton?.addEventListener("click", nextSlide);

        // Показываем первый слайд
        showSlide(currentIndex);

        // Автоматическая смена слайдов
        setInterval(nextSlide, 5000); // Меняет слайд каждые 5 секунд
    });
}

// Функция для переключения видимости чата
const chatButton = document.getElementById('chatButton');
const chatContainer = document.getElementById('chatContainer');

function toggleChat() {
    if (chatContainer.style.display === 'none' || chatContainer.style.display === '') {
        chatContainer.style.display = 'block';
        chatButton.classList.add('active');
        chatButton.textContent = '×';
    } else {
        chatContainer.style.display = 'none';
        chatButton.classList.remove('active');
        chatButton.textContent = '💬';
    }
}

chatButton?.addEventListener('click', toggleChat);

// Функция для фильтрации datalist
function filterDatalist(inputId, datalistId) {
    const input = document.getElementById(inputId);
    const datalist = document.getElementById(datalistId);
    const options = datalist.getElementsByTagName("option");

    input.addEventListener("input", function () {
        const filter = input.value.toLowerCase();
        for (let option of options) {
            option.style.display = option.value.toLowerCase().includes(filter) ? "" : "none";
        }
    });
}

filterDatalist("city", "city-list");
filterDatalist("category", "category-list");


function toggleAndApplyFilter(filterId) {
    const filterItem = document.querySelector(`.filter-item#${filterId}`);
    const filterContent = document.getElementById(filterId);
    const arrowIcon = document.querySelector(`[onclick="toggleAndApplyFilter('${filterId}')"] i`);

    // Закрываем все остальные фильтры
    document.querySelectorAll(".filter-item").forEach((item) => {
        if (item.id !== filterId) {
            item.classList.remove("open");
            item.classList.add("close");
            const otherArrow = item.querySelector("i");
            if (otherArrow) {
                otherArrow.classList.remove("rotate");
            }
        }
    });

    // Переключаем текущий фильтр
    if (filterItem.classList.contains("open")) {
        filterItem.classList.remove("open");
        filterItem.classList.add("close");
        arrowIcon.classList.remove("rotate");
    } else {
        filterItem.classList.remove("close");
        filterItem.classList.add("open");
        arrowIcon.classList.add("rotate");
    }

    // Применяем фильтр через AJAX
    applyFilters();
}



    // Функция для применения фильтров
    function applyFilters() {
        // Собираем данные из всех фильтров
        const formData = new FormData();

        // Город отправления
        const departureCity = document.getElementById('departure_city')?.value;
        if (departureCity) formData.append('departure_city', departureCity);

        // Город прибытия
        const arrivalCity = document.getElementById('arrival_city')?.value;
        if (arrivalCity) formData.append('arrival_city', arrivalCity);

        // Категория
        const category = document.getElementById('category')?.value;
        if (category) formData.append('category', category);

        // Даты
        const startDate = document.querySelector('input[name="start_date"]')?.value;
        const endDate = document.querySelector('input[name="end_date"]')?.value;
        if (startDate) formData.append('start_date', startDate);
        if (endDate) formData.append('end_date', endDate);

        // Цена
        const minPrice = document.getElementById('min-price-input')?.value;
        const maxPrice = document.getElementById('max-price-input')?.value;
        if (minPrice) formData.append('min_price', minPrice);
        if (maxPrice) formData.append('max_price', maxPrice);

        // Оценка
        const ratingMin = document.getElementById('rating-min')?.value;
        if (ratingMin) formData.append('rating_min', ratingMin);

        // Дополнительные характеристики
        const selectedFeatures = Array.from(document.querySelectorAll('input[name="features[]"]:checked'))
            .map(checkbox => checkbox.value);
        if (selectedFeatures.length > 0) {
            formData.append('features', selectedFeatures.join(','));
        }

        // Преобразуем FormData в строку параметров
        const params = new URLSearchParams(formData).toString();

        // Отправляем запрос через fetch с методом GET
        fetch(`catalog.php?${params}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка при выполнении запроса');
            }
            return response.text();
        })
        .then(html => {
            // Обновляем только блок с турами
            document.querySelector('.tour-container').innerHTML = html;

            // Восстанавливаем состояние фильтров
            restoreFilters(departureCity, arrivalCity, category, startDate, endDate, minPrice, maxPrice, ratingMin, selectedFeatures);

            // Инициализируем слайдеры для нового контента
            initializeSliders();
        })
        .catch(error => console.error('Ошибка:', error));
    }

    // Функция для восстановления состояния фильтров
    function restoreFilters(departureCity, arrivalCity, category, startDate, endDate, minPrice, maxPrice, ratingMin, selectedFeatures) {
        // Город отправления
        const departureCityInput = document.getElementById('departure_city');
        if (departureCityInput) departureCityInput.value = departureCity;

        // Город прибытия
        const arrivalCityInput = document.getElementById('arrival_city');
        if (arrivalCityInput) arrivalCityInput.value = arrivalCity;

        // Категория
        const categoryInput = document.getElementById('category');
        if (categoryInput) categoryInput.value = category;

        // Даты
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        if (startDateInput) startDateInput.value = startDate;
        if (endDateInput) endDateInput.value = endDate;

        // Цена
        const minPriceInput = document.getElementById('min-price-input');
        const maxPriceInput = document.getElementById('max-price-input');
        if (minPriceInput) minPriceInput.value = minPrice;
        if (maxPriceInput) maxPriceInput.value = maxPrice;

        // Оценка
        const ratingMinInput = document.getElementById('rating-min');
        if (ratingMinInput) ratingMinInput.value = ratingMin;

        // Дополнительные характеристики
        document.querySelectorAll('input[name="features[]"]').forEach(checkbox => {
            checkbox.checked = selectedFeatures.includes(checkbox.value);
        });
    }




document.addEventListener("DOMContentLoaded", function () {
    const btnBook = document.querySelector(".btn-book.tour");
    const footer = document.querySelector(".footer"); // Предполагается, что у футера класс .footer
    console.log("Button:", btnBook);
    console.log("Footer:", footer);
    function updateButtonPosition() {
        const footerRect = footer.getBoundingClientRect(); // Получаем положение футера
        const windowHeight = window.innerHeight; // Высота видимой области экрана
        const buttonHeight = btnBook.offsetHeight; // Высота кнопки

        // Если футер начинает перекрывать кнопку
        if (footerRect.top <= windowHeight) {
            const spaceAboveFooter = footerRect.top - buttonHeight - 20; // Оставляем отступ 20px
            btnBook.style.bottom = `${spaceAboveFooter}px`; // Перемещаем кнопку выше футера
        } else {
            btnBook.style.bottom = "20px"; // Возвращаем кнопку вниз экрана
        }
    }

    // Обновляем позицию кнопки при прокрутке и изменении размера окна
    window.addEventListener("scroll", updateButtonPosition);
    window.addEventListener("resize", updateButtonPosition);

    // Инициализация при загрузке страницы
    updateButtonPosition();
});

