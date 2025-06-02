<?php
session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$table = $_GET['table'];
$id = $_GET['id'];
$role = $_SESSION['user_role'];

// Ограничения для менеджера
$restricted_tables = ['users', 'payments'];
if ($role === 'manager' && in_array($table, $restricted_tables)) {
    header("Location: index.php");
    exit;
}

// Получаем все столбцы таблицы
$columns = [];
$columnsRes = $conn->query("SHOW COLUMNS FROM `$table`");
$primaryKey = '';

while ($col = $columnsRes->fetch_assoc()) {
    $columns[] = $col['Field'];
    if ($col['Key'] === 'PRI') {
        $primaryKey = $col['Field'];
    }
}

if (!$primaryKey) {
    die("Ошибка: не найден первичный ключ в таблице $table.");
}

// Получаем данные для редактирования
$query = "SELECT * FROM `$table` WHERE `$primaryKey` = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Ошибка: запись не найдена.");
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать запись</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .form-container { background: white; border-radius: 10px; padding: 10px; width: 50%; margin: auto; }
        .form-container input { width: 100%; padding: 8px; margin: 5px 0; }
        .form-container button { width: 100%; padding: 8px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .form-container button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>Редактировать запись в таблице <?= htmlspecialchars($table) ?></h2>

    <div class="form-container">
        <form action="update.php" method="POST">
            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
            <input type="hidden" name="redirect" value="<?= ($role === 'admin') ? 'admin.php' : 'manager.php' ?>">

            <?php foreach ($columns as $col): ?>
                <?php if ($col !== $primaryKey): ?>
                    <label for="<?= $col ?>"><?= htmlspecialchars($col) ?></label>
                    <input type="text" name="values[<?= htmlspecialchars($col) ?>]" value="<?= htmlspecialchars($row[$col], ENT_QUOTES, 'UTF-8') ?>" required>
                <?php endif; ?>
            <?php endforeach; ?>

            <button type="submit">Сохранить изменения</button>
        </form>
    </div>
</body>
</html>

<?php
$conn->close();
?>
