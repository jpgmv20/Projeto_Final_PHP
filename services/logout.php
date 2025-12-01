<?php
// services/logout.php  (ou o nome que você usar)

require_once __DIR__ . '/config.php';

session_start();

$user_id = $_SESSION['id'] ?? null;

if ($user_id) {
    $mysqli = connect_mysql();
    if ($mysqli) {
        // prepara e executa remoção de tokens (se existir)
        $stmt = $mysqli->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        $mysqli->close();
    }
}

// limpa variáveis de sessão
$_SESSION = [];

// remove cookie de sessão (se usado)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// destrói a sessão no servidor
session_destroy();

// remove remember_me cookie (use os mesmos parâmetros que criou o cookie originalmente)
setcookie("remember_me", "", time() - 3600, "/", "", true, true);

// redireciona para a página de login (use Location: e URL absoluto ou relativo)
$loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/login.php';
header('Location: ' . $loginUrl);
exit;
