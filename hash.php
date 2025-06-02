<?php
$adminPassword = "manager"; // Замени на нужный пароль
$hash = password_hash($adminPassword, PASSWORD_DEFAULT);
echo "Хеш пароля: " . $hash;
?>