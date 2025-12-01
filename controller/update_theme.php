<?php
// controller/update_theme.php
// Salva a preferência de tema do usuário no campo `config` (JSON) da tabela users.
// Requer: services/config.php -> connect_mysql()

require_once __DIR__ . '/../services/config.php';
session_start();

// responder JSON sempre
header('Content-Type: application/json; charset=utf-8');

// verifica login
if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

// pega valor enviado
$themeRaw = isset($_POST['theme']) ? (string)$_POST['theme'] : '';

// normaliza / valida (aceitamos 'theme-dark' ou 'dark' -> armazenamos 'theme-dark'; qualquer outro => '')
$themeRaw = trim($themeRaw);
$storeTheme = '';
if ($themeRaw === 'theme-dark' || $themeRaw === 'dark') {
    $storeTheme = 'theme-dark';
} else {
    // aceitar também '' (modo claro) ou qualquer valor estranho -> claro (string vazia)
    $storeTheme = '';
}

// limite de tamanho por segurança
if (strlen($storeTheme) > 64) {
    $storeTheme = '';
}

$mysqli = connect_mysql();
if (!$mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connection_failed']);
    exit;
}

// Prepara e executa: atualiza a chave $.tema dentro do JSON config, criando o objeto se necessário
// Uso: JSON_SET(COALESCE(config, JSON_OBJECT()), '$.tema', ?)
$stmt = $mysqli->prepare("UPDATE users SET config = JSON_SET(COALESCE(config, JSON_OBJECT()), '$.tema', ?) WHERE id = ?");
if (!$stmt) {
    // erro prepare
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'prepare_failed', 'db_error' => $mysqli->error]);
    $mysqli->close();
    exit;
}

$userId = (int)$_SESSION['id'];
$stmt->bind_param('si', $storeTheme, $userId);

$execOk = $stmt->execute();
if (!$execOk) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'execute_failed', 'db_error' => $stmt->error]);
    $stmt->close();
    $mysqli->close();
    exit;
}

// atualiza sessão localmente também (mantém o restante das preferências)
if (!isset($_SESSION['config']) || !is_array($_SESSION['config'])) {
    // tenta decodificar do banco se quiser: mas aqui apenas atualiza array local
    $_SESSION['config'] = [];
}
$_SESSION['config']['tema'] = $storeTheme;

// sucesso
echo json_encode(['ok' => true, 'theme' => $storeTheme]);

$stmt->close();
$mysqli->close();
exit;
