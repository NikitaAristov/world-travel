<?php
require_once 'vendor/autoload.php';
require_once 'db_connect.php';

use YooKassa\Client;

$shopId = '1076262';
$secretKey = 'test_FtTauE3OdWtEgj_c4kSr1--_jcPVcfg6htDvpyTInQw';
$client = new Client();
$client->setAuth($shopId, $secretKey); // Инициализация клиента ЮКасса

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die('Метод не поддерживается.');
}

// Получаем данные из POST-запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Логируем входящие данные для отладки
file_put_contents('debug.log', print_r($data, true));

if (!isset($data['event'])) {
    http_response_code(400); // Bad Request
    die('Некорректные данные.');
}

if ($data['event'] === 'payment.succeeded') {
    $payment_id = $data['object']['id'];
    $status = $data['object']['status'];

    // Обновляем статус платежа в базе данных
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_status = ?
        WHERE yookassa_payment_id = ?
    ");
    if (!$stmt) {
        http_response_code(500); // Internal Server Error
        die('Ошибка подготовки запроса: ' . $conn->error);
    }
    $stmt->bind_param('ss', $status, $payment_id);
    if (!$stmt->execute()) {
        http_response_code(500); // Internal Server Error
        die('Ошибка выполнения запроса: ' . $stmt->error);
    }

    // Если платеж успешен, обновляем статус бронирования
    if ($status === 'succeeded') {
        $update_stmt = $conn->prepare("
            UPDATE bookings 
            SET booking_status = 'confirmed' 
            WHERE booking_payment_id = ?
        ");
        if (!$update_stmt) {
            http_response_code(500); // Internal Server Error
            die('Ошибка подготовки запроса: ' . $conn->error);
        }
        $update_stmt->bind_param('s', $payment_id);
        if (!$update_stmt->execute()) {
            http_response_code(500); // Internal Server Error
            die('Ошибка выполнения запроса: ' . $update_stmt->error);
        }
    }
} elseif ($data['event'] === 'payment.canceled') {
    $payment_id = $data['object']['id'];
    $status = $data['object']['status'];

    // Обновляем статус платежа в базе данных
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_status = ?
        WHERE yookassa_payment_id = ?
    ");
    if (!$stmt) {
        http_response_code(500); // Internal Server Error
        die('Ошибка подготовки запроса: ' . $conn->error);
    }
    $stmt->bind_param('ss', $status, $payment_id);
    if (!$stmt->execute()) {
        http_response_code(500); // Internal Server Error
        die('Ошибка выполнения запроса: ' . $stmt->error);
    }

    // Если платеж отменен, обновляем статус бронирования
    $update_stmt = $conn->prepare("
        UPDATE bookings 
        SET booking_status = 'invalid' 
        WHERE booking_payment_id = ?
    ");
    if (!$update_stmt) {
        http_response_code(500); // Internal Server Error
        die('Ошибка подготовки запроса: ' . $conn->error);
    }
    $update_stmt->bind_param('s', $payment_id);
    if (!$update_stmt->execute()) {
        http_response_code(500); // Internal Server Error
        die('Ошибка выполнения запроса: ' . $update_stmt->error);
    }
}

http_response_code(200); // Отправляем ответ ЮКассе
exit;
?>