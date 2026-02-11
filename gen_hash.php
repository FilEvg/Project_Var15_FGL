<?php
// generate_hash.php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Пароль: " . $password . "<br>";
echo "Хеш: " . $hash . "<br>";

// Проверка
if (password_verify($password, $hash)) {
    echo "Хеш правильный!";
} else {
    echo "Хеш неверный!";
}
?>