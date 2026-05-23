<?php
require_once 'c:/laragon/www/Arsip/config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE users");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
