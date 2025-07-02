<?php
require 'includes/config.php';

$username = 'admin';
$email = 'admin@example.com';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

$pdo->prepare("INSERT INTO users (...) VALUES (?, ?, ?, TRUE)")
    ->execute([$username, $email, $hash]);

echo "Admin created!\n";
