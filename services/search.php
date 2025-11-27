<?php
// services/search.php
// Retorna JSON com resultados de levels e users com base em ?q=...

require_once __DIR__ . '/config.php';

// Helper: mesma função de header para normalizar avatar URL
if (!function_exists('avatar_url_web')) {
    function avatar_url_web(string $path = ''): string {
        $default = 'image/images.jfif';
        if (empty($path)) {
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $p = ltrim($path, '/');
        $p = preg_replace('#^(?:Projeto_Final_PHP/)+#i', '', $p);
        return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $p;
    }
}

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));

// resposta padrão
$out = ['results' => []];

if ($q === '') {
    echo json_encode($out);
    exit;
}

$mysqli = connect_mysql();
if (!$mysqli) {
    echo json_encode($out);
    exit;
}

// buscamos até 5 perfis e 5 levels (separei para controlar limites)
$like = '%' . $q . '%';
$results = [];

// 1) perfis (users) - pesquisa por nome ou email
if ($stmt = $mysqli->prepare("SELECT id, nome, email, avatar_url FROM users WHERE nome LIKE ? OR email LIKE ? LIMIT 6")) {
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'type' => 'user',
            'id' => (int)$row['id'],
            'title' => $row['nome'],
            'nome' => $row['nome'],
            'sub' => $row['email'],
            'avatar_url' => avatar_url_web($row['avatar_url'] ?? '')
        ];
    }
    $stmt->close();
}

// 2) levels - pesquisa por title (também por descricao)
if ($stmt = $mysqli->prepare("SELECT id, title, descricao, level_json FROM levels WHERE title LIKE ? OR descricao LIKE ? LIMIT 6")) {
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // garantimos que level_json é string (se já é string, ok; se é JSON DB, pegamos string)
        $level_json_raw = $row['level_json'] ?? '';
        if (is_string($level_json_raw)) {
            $lvl_json_for_send = $level_json_raw;
        } else {
            // fallback: encode array/object
            $lvl_json_for_send = json_encode($level_json_raw);
        }
        $results[] = [
            'type' => 'level',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'sub' => mb_strimwidth($row['descricao'] ?? '', 0, 80, '...'),
            'avatar_url' => avatar_url_web('image/images.jfif'), // use fallback image for levels
            'level_json' => $lvl_json_for_send
        ];
    }
    $stmt->close();
}

$mysqli->close();

// opcional: ordenar (primeiro levels, depois users) — aqui manter a ordem obtida (users then levels).
$out['results'] = $results;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
