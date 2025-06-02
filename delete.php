<?php
session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';

$table = $_GET['table'];
$id = $_GET['id'];
$role = $_SESSION['user_role'];
$redirect = ($role === 'admin') ? 'admin.php' : 'manager.php';

// Ограничения для менеджера
$restricted_tables = ['users', 'payments'];
if ($role === 'manager' && in_array($table, $restricted_tables)) {
    header("Location: index.php");
    exit;
}

// Получаем первичный ключ таблицы
$columnsRes = $conn->query("SHOW COLUMNS FROM `$table`");
$primaryKey = '';

while ($col = $columnsRes->fetch_assoc()) {
    if ($col['Key'] === 'PRI') {
        $primaryKey = $col['Field'];
        break;
    }
}

if (!$primaryKey) {
    die("Ошибка: не найден первичный ключ в таблице $table.");
}

// Удаление записи
$query = "DELETE FROM `$table` WHERE `$primaryKey` = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
   if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: manager.php");
    }
} else {
    echo "Ошибка при удалении: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
