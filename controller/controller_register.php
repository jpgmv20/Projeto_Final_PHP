<?php

session_start();

require_once(__DIR__ . "/../services/config.php");

$username = trim($_POST["username"] ?? '');
$email = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';
$passwordConfirm = $_POST["passwordConfirm"] ?? '';

$mysqli = connect_mysql();

/* ========== validações ========= */

if ($username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
    $_SESSION['error'] = "Preencha todos os campos.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "E-mail inválido.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

if ($password !== $passwordConfirm) {
    $_SESSION['error'] = "As senhas não coincidem.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

/* ========== senha ========= */

$password_hash = password_hash($password, PASSWORD_DEFAULT);

/* ========== verifica email duplicado ========= */

$sql = "SELECT id FROM users WHERE email = ? LIMIT 1";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = "E-mail já cadastrado.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

$stmt->close();

/* ========== avatar upload ========= */

$uploadsDir = __DIR__ . '/../image/';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$avatar_url = 'Projeto_Final_PHP/image/images.jfif';

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

    $file = $_FILES["avatar"];
    $targetPath = $uploadsDir . $email . "_" .basename($file["name"]);

    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        $avatar_url = 'Projeto_Final_PHP/image/' . $email . "_" . $file["name"];
    }

}

/* ========== config JSON ========= */

$config_json = json_encode([
    "tema" => "theme-dark",
    "sons" => true
]);

/* ========== cria usuário ========= */
create_user(
    $username,
    $email,
    $password_hash,
    $avatar_url,
    $config_json
);

header('Location: /Projeto_Final_PHP/view/login.php');
exit;
