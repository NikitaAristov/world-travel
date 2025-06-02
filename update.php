<?php
session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';

$table = $_POST['table'];
$id = $_POST['id'];
$values = $_POST['values'];
$redirect = $_POST['redirect'];
$role = $_SESSION['user_role'];

// Ограничения для менеджера
$restricted_tables = ['users', 'payments'];
if ($role === 'manager' && in_array($table, $restricted_tables)) {
    header("Location: index.php");
    exit;
}

// Получаем первичный ключ таблицы
$columnsRes = $conn->query("SHOW COLUMNS FROM `$table`");
$primaryKey = '';
$columns = [];

while ($col = $columnsRes->fetch_assoc()) {
    if ($col['Key'] === 'PRI') {
        $primaryKey = $col['Field'];
    } else {
        $columns[] = $col['Field'];
    }
}

if (!$primaryKey) {
    die("Ошибка: не найден первичный ключ в таблице $table.");
}

// Формируем SQL-запрос
$updateFields = [];
$params = [];
$types = '';

foreach ($columns as $col) {
    if (isset($values[$col])) {
        $updateFields[] = "`$col` = ?";
        $params[] = $values[$col];
        $types .= 's';
    }
}

$params[] = $id;
$types .= 'i';

$query = "UPDATE `$table` SET " . implode(", ", $updateFields) . " WHERE `$primaryKey` = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: manager.php");
    }
} else {
    echo "Ошибка при обновлении: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
