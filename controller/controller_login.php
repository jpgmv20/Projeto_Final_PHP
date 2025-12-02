<?php
// controller_login.php
require_once(__DIR__ . "/../services/config.php");
session_start();

$email_input = trim($_POST["email"] ?? '');
$senha_input = $_POST["password"] ?? '';

$mysqli = connect_mysql();
if (!$mysqli) {
    error_log("connect_mysql() falhou");
    header("Location: ../view/login.php");
    exit;
}

if (empty($email_input) || empty($senha_input)) {
    $_SESSION['error'] = "Preencha email e senha.";
    header("Location: ../view/login.php");
    exit;
}

// Buscamos o usuário (não trazemos o BLOB do avatar aqui)
$sql = "SELECT id, nome, descricao, email, password, image_type, config FROM users WHERE email = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error);
    header("Location: ../view/login.php");
    exit;
}
$stmt->bind_param("s", $email_input);
if (!$stmt->execute()) {
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    $stmt->close();
    header("Location: ../view/login.php");
    exit;
}
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    $mysqli->close();
    $_SESSION['error'] = "Email ou senha inválidos.";
    header("Location: ../view/login.php");
    exit;
}

// bind result
$stmt->bind_result($db_id, $db_nome, $db_descricao, $db_email, $db_password_hash, $db_image_type, $db_config);
$stmt->fetch();

// verifica a senha
if (!password_verify($senha_input, $db_password_hash)) {
    $stmt->close();
    $mysqli->close();
    $_SESSION['error'] = "Email ou senha inválidos.";
    header("Location: ../view/login.php");
    exit;
}

// remember-me
if (isset($_POST['remember'])) {
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires = date("Y-m-d H:i:s", time() + 60*60*24*30);
    $stmt2 = $mysqli->prepare("INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    if ($stmt2) {
        $stmt2->bind_param("iss", $db_id, $token_hash, $expires);
        if (!$stmt2->execute()) {
            error_log("Failed to insert user token: (" . $stmt2->errno . ") " . $stmt2->error);
        }
        $stmt2->close();
        $cookie_value = $db_id . ":" . $token;
        setcookie("remember_me", $cookie_value, time() + 60*60*24*30, "/", "", false, true);
    } else {
        error_log("Prepare failed for insert token: (" . $mysqli->errno . ") " . $mysqli->error);
    }
}

// salva dados na sessão
$_SESSION['id'] = $db_id;
$_SESSION['nome'] = $db_nome;
$_SESSION['descricao'] = $db_descricao;
$_SESSION['email'] = $db_email;
// guardamos o ID do usuário — sua avatar_url_web() trata ID e monta get_avatar.php?id=...
$_SESSION['avatar_url'] = (string)$db_id;
$_SESSION['config'] = $db_config ? json_decode($db_config, true) : [];

// limpa e fecha
$stmt->close();
$mysqli->close();

header("Location: ../index.php");
exit;
