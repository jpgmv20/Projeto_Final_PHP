<?php
require_once("../services/config.php");

// Inicia sessão primeiro
session_start();

// Recupera input
$email_input = trim($_POST["email"] ?? '');
$senha_input = $_POST["password"] ?? '';

// Conecta ao MySQL
$mysqli = connect_mysql();

if (!$mysqli) {
    error_log("connect_mysql() falhou");
    header("Location: ../view/login.php");
    exit;
}

// Valida campos
if (empty($email_input) || empty($senha_input)) {
    $_SESSION['error'] = "Preencha email e senha.";
    header("Location: ../view/login.php");
    exit;
}

$sql = "SELECT id, nome, descricao, email, password, avatar_url, config FROM users WHERE email = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error);
    header("Location: ../view/login.php");
    exit;
}

// vincula o parâmetro (email) e executa
$stmt->bind_param("s", $email_input);

if (!$stmt->execute()) {
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    $stmt->close();
    header("Location: ../view/login.php");
    exit;
}

// IMPORTANT: store_result() para evitar "commands out of sync" e permitir novas queries
$stmt->store_result();

// Se não houver linha, retorna
if ($stmt->num_rows === 0) {
    $stmt->close();
    $mysqli->close();
    $_SESSION['error'] = "Email ou senha inválidos.";
    header("Location: ../view/login.php");
    exit;
}

// vincula resultados a variáveis separadas
$stmt->bind_result($db_id, $db_nome, $db_descricao, $db_email, $db_password_hash, $db_avatar_url, $db_config);

// busca (fetch) a linha
$stmt->fetch();

// verifica a senha
if (!password_verify($senha_input, $db_password_hash)) {
    // senha incorreta
    $stmt->close();
    $mysqli->close();
    $_SESSION['error'] = "Email ou senha inválidos.";
    header("Location: ../view/login.php");
    exit;
}

// Lógica remember-me (salva hash no DB, cookie com token cru)
if (isset($_POST['remember'])) {
    // gera token seguro (valor que irá no cookie)
    $token = bin2hex(random_bytes(32));  // 64 chars
    $token_hash = hash('sha256', $token);

    // expira em 30 dias
    $expires = date("Y-m-d H:i:s", time() + 60*60*24*30);

    // garante que tabela user_tokens exista ou trate o erro
    $stmt2 = $mysqli->prepare("INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    if ($stmt2) {
        $stmt2->bind_param("iss", $db_id, $token_hash, $expires);
        if (!$stmt2->execute()) {
            error_log("Failed to insert user token: (" . $stmt2->errno . ") " . $stmt2->error);
            // não falha o login apenas por erro no remember-me
        }
        $stmt2->close();

        // cria o cookie com user_id:token (token cru) — server guarda apenas hash
        // marque Secure=true em produção (apenas HTTPS). HttpOnly=true evita JS ler o cookie.
        $cookie_value = $db_id . ":" . $token;
        setcookie("remember_me", $cookie_value, time() + 60*60*24*30, "/", "", false, true);
    } else {
        error_log("Prepare failed for insert token: (" . $mysqli->errno . ") " . $mysqli->error);
    }
}

// senha correta -> salva dados na sessão
$_SESSION['id'] = $db_id;
$_SESSION['nome'] = $db_nome;
$_SESSION['descricao'] = $db_descricao;
$_SESSION['email'] = $db_email;
$_SESSION['avatar_url'] = $db_avatar_url;
$_SESSION['config'] = json_decode($db_config, true);

// limpa e fecha
$stmt->close();
$mysqli->close();

// redireciona para área logada
header("Location: ../index.php");
exit;
