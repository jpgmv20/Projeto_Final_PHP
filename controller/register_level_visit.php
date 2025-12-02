<?php
require_once(__DIR__ . '/../services/config.php');
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo "Not logged";
    exit;
}

$user_id  = (int)$_SESSION['id'];
$level_id = (int)($_POST['level_id'] ?? 0);

if ($level_id <= 0) {
    http_response_code(400);
    echo "Invalid level_id";
    exit;
}

$mysqli = connect_mysql();

$sql = "UPDATE levels SET plays_count = plays_count + 1 WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $level_id);
$stmt->execute();
$stmt->close();
$mysqli->close();

echo "OK";
exit;
