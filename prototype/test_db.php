<?php
$host = "localhost";
$port = "5432";
$dbname = "parish_db";
$user = "postgres";
$password = "123456";

try {
    $pdo = new PDO("pgsql:host=$host; port=$port; dbname=$dbname", $user, $password);
    echo "Connected successfully!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
