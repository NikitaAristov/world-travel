<?php
session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';

$table = $_POST['table'];
$values = $_POST['values'];

// Список таблиц, к которым у менеджера нет доступа
$restricted_tables = ['users', 'payments'];

if ($_SESSION['user_role'] === 'manager' && in_array($table, $restricted_tables)) {
    header("Location: index.php");
    exit;
}

// Получаем список столбцов без первичного ключа
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM `$table`");
while ($col = $res->fetch_assoc()) {
    if ($col['Key'] !== 'PRI') {
        $columns[] = $col['Field'];
    }
}

// Проверяем, что количество переданных значений соответствует количеству столбцов
if (count($values) !== count($columns)) {
    die("Ошибка: передано неверное количество данных.");
}

// Экранируем значения и формируем SQL-запрос
$escaped_values = [];
foreach ($values as $val) {
    $escaped_values[] = "'" . $conn->real_escape_string($val) . "'";
}

$query = "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $escaped_values) . ")";

if ($conn->query($query)) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: manager.php");
    }
} else {
    echo "Ошибка при добавлении: " . $conn->error;
}
exit;
?>
