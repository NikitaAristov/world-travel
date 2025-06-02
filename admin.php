<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

include 'db_connect.php';
header('Content-Type: text/html; charset=UTF-8');

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .table-container { background: white; border-radius: 10px; padding: 10px; margin-bottom: 15px; }
        .toggle-btn { padding: 10px; background: #007bff; color: white; border-radius: 6px; text-align: center; cursor: pointer; }
        .toggle-btn:hover { background: #0056b3; }
        .table-content { display: none; padding: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #007bff; color: white; }
        .delete-btn, .edit-btn { padding: 6px 12px; color: white; border-radius: 6px; text-decoration: none; }
        .delete-btn { background: #dc3545; }
        .edit-btn { background: #ffc107; }
        .delete-btn:hover { background: #c82333; }
        .edit-btn:hover { background: #e0a800; }
        .add-form { margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 6px; }
        .add-form input, .add-form button { padding: 8px; margin: 5px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <h2>Админ-панель</h2>
    <p>Добро пожаловать, администратор!</p>

    <h3>Таблицы</h3>
    <?php foreach ($tables as $index => $table): ?>
        <div class="table-container">
            <script>
                function toggleTable(id) {
                    let content = document.getElementById(id);
                    content.style.display = content.style.display === 'none' ? 'block' : 'none';
                }
            </script>
            <p class="toggle-btn" onclick="toggleTable('table_<?= $index ?>')">▶ <?= htmlspecialchars($table) ?></p>
            <div id="table_<?= $index ?>" class="table-content">
                <table border="1">
                    <tr>
                        <?php
                        // Получаем столбцы и первичный ключ
                        $columns = [];
                        $primaryKey = '';
                        $res = $conn->query("SHOW COLUMNS FROM $table");
                        while ($col = $res->fetch_assoc()) {
                            $columns[] = $col['Field'];
                            if ($col['Key'] === 'PRI') {
                                $primaryKey = $col['Field'];
                            }
                            echo "<th>" . htmlspecialchars($col['Field']) . "</th>";
                        }
                        ?>
                        <th>Действие</th>
                    </tr>
                    <?php
                    $res = $conn->query("SELECT * FROM $table");
                    while ($row = $res->fetch_assoc()) {
                        echo "<tr>";
                        foreach ($columns as $col) {
                            echo "<td>" . htmlspecialchars($row[$col], ENT_QUOTES, 'UTF-8') . "</td>";
                        }
                        echo "<td>
                                <a href='edit.php?table=$table&id={$row[$primaryKey]}' class='edit-btn'>Редактировать</a>
                                <a href='delete.php?table=$table&id={$row[$primaryKey]}&pk=$primaryKey' class='delete-btn'>Удалить</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </table>

                <!-- Форма для добавления записи (без первичного ключа) -->
                <form class="add-form" action="insert.php" method="POST">
                    <input type="hidden" name="table" value="<?= $table ?>">
                    <?php foreach ($columns as $col): ?>
                        <?php if ($col !== $primaryKey): ?>
                            <input type="text" name="values[]" placeholder="<?= htmlspecialchars($col) ?>" required>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button type="submit">Добавить запись</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <p><a href="logout.php">Выйти</a></p>
</body>
</html>
<?php
$conn->close();
?>
