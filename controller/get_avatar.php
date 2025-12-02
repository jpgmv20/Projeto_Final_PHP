<?php
// controller/get_avatar.php
require_once __DIR__ . '/../services/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    // fallback: serve arquivo padrão
    header('Content-Type: image/jpeg');
    readfile(__DIR__ . '/../image/images.jfif');
    exit;
}

$mysqli = connect_mysql();
if (!$mysqli) {
    header('Content-Type: image/jpeg');
    readfile(__DIR__ . '/../image/images.jfif');
    exit;
}

$stmt = $mysqli->prepare("SELECT avatar_image, image_type FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    header('Content-Type: image/jpeg');
    readfile(__DIR__ . '/../image/images.jfif');
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    header('Content-Type: image/jpeg');
    readfile(__DIR__ . '/../image/images.jfif');
    exit;
}

$stmt->bind_result($avatar_blob, $image_type);
$stmt->fetch();
$stmt->close();
$mysqli->close();

if (empty($avatar_blob)) {
    // sem imagem → fallback
    header('Content-Type: image/jpeg');
    readfile(__DIR__ . '/../image/images.jfif');
    exit;
}

// definir content-type (se ausente usa octet-stream)
$contentType = $image_type ?: 'application/octet-stream';
header('Content-Type: ' . $contentType);
// opcionalmente cache
header('Cache-Control: public, max-age=3600');
// envia blob
echo $avatar_blob;
exit;
