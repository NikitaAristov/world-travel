<?php
$servername = "localhost";
$username = "root"; // Измени на нужного пользователя
$password = "root"; // Измени на пароль
$dbname = "travel"; // Укажи имя своей БД

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
?>
