<?php
// controller/controller_new_level.php
require_once __DIR__ . '/../services/config.php';
session_start();

/**
 * Controller simplificado para inserir level usando stored procedure create_level.
 * - Não usa CSRF (por pedido).
 * - Faz logs simples em /controller/../logs/controller_new_level.log para facilitar debug.
 * - Redireciona para ../index.php em caso de sucesso, ou volta para ../view/post.php em caso de erro,
 *   gravando mensagens em $_SESSION['flash_error'].
 */

function controller_log($msg) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . '/controller_new_level.log';
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

controller_log("=== request start ===");
controller_log("REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? ''));
controller_log("_POST=" . json_encode($_POST));
controller_log("_SESSION_id=" . ($_SESSION['id'] ?? 'NULL'));

// only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    controller_log("Not POST -> redirect to index");
    header('Location: ../index.php');
    exit;
}

// must be logged
if (empty($_SESSION['id'])) {
    controller_log("No session id -> redirect to index");
    header('Location: ../index.php');
    exit;
}

// ensure action publish
if (($_POST['action'] ?? '') !== 'publish') {
    $_SESSION['flash_error'] = 'Ação inválida.';
    controller_log("Invalid action");
    header('Location: ../view/post.php');
    exit;
}

// sanitize inputs (minimal)
$title = trim((string)($_POST['title'] ?? ''));
$difficulty = strtolower(trim((string)($_POST['difficulty'] ?? 'easy')));
$allowed = ['easy','medium','hard','insane'];
if (!in_array($difficulty, $allowed, true)) $difficulty = 'easy';

if ($title === '') {
    $_SESSION['flash_error'] = 'Título é obrigatório.';
    controller_log("Missing title");
    header('Location: ../view/post.php');
    exit;
}
if (mb_strlen($title) > 200) {
    $_SESSION['flash_error'] = 'Título muito longo (máx 200 caracteres).';
    controller_log("Title too long");
    header('Location: ../view/post.php');
    exit;
}

// optional level JSON
$level_json = null;
if (!empty($_POST['select_level'])) {
    $level_json = trim((string)$_POST['select_level']);
    $decoded = json_decode($level_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $_SESSION['flash_error'] = 'JSON da fase inválido.';
        controller_log("Invalid JSON");
        header('Location: ../view/post.php');
        exit;
    }
}

// connect
$mysqli = connect_mysql();
if (!$mysqli || $mysqli->connect_errno) {
    $_SESSION['flash_error'] = 'Erro ao conectar ao banco.';
    controller_log("DB connect error: " . ($mysqli->connect_error ?? 'unknown'));
    header('Location: ../view/post.php');
    exit;
}
$mysqli->set_charset('utf8mb4');

// prepare and call
$author_id = (int)$_SESSION['id'];
$p_title = $title;
$p_descricao = '';
$p_difficulty = $difficulty;
$p_level_json = $level_json !== null ? $level_json : null;
$p_published = 1;

$stmt = $mysqli->prepare("CALL create_level(?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    $_SESSION['flash_error'] = 'Erro ao preparar a procedure: ' . $mysqli->error;
    controller_log("Prepare failed: " . $mysqli->error);
    header('Location: ../view/post.php');
    exit;
}

// bind params (i s s s s i) -> 'issssi'
if (!$stmt->bind_param('issssi', $author_id, $p_title, $p_descricao, $p_difficulty, $p_level_json, $p_published)) {
    $_SESSION['flash_error'] = 'Erro ao vincular parâmetros: ' . $stmt->error;
    controller_log("Bind failed: " . $stmt->error);
    $stmt->close();
    header('Location: ../view/post.php');
    exit;
}

if (!$stmt->execute()) {
    $_SESSION['flash_error'] = 'Erro ao executar a procedure: ' . $stmt->error;
    controller_log("Execute failed: " . $stmt->error);
    // flush any results
    while ($mysqli->more_results() && $mysqli->next_result()) {}
    $stmt->close();
    header('Location: ../view/post.php');
    exit;
}

// consume any extra resultsets
while ($mysqli->more_results() && $mysqli->next_result()) {}

// close and success
$stmt->close();
$_SESSION['flash_success'] = 'Fase criada com sucesso.';
controller_log("Success: level created by user $author_id");

header('Location: ../index.php');
exit;
