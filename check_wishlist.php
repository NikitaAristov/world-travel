<?php
session_start();
require_once 'db_connect.php';

// Включаем вывод ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']));
}

$user_id = $_SESSION['user_id'];
$tour_id = intval($_POST['tour_id']);

// Логируем входные данные
error_log("User ID: $user_id, Tour ID: $tour_id");

// Проверяем, существует ли запись в таблице wishlist
$check_query = $conn->query("SELECT * FROM wishlist WHERE wishlist_user_id = $user_id AND wishlist_tour_id = $tour_id");
if ($check_query === false) {
    error_log("Ошибка при выполнении запроса: " . $conn->error);
    die(json_encode(['success' => false, 'message' => 'Ошибка при проверке избранного']));
}

if ($check_query->num_rows > 0) {
    // Если тур уже в избранном, удаляем его
    $delete_query = $conn->query("DELETE FROM wishlist WHERE wishlist_user_id = $user_id AND wishlist_tour_id = $tour_id");
    if ($delete_query) {
        die(json_encode(['success' => true, 'is_in_wishlist' => false]));
    } else {
        error_log("Ошибка при удалении из избранного: " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'Ошибка при удалении из избранного']));
    }
} else {
    // Если тур не в избранном, добавляем его
    $insert_query = $conn->query("INSERT INTO wishlist (wishlist_user_id, wishlist_tour_id, wishlist_date_add) VALUES ($user_id, $tour_id, NOW())");
    if ($insert_query) {
        die(json_encode(['success' => true, 'is_in_wishlist' => true]));
    } else {
        error_log("Ошибка при добавлении в избранное: " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'Ошибка при добавлении в избранное']));
    }
}
$conn->close();
?>