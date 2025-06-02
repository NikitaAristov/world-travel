<?php
session_start();
include 'db_connect.php'; // Подключение к базе данных
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST["full_name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $phone = $_POST["phone"];

    // Проверка ФИО
    $name_parts = explode(" ", $full_name);
    if (count($name_parts) < 2) {
        $error = "Ошибка: Введите хотя бы фамилию и имя!";
    } else {
        $family = $name_parts[0];
        $name = $name_parts[1];
        $lastname = isset($name_parts[2]) ? $name_parts[2] : null; // NULL, если отчества нет

        // Проверка формата ФИО (только русские буквы)
        // Проверки
        if (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $family)) {
            $error = "Ошибка: Фамилия для человека $i должна начинаться с заглавной буквы и содержать только русские буквы.";
        }
        elseif (!preg_match('/^[А-ЯЁ][а-яё]*$/u', $name)) {
            $error = "Ошибка: Имя для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
        }
        elseif ($lastname && !preg_match('/^[А-ЯЁ][а-яё]*$/u', $lastname)) {
            $error = "Ошибка: Отчество для человека $i должно начинаться с заглавной буквы и содержать только русские буквы.";
        }
        
    }

    // Проверка пароля
    if (
        !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_])(?=.{6,})(?!.*\s).*$/', $password))
    {
        $error = "Ошибка: Пароль должен содержать минимум 6 символов, включая заглавные и строчные английские буквы, хотя бы один специальный символ, и не должен содержать пробелы.";
    }

    // Корректировка номера телефона
    if (preg_match('/^8\d{10}$/', $phone)) {
        // Если номер начинается с 8, заменяем 8 на +7
        $phone = '+7' . substr($phone, 1);
    } elseif (preg_match('/^9\d{9}$/', $phone)) {
        // Если номер начинается с 9, добавляем +7 в начало
        $phone = '+7' . $phone;
    }

    // Проверка формата номера телефона после корректировки
    if (!preg_match('/^\+7\d{10}$/', $phone)) {
        $error = "Ошибка: Номер телефона должен быть в формате +7XXXXXXXXXX (12 символов).";
    }

    // Если ошибок нет, продолжаем выполнение
    if (!isset($error)) {
        // Проверка уникальности email и телефона
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email = ? OR user_phonenumber = ?");
        if (!$stmt) {
            $error = "Ошибка запроса (проверка email и телефона): " . $conn->error;
        } else {
            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Ошибка: Такой email или номер телефона уже зарегистрированы.";
            } else {
                // Хешируем пароль
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Добавляем пользователя в базу данных
                $stmt = $conn->prepare("
                    INSERT INTO users (
                        user_family, 
                        user_name, 
                        user_lastname, 
                        user_email, 
                        user_password, 
                        user_phonenumber, 
                        user_role
                    ) VALUES (?, ?, ?, ?, ?, ?, 'client')
                ");
                if (!$stmt) {
                    $error = "Ошибка подготовки запроса: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssss", $family, $name, $lastname, $email, $hashed_password, $phone);

                    if ($stmt->execute()) {
                        $success = "Регистрация успешна! Теперь вы можете <a href='login.php'>войти</a>.";
                        header("Location: account.php");
                        exit; // Завершаем выполнение скрипта после перенаправления
                    } else {
                        $error = "Ошибка регистрации: " . $stmt->error;
                    }
                }
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <title>Регистрация | World Travel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <section class="main login">
        <div class="login-container register-page ">
            <div class="login-box register-page">
                <h2>Регистрация клиента</h2>
                <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
                <?php if (isset($success)) echo "<p style='color: green;'>$success</p>"; ?>
                <form method="post" class="reg-form">
                    <label>ФИО:
                        <input type="text" name="full_name" required placeholder="Фамилия Имя Отчество">
                    </label>

                    <label>Email:
                        <input type="email" name="email" required>
                    </label>
                    
                    <label>Телефон:
                        <input type="text" name="phone" required placeholder="+79991234567">
                    </label>
                    
                    <label>Пароль:
                        <input type="password" name="password" required>
                    </label>
                    
                    <button type="submit">Зарегистрироваться</button>
                </form>

                <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
            </div>
        </div>
    </section>
    

    <?php include 'footer.php'; ?>
</body>
</html>

