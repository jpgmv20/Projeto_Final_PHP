<?php
// controller_register.php
session_start();
require_once(__DIR__ . "/../services/config.php");

$username = trim($_POST["username"] ?? '');
$email = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';
$passwordConfirm = $_POST["passwordConfirm"] ?? '';

$mysqli = connect_mysql();
if (!$mysqli) {
    $_SESSION['error'] = "Erro interno: falha na conexão.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

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
if (!$stmt) {
    $_SESSION['error'] = "Erro interno (prepare).";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['error'] = "E-mail já cadastrado.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}
$stmt->close();

/* ========== avatar upload (lê conteúdo e MIME) ========= */
$avatar_blob = null;
$avatar_mime = null;

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['avatar'];
    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $avatar_blob = file_get_contents($file['tmp_name']);
        // detectar mime (recomendado)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $avatar_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        // limitar tipo aceitável (opcional)
        if (!in_array($avatar_mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
            // rejeitar tipos perigosos
            $_SESSION['error'] = "Tipo de imagem não suportado. Use jpg/png/webp/gif.";
            header('Location: /Projeto_Final_PHP/view/register.php');
            exit;
        }
    }
}

/* ========== config JSON ========= */
$config_json = json_encode([
    "tema" => "theme-dark",
    "sons" => true
]);

/* ========== insere usuário com BLOB =========
   Usamos mysqli_stmt::send_long_data para enviar o BLOB corretamente.
*/
$descricao = ''; // conforme pedido: descrição vazia no register

$sql = "INSERT INTO users (nome, descricao, email, password, image_type, avatar_image, config)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    error_log("Prepare insert user failed: {$mysqli->errno} {$mysqli->error}");
    $_SESSION['error'] = "Erro interno ao criar usuário.";
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

// types: s s s s s b s  -> nome, descricao, email, password, image_type, avatar_image (b), config
// NOTE: bind_param requires variables (by reference)
$image_type_var = $avatar_mime ?? '';     // string (can be empty)
$avatar_blob_var = null;                  // placeholder; we'll send actual binary with send_long_data
$stmt->bind_param("sssssbs", $username, $descricao, $email, $password_hash, $image_type_var, $avatar_blob_var, $config_json);

// se há blob, envia-o (param index 5 = 0-based)
if ($avatar_blob !== null) {
    $stmt->send_long_data(5, $avatar_blob);
}

$ok = $stmt->execute();
if (!$ok) {
    error_log("Insert user failed: ({$stmt->errno}) {$stmt->error}");
    $_SESSION['error'] = "Erro ao salvar usuário.";
    $stmt->close();
    header('Location: /Projeto_Final_PHP/view/register.php');
    exit;
}

$stmt->close();
$mysqli->close();

/* sucesso */
$_SESSION['success'] = "Conta criada com sucesso. Faça login.";
header('Location: /Projeto_Final_PHP/view/login.php');
exit;
