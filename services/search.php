<?php
// services/search.php
// Retorna JSON com resultados de levels e users com base em ?q=...

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// helper local: gera avatar URL compatível com header.php avatar_url_web
if (!function_exists('avatar_url_web')) {
    function avatar_url_web(string $user_or_path = ''): string {
        $default = 'image/images.jfif';

        if ($user_or_path === '' || $user_or_path === null) {
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
        }

        if (ctype_digit((string)$user_or_path)) {
            $id = intval($user_or_path);
            if ($id <= 0) {
                return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
            }
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/controller/get_avatar.php?id=' . $id;
        }

        if (preg_match('#^https?://#i', $user_or_path)) {
            return $user_or_path;
        }

        $p = ltrim($user_or_path, '/');
        $p = preg_replace('#^(?:Projeto_Final_PHP/)+#i', '', $p);

        return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_FINAL_PHP/' . $p;
    }
}

$q = trim((string)($_GET['q'] ?? ''));

$out = ['results' => []];

if ($q === '') {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = connect_mysql();
if (!$mysqli) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$results = [];

/* 1) Perfis (users) - pesquisa por nome ou email
   Observação: retornamos avatar_url apontando para controller/get_avatar.php?id=USER_ID
*/
$userSql = "SELECT id, nome, email FROM users WHERE nome LIKE ? OR email LIKE ? LIMIT 6";
if ($stmt = $mysqli->prepare($userSql)) {
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
            'avatar_url' => avatar_url_web((string)$row['id'])
        ];
    }
    $stmt->close();
}

/* 2) Levels - pesquisa por title/descricao */
$levelSql = "SELECT id, title, descricao, level_json FROM levels WHERE title LIKE ? OR descricao LIKE ? LIMIT 6";
if ($stmt = $mysqli->prepare($levelSql)) {
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $level_json_raw = $row['level_json'] ?? '';
        if (is_string($level_json_raw)) {
            $lvl_json_for_send = $level_json_raw;
        } else {
            $lvl_json_for_send = json_encode($level_json_raw);
        }
        $results[] = [
            'type' => 'level',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'sub' => mb_strimwidth($row['descricao'] ?? '', 0, 80, '...'),
            // usa avatar fallback para níveis (imagem padrão)
            'avatar_url' => avatar_url_web(''),
            'level_json' => $lvl_json_for_send
        ];
    }
    $stmt->close();
}

$mysqli->close();

$out['results'] = $results;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
