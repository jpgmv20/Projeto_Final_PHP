<?php
// view/hub.php (adaptado para BLOB avatars via controller/get_avatar.php)
require_once __DIR__ . '/../services/config.php';
session_start();
/* ---------- redireciona se não estiver logado (proteção contra acesso por URL) ----------
   no seu código original o redirect estava comentado; deixei comentado também para testes
*/
if (empty($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

/* ---------------- avatar helper (usando endpoint que entrega BLOB) ---------------- */
if (!function_exists('avatar_url_web')) {
    function avatar_url_web(string $user_id = ''): string {
        $default = 'image/images.jfif';
        if (empty($user_id)) {
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
        }
        return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/controller/get_avatar.php?id=' . intval($user_id);
    }
}

/* ---------------- mysqli CONNECTION (usa connect_mysql()) ---------------- */
$mysqli = connect_mysql();
if (!$mysqli || $mysqli->connect_errno) {
    die("Erro ao conectar ao banco de dados: " . ($mysqli->connect_error ?? 'unknown'));
}
$mysqli->set_charset('utf8mb4');

/* ---------------- CREATE TABLES (idempotente) ---------------- */
$mysqli->query("
CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('private','group') NOT NULL DEFAULT 'private',
  title VARCHAR(255) DEFAULT NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$mysqli->query("
CREATE TABLE IF NOT EXISTS conversation_participants (
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  INDEX idx_conv_part_user (user_id),
  CONSTRAINT fk_conv_part_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_part_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$mysqli->query("
CREATE TABLE IF NOT EXISTS conversation_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  content TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_by JSON NULL,
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ---------------- helpers ---------------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------------- current user (usando sessão existente) ---------------- */
$currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$currentUserName = $_SESSION['nome'] ?? 'Usuário';
$currentUserAvatar = avatar_url_web($_SESSION['id'] ?? '');

/* ---------------- get_user_suggestions ---------------- */
function get_user_suggestions(mysqli $mysqli, ?string $query, int $limit = 10, int $exclude_user_id = 0) {
    $out = [];
    if ($query === null) return $out;
    $q = trim($query);
    if ($q === '') return $out;
    $like = '%' . $q . '%';
    $stmt = $mysqli->prepare("SELECT id, nome, email FROM users WHERE (nome LIKE ? OR email LIKE ?) AND id != ? ORDER BY nome LIMIT ?");
    if (!$stmt) return $out;
    $stmt->bind_param('ssis', $like, $like, $exclude_user_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

/* ---------------- start conversation helper ---------------- */
if (isset($_GET['start_conversation_with'])) {
    $otherId = (int)$_GET['start_conversation_with'];
    if ($otherId && $otherId !== $currentUserId) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param('i', $otherId);
        $stmt->execute();
        $res = $stmt->get_result();
        $other = $res->fetch_assoc();
        $stmt->close();
        if ($other) {
            $q = $mysqli->prepare("
                SELECT c.id
                FROM conversations c
                JOIN conversation_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
                JOIN conversation_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
                WHERE c.type = 'private'
                LIMIT 1
            ");
            $q->bind_param('ii', $currentUserId, $otherId);
            $q->execute();
            $res2 = $q->get_result();
            $found = $res2->fetch_row();
            $q->close();
            if ($found && isset($found[0])) {
                header("Location: ?conversation_id=" . (int)$found[0]);
                exit;
            } else {
                $mysqli->begin_transaction();
                $mysqli->query("INSERT INTO conversations (type, title) VALUES ('private', NULL)");
                $convId = (int)$mysqli->insert_id;
                $ins = $mysqli->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                $ins->bind_param('ii', $convId, $userIdBind);
                $userIdBind = $currentUserId; $ins->execute();
                $userIdBind = $otherId; $ins->execute();
                $ins->close();
                $mysqli->commit();
                header("Location: ?conversation_id=" . $convId);
                exit;
            }
        }
    }
}

/* ---------------- HANDLE SEND MESSAGE (APENAS TEXTO) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if ($content === '') {
        $_SESSION['flash_error'] = "Escreva uma mensagem.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $mysqli->begin_transaction();

    $initRead = json_encode([$currentUserId], JSON_UNESCAPED_UNICODE);

    $stmt = $mysqli->prepare("INSERT INTO conversation_messages (conversation_id, sender_id, content, read_by) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $mysqli->rollback();
        $_SESSION['flash_error'] = "Erro ao preparar query de envio: " . $mysqli->error;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    $stmt->bind_param('iiss', $convId, $currentUserId, $content, $initRead);
    if (!$stmt->execute()) {
        $stmt->close();
        $mysqli->rollback();
        $_SESSION['flash_error'] = "Erro ao executar envio: " . $stmt->error;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    $stmt->close();

    $mysqli->query("UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = " . (int)$convId);
    $mysqli->commit();

    $_SESSION['flash_success'] = "Mensagem enviada.";
    header("Location: " . preg_replace('/#.*/','', $_SERVER['REQUEST_URI']) . "#conv-$convId");
    exit;
}

/* ---------------- AJAX endpoints ---------------- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajax = $_GET['ajax'];

    if ($ajax === 'conversations') {
        $sql = "
        SELECT
          c.id AS convo_id,
          c.title,
          m.content AS last_content,
          m.created_at AS last_created_at,
          c.last_message_at
        FROM conversations c
        LEFT JOIN conversation_messages m
          ON m.id = (
            SELECT id
            FROM conversation_messages
            WHERE conversation_id = c.id
            ORDER BY created_at DESC
            LIMIT 1
          )
        WHERE EXISTS (SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id = c.id AND cp.user_id = ?)
        ORDER BY COALESCE(c.last_message_at, m.created_at) DESC
        ";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        $convs = [];
        while ($r = $res->fetch_assoc()) $convs[] = $r;
        $stmt->close();

        $out = [];
        foreach ($convs as $c) {
            $unread = (int)count_unread($mysqli, $c['convo_id'], $currentUserId);
            $members = get_convo_members($mysqli, $c['convo_id']);
            $title = $c['title'] ?: (count($members) ? $members[0]['nome'] : 'Chat');

            $avatar = '';
            if (count($members)) {
                foreach ($members as $memb) {
                    if ((int)$memb['id'] !== $currentUserId) {
                        $avatar = avatar_url_web($memb['id'] ?? '');
                        break;
                    }
                }
                if ($avatar === '') $avatar = avatar_url_web($members[0]['id'] ?? '');
            } else {
                $avatar = avatar_url_web('');
            }

            $out[] = [
                'convo_id' => (int)$c['convo_id'],
                'title' => $title,
                'preview' => $c['last_content'] ? mb_strimwidth($c['last_content'], 0, 60, '...') : 'Sem mensagens ainda',
                'time' => $c['last_created_at'] ?? $c['last_message_at'],
                'unread' => $unread,
                'avatar' => $avatar,
            ];
        }

        echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ajax === 'messages') {
        $convId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
        if (!$convId) {
            echo json_encode(['ok' => false, 'error' => 'conversation_id missing']);
            exit;
        }

        $sel = $mysqli->prepare("SELECT id, read_by, sender_id FROM conversation_messages WHERE conversation_id = ? AND sender_id != ?");
        $sel->bind_param('ii', $convId, $currentUserId);
        $sel->execute();
        $rsel = $sel->get_result();
        while ($row = $rsel->fetch_assoc()) {
            $mid = (int)$row['id'];
            $rb = $row['read_by'];
            $arr = [];
            if ($rb !== null && $rb !== '') {
                $decoded = json_decode($rb, true);
                if (is_array($decoded)) $arr = $decoded;
            }
            if (!in_array($currentUserId, $arr, true)) {
                $arr[] = $currentUserId;
                $jsonStr = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
                $up = $mysqli->prepare("UPDATE conversation_messages SET read_by = ? WHERE id = ?");
                if ($up) {
                    $up->bind_param('si', $jsonStr, $mid);
                    $up->execute();
                    $up->close();
                }
            }
        }
        $sel->close();

        // retorna mensagens com avatar baseado em sender_id
        $mstmt = $mysqli->prepare("SELECT cm.*, u.nome AS sender_name FROM conversation_messages cm LEFT JOIN users u ON u.id = cm.sender_id WHERE cm.conversation_id = ? ORDER BY cm.created_at ASC");
        $mstmt->bind_param('i', $convId);
        $mstmt->execute();
        $mres = $mstmt->get_result();
        $msgs = [];
        while ($m = $mres->fetch_assoc()) {
            $msgs[] = [
                'id' => (int)$m['id'],
                'sender_id' => (int)$m['sender_id'],
                'sender_name' => $m['sender_name'],
                // use sender_id to build avatar URL
                'sender_avatar' => avatar_url_web($m['sender_id'] ?? ''),
                'content' => $m['content'],
                'created_at' => $m['created_at'],
            ];
        }
        $mstmt->close();

        echo json_encode(['ok' => true, 'messages' => $msgs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ajax === 'all_users') {
        $q = $mysqli->prepare("SELECT id, nome, email FROM users WHERE id != ? ORDER BY nome");
        $q->bind_param('i', $currentUserId);
        $q->execute();
        $r = $q->get_result();
        $users = [];
        while ($row = $r->fetch_assoc()) {
            $users[] = [
                'id' => (int)$row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'avatar' => avatar_url_web($row['id'] ?? '')
            ];
        }
        $q->close();
        echo json_encode(['ok' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown ajax']);
    exit;
}

/* ---------------- count_unread & get_convo_members ---------------- */
function count_unread(mysqli $mysqli, $conversation_id, $user_id) {
    $sql = "SELECT id, read_by, sender_id FROM conversation_messages WHERE conversation_id = ? AND sender_id != ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $conversation_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cnt = 0;
    while ($row = $res->fetch_assoc()) {
        $rb = $row['read_by'];
        $arr = [];
        if ($rb !== null && $rb !== '') {
            $decoded = json_decode($rb, true);
            if (is_array($decoded)) $arr = $decoded;
        }
        if (!in_array($user_id, $arr, true)) $cnt++;
    }
    $stmt->close();
    return (int)$cnt;
}

function get_convo_members(mysqli $mysqli, $conversation_id) {
    $q = $mysqli->prepare("SELECT u.id, u.nome FROM users u JOIN conversation_participants cp ON cp.user_id = u.id WHERE cp.conversation_id = ?");
    $q->bind_param('i', $conversation_id);
    $q->execute();
    $res = $q->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $q->close();
    return $out;
}

/* ---------------- simple time formatting ---------------- */
function fmt_time($dt) {
    if (!$dt) return '';
    $t = strtotime($dt);
    $diff = time() - $t;
    if ($diff < 60) return $diff . 's';
    if ($diff < 3600) return floor($diff/60) . 'm';
    if ($diff < 86400) return floor($diff/3600) . 'h';
    return date('d/m H:i', $t);
}

/* ---------------- Prepare initial page data (non-AJAX) ---------------- */
$stmt = $mysqli->prepare("
SELECT
  c.id AS convo_id,
  c.title,
  m.content AS last_content,
  m.created_at AS last_created_at,
  c.last_message_at
FROM conversations c
LEFT JOIN conversation_messages m
  ON m.id = (
    SELECT id
    FROM conversation_messages
    WHERE conversation_id = c.id
    ORDER BY created_at DESC
    LIMIT 1
  )
WHERE EXISTS (SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id = c.id AND cp.user_id = ?)
ORDER BY COALESCE(c.last_message_at, m.created_at) DESC
");
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$res = $stmt->get_result();
$conversations = [];
while ($r = $res->fetch_assoc()) $conversations[] = $r;
$stmt->close();

$userSearch = isset($_GET['user_search']) ? trim($_GET['user_search']) : null;
$userResults = [];
if ($userSearch !== null && $userSearch !== '') {
    $userResults = get_user_suggestions($mysqli, $userSearch, 10, $currentUserId);
}

$currentConvId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : (count($conversations) ? (int)$conversations[0]['convo_id'] : 0);
$messages = [];
$currentConv = null;
if ($currentConvId) {
    $q = $mysqli->prepare("SELECT * FROM conversations WHERE id = ?");
    $q->bind_param('i', $currentConvId);
    $q->execute();
    $r = $q->get_result();
    $currentConv = $r->fetch_assoc();
    $q->close();

    // Marcar mensagens como lidas — sem usar JSON no SQL:
    $sel = $mysqli->prepare("SELECT id, read_by, sender_id FROM conversation_messages WHERE conversation_id = ? AND sender_id != ?");
    $sel->bind_param('ii', $currentConvId, $currentUserId);
    $sel->execute();
    $resSel = $sel->get_result();
    while ($row = $resSel->fetch_assoc()) {
        $mid = (int)$row['id'];
        $rb = $row['read_by'];
        $arr = [];
        if ($rb !== null && $rb !== '') {
            $decoded = json_decode($rb, true);
            if (is_array($decoded)) $arr = $decoded;
        }
        if (!in_array($currentUserId, $arr, true)) {
            $arr[] = $currentUserId;
            $jsonStr = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
            $up = $mysqli->prepare("UPDATE conversation_messages SET read_by = ? WHERE id = ?");
            if ($up) {
                $up->bind_param('si', $jsonStr, $mid);
                $up->execute();
                $up->close();
            }
        }
    }
    $sel->close();

    $mstmt = $mysqli->prepare("SELECT cm.*, u.nome AS sender_name FROM conversation_messages cm LEFT JOIN users u ON u.id = cm.sender_id WHERE cm.conversation_id = ? ORDER BY cm.created_at ASC");
    $mstmt->bind_param('i', $currentConvId);
    $mstmt->execute();
    $mres = $mstmt->get_result();
    while ($row = $mres->fetch_assoc()) $messages[] = $row;
    $mstmt->close();
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hub de Conversas (robozzle)</title>
  <link rel="stylesheet" href="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/hub.css' ?>">
  <style>
    :root{--gap:12px;--muted:#7b8694}
    body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb;color:#0b1220}
    .chat-app{display:flex;height:100vh}
    .chats-list{width:320px;border-right:1px solid #e6e9ef;background:#fff;display:flex;flex-direction:column}
    .chats-header{padding:12px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
    .chats-search{padding:8px;border-bottom:1px solid #f0f0f2;display:flex;gap:6px;align-items:center}
    .chats-search input{flex:1;padding:8px;border-radius:8px;border:1px solid #ddd}
    .chats-search .users-btn{padding:6px 8px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer}
    .users-dropdown{position:absolute;left:8px;top:64px;width:280px;background:#fff;border:1px solid #e6e9ef;border-radius:8px;box-shadow:0 8px 24px rgba(2,6,23,0.08);max-height:360px;overflow:auto;display:none;z-index:1500;padding:8px}
    .users-dropdown .user-item{display:flex;gap:8px;padding:8px;border-radius:6px;align-items:center;text-decoration:none;color:inherit}
    .users-dropdown .user-item:hover{background:#f3f7ff}
    .chats{overflow:auto;flex:1;position:relative}
    .chat-item{display:flex;gap:8px;padding:10px;border-bottom:1px solid #f2f4f6;text-decoration:none;color:inherit}
    .chat-item.active{background:linear-gradient(90deg,#eef7ff,#fff)}
    .chat-avatar,.avatar{width:40px;height:40px;border-radius:50%;background:#dfe9f9;color:#0b66b2;display:flex;align-items:center;justify-content:center;font-weight:700;overflow:hidden}
    .chat-avatar img{width:100%;height:100%;object-fit:cover;display:block}
    .chat-body{flex:1}
    .chat-title{font-weight:700}
    .chat-preview{color:var(--muted);font-size:0.9rem}
    .chat-unread{background:#e0245e;color:#fff;padding:4px 8px;border-radius:999px;font-weight:700;font-size:0.8rem}
    .chat-panel{flex:1;display:flex;flex-direction:column}
    .panel-header{padding:12px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:12px}
    .messages{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:10px}
    .message-row{display:flex;gap:10px}
    .message-row.me{justify-content:flex-end}
    .msg-bubble{max-width:60%;background:#fff;padding:10px;border-radius:12px;box-shadow:0 4px 14px rgba(10,20,40,0.04)}
    .message-row.me .msg-bubble{background:linear-gradient(180deg,#d6f0ff,#fff)}
    .composer{display:flex;gap:8px;padding:12px;border-top:1px solid #eee;align-items:center}
    .composer textarea{flex:1;padding:10px;border-radius:8px;border:1px solid #ddd}
    .send-btn{background:#0b66b2;color:#fff;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
  </style>
</head>
<body class="<?PHP $config = $_SESSION['config']['tema'] ?? ""; echo e($config) ?>">
  <div class="chat-app" role="application">
    <aside class="chats-list" aria-label="Conversas">
      <div class="chats-header">
        <h3 style="margin:0">Conversas</h3>
        <div style="display:flex;align-items:center;gap:8px">
          <!-- Avatar do usuário logado -->
          <button class="avatar small" title="<?php echo e($currentUserName); ?>" id="goPerfil" style="display:flex;align-items:center;gap:12px;border:none;background:none;cursor:pointer;padding:0;">
            <img src="<?php echo e($currentUserAvatar); ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;display:block">
          </button>
        </div>
      </div>

      <?php if ($flash_success): ?><div style="padding:8px;color:green"><?php echo e($flash_success); ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div style="padding:8px;color:#b32039"><?php echo e($flash_error); ?></div><?php endif; ?>

      <div class="chats-search" style="position:relative;">
        <button class="users-btn" id="usersBtn" type="button" title="Lista de usuários">Lista de usuários</button>

        <form id="searchForm" method="get" action="" style="display:flex;flex:1">
          <input type="search" id="chatSearch" name="user_search" placeholder="Procurar usuários (nome ou email)..." value="<?php echo e($userSearch ?? ''); ?>">
          <?php if ($userSearch !== null && $userSearch !== ''): ?>
            <a href="./" class="clear-search" title="Limpar" style="margin-left:8px">✖</a>
          <?php endif; ?>
        </form>

        <div class="users-dropdown" id="usersDropdown" aria-hidden="true"></div>
      </div>

      <nav class="chats" id="chatsNav" aria-label="Lista de conversas">
        <?php
          if ($userSearch !== null && $userSearch !== ''):
            if (count($userResults) === 0): ?>
              <div style="padding:12px;color:var(--muted)">Nenhum usuário encontrado.</div>
            <?php endif;
            foreach ($userResults as $u): ?>
              <a class="chat-item user-result" href="?start_conversation_with=<?php echo (int)$u['id']; ?>">
                <div class="chat-avatar">
                  <img src="<?php echo e(avatar_url_web($u['id'] ?? '')); ?>" alt="avatar">
                </div>
                <div class="chat-body">
                  <div class="chat-top">
                    <div class="chat-title"><?php echo e($u['nome']); ?></div>
                    <div class="chat-time" style="font-size:0.85rem;color:var(--muted)"><?php echo e($u['email']); ?></div>
                  </div>
                  <div class="chat-bottom">
                    <div class="chat-preview">Clique para abrir conversa</div>
                  </div>
                </div>
              </a>
            <?php endforeach;
          else:
            foreach ($conversations as $c):
              $unread = count_unread($mysqli, $c['convo_id'], $currentUserId);
              $members = get_convo_members($mysqli, $c['convo_id']);
              $title = $c['title'] ?: (count($members) ? $members[0]['nome'] : 'Chat');
              $preview = $c['last_content'] ? mb_strimwidth($c['last_content'], 0, 60, '...') : 'Sem mensagens ainda';
              $avatar = '';
              if (count($members)) {
                  foreach ($members as $memb) {
                      if ((int)$memb['id'] !== $currentUserId) {
                          $avatar = avatar_url_web($memb['id'] ?? '');
                          break;
                      }
                  }
                  if ($avatar === '') $avatar = avatar_url_web($members[0]['id'] ?? '');
              } else {
                  $avatar = avatar_url_web('');
              }
        ?>
          <a class="chat-item<?php echo ($currentConvId === (int)$c['convo_id']) ? ' active' : ''; ?>" href="?conversation_id=<?php echo (int)$c['convo_id']; ?>#conv-<?php echo (int)$c['convo_id']; ?>" id="conv-<?php echo (int)$c['convo_id']; ?>">
            <div class="chat-avatar"><img src="<?php echo e($avatar); ?>" alt="avatar"></div>
            <div class="chat-body">
              <div class="chat-top">
                <div class="chat-title"><?php echo e($title); ?></div>
                <div class="chat-time" style="font-size:0.85rem;color:var(--muted)"><?php echo e(fmt_time($c['last_created_at'] ?? $c['last_message_at'])); ?></div>
              </div>
              <div class="chat-bottom" style="display:flex;justify-content:space-between;align-items:center">
                <div class="chat-preview"><?php echo e($preview); ?></div>
                <?php if ($unread > 0): ?><div class="chat-unread"><?php echo (int)$unread; ?></div><?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; endif; ?>

      </nav>
        <div class="exit-div">

          <a href="../index.php" class="exit-btt">Voltar</a>
        </div>
      <div style="padding:8px;border-top:1px solid #f0f0f2;font-size:0.85rem;color:var(--muted)">Hub de Conversas — integrado ao robozzle</div>
    </aside>

    <main class="chat-panel" role="main">
      <?php if (!$currentConv): ?>
        <div style="padding:24px">
          <h3>Selecione uma conversa</h3>
          <p>As conversas aparecerão aqui. Clique em uma à esquerda para visualizá-la.</p>
        </div>
      <?php else: ?>
        <header class="panel-header">
  <button class="back-btn" onclick="history.back()">← Voltar</button>
  <button id="goPerfil" class="panel-title" style="display:flex;align-items:center;gap:12px;border:none;background:none;cursor:pointer;padding:0;">

    <?php
      // pega membros da conversa
      $members = get_convo_members($mysqli, $currentConvId);

      // tenta identificar o "outro" usuário (primeiro diferente do user logado)
      $otherUser = null;
      foreach ($members as $memb) {
          if ((int)$memb['id'] !== $currentUserId) {
              $otherUser = $memb;
              break;
          }
      }
      if ($otherUser === null) {
          $otherUser = $members[0]; // fallback
      }

      $title = $currentConv['title'] ?: ($otherUser['nome'] ?? 'Chat');
      $otherAvatarUrl = avatar_url_web($otherUser['id'] ?? '');
    ?>
    <div class="avatar medium" style="width:48px;height:48px;border-radius:8px;overflow:hidden;flex:0 0 auto;">
      <img src="<?php echo e($otherAvatarUrl); ?>" alt="Avatar <?php echo e($otherUser['nome'] ?? ''); ?>"
           style="width:100%;height:100%;object-fit:cover;display:block">
    </div>

    <div>
      <div class="title" style="font-weight:700"><?php echo e($title); ?></div>
      <div class="sub muted" style="font-size:0.9rem;color:var(--muted)">
        <?php echo count($members); ?> participante(s)
      </div>
    </div>
  </button>
</header>

        <section class="messages" id="messages">
          <?php foreach ($messages as $m):
              $isMe = ((int)$m['sender_id'] === $currentUserId);
          ?>
            <div class="message-row <?php echo $isMe ? 'me' : 'them'; ?>">
              <?php if (!$isMe): ?><div class="msg-avatar"><img src="<?php echo e(avatar_url_web($m['sender_id'] ?? '')); ?>" alt="avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover"></div><?php endif; ?>
              <div class="msg-bubble">
                <?php if (!empty($m['content'])): ?><div class="msg-text"><?php echo nl2br(e($m['content'])); ?></div><?php endif; ?>
                <div class="msg-time" style="font-size:0.75rem;color:var(--muted);margin-top:6px"><?php echo e(date('d/m H:i', strtotime($m['created_at']))); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        <form class="composer" action="" method="post">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="conversation_id" value="<?php echo (int)$currentConvId; ?>">

          <textarea id="message-input" name="content" placeholder="Digite uma mensagem..." rows="1" required></textarea>

          <button type="submit" class="send-btn">Enviar</button>
        </form>
      <?php endif; ?>
    </main>
  </div>

  <script>
      let goPerfil = document.getElementById("goPerfil");
      goPerfil.addEventListener("click", function() {
        window.location.href = "/Projeto_Final_PHP/view/perfil.php?user_id=<?php echo (int)$otherUser['id']; ?>";
      });
            let gomyPerfil = document.getElementsByClassName("avatar small");
      goPerfil.addEventListener("click", function() {
        window.location.href = "/Projeto_Final_PHP/view/perfil.php?user_id=<?php echo (int)$_SESSION['id']; ?>";
      });
    const chatsNav = document.getElementById('chatsNav');
    const messagesEl = document.getElementById('messages');
    const usersBtn = document.getElementById('usersBtn');
    const usersDropdown = document.getElementById('usersDropdown');
    const chatSearch = document.getElementById('chatSearch');
    let inputchat=document.getElementById("message-input")
    const EnterKeyPressCode=13;              
    inputchat.addEventListener('keydown', function(event) {if (event.keyCode === EnterKeyPressCode) {event.preventDefault(); this.form.submit();}});
    let activeConvId = <?php echo json_encode($currentConvId); ?>;
    async function fetchConversations() {
      try {
        const res = await fetch('?ajax=conversations');
        const json = await res.json();
        if (!json.ok) return;
        const items = json.items || [];
        const html = items.map(it => {
          const active = (it.convo_id === activeConvId) ? ' active' : '';
          const unreadBadge = it.unread > 0 ? `<div class="chat-unread">${it.unread}</div>` : '';
          return `
            <a class="chat-item${active}" href="?conversation_id=${it.convo_id}#conv-${it.convo_id}" id="conv-${it.convo_id}">
              <div class="chat-avatar"><img src="${it.avatar}" alt="avatar"></div>
              <div class="chat-body">
                <div class="chat-top">
                  <div class="chat-title">${escapeHtml(it.title)}</div>
                  <div class="chat-time" style="font-size:0.85rem;color:var(--muted)">${escapeHtml(it.time || '')}</div>
                </div>
                <div class="chat-bottom" style="display:flex;justify-content:space-between;align-items:center">
                  <div class="chat-preview">${escapeHtml(it.preview)}</div>
                  ${unreadBadge}
                </div>
              </div>
            </a>
          `;
        }).join('');
        if (html) chatsNav.innerHTML = html;
      } catch (err) {
        console.error('erro convs', err);
      }
    }

    async function fetchMessages() {
      if (!activeConvId) return;
      try {
        const res = await fetch('?ajax=messages&conversation_id=' + encodeURIComponent(activeConvId));
        const json = await res.json();
        if (!json.ok) return;
        const msgs = json.messages || [];
        const html = msgs.map(m => {
          const isMe = (m.sender_id === <?php echo json_encode($currentUserId); ?>);
          const avatarHtml = isMe ? '' : `<div class="msg-avatar"><img src="${m.sender_avatar}" alt="avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover"></div>`;
          const contentHtml = m.content ? `<div class="msg-text">${nl2br(escapeHtml(m.content))}</div>` : '';
          return `<div class="message-row ${isMe ? 'me' : 'them'}">${avatarHtml}<div class="msg-bubble">${contentHtml}<div class="msg-time" style="font-size:0.75rem;color:var(--muted);margin-top:6px">${escapeHtml(m.created_at)}</div></div></div>`;
        }).join('');
        if (messagesEl) {
          messagesEl.innerHTML = html;
          messagesEl.scrollTop = messagesEl.scrollHeight;
        }
      } catch (err) {
        console.error('erro msgs', err);
      }
    }

    function escapeHtml(s){ return (s===null||s===undefined) ? '' : String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
    function nl2br(s){ return s.replace(/\n/g, '<br>'); }

    setInterval(fetchConversations, 1000);
    setInterval(fetchMessages, 1000);

    usersBtn.addEventListener('click', async () => {
      if (usersDropdown.style.display === 'block') {
        usersDropdown.style.display = 'none';
        usersDropdown.setAttribute('aria-hidden','true');
        return;
      }
      try {
        usersDropdown.innerHTML = '<div style="padding:8px;color:var(--muted)">Carregando...</div>';
        usersDropdown.style.display = 'block';
        usersDropdown.setAttribute('aria-hidden','false');
        const res = await fetch('?ajax=all_users');
        const json = await res.json();
        if (!json.ok) {
          usersDropdown.innerHTML = '<div style="padding:8px;color:#b32039">Erro ao carregar</div>';
          return;
        }
        const html = (json.users || []).map(u => `
          <a class="user-item" href="?start_conversation_with=${u.id}">
            <div style="width:40px;height:40px;border-radius:50%;overflow:hidden"><img src="${u.avatar}" style="width:100%;height:100%;object-fit:cover"></div>
            <div style="display:flex;flex-direction:column">
              <strong style="font-size:0.95rem">${escapeHtml(u.nome)}</strong>
              <span style="font-size:0.85rem;color:var(--muted)">${escapeHtml(u.email)}</span>
            </div>
          </a>
        `).join('');
        usersDropdown.innerHTML = html || '<div style="padding:8px;color:var(--muted)">Nenhum usuário encontrado.</div>';
      } catch (err) {
        usersDropdown.innerHTML = '<div style="padding:8px;color:#b32039">Erro ao carregar</div>';
      }
    });

    document.addEventListener('click', (ev) => {
      if (!usersDropdown.contains(ev.target) && !usersBtn.contains(ev.target)) {
        usersDropdown.style.display = 'none';
        usersDropdown.setAttribute('aria-hidden','true');
      }
    });

    document.getElementById('searchForm').addEventListener('submit', function(e){
      if (!chatSearch.value.trim()) {
        e.preventDefault();
      }
    });

    (function(){ var m=document.getElementById('messages'); if(m) m.scrollTop=m.scrollHeight; })();
  </script>
</body>
</html>
