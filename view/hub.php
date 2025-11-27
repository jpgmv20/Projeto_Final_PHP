<?php
session_start();

/*
 hub.php - Hub de conversas integrado ao DB 'robozzle'
 - Versão com busca por nome/email adaptada à sua tabela users (coluna 'nome')
 - Cria tabelas de conversa se necessário, abre/cria conversas privadas,
   envia mensagens (upload de imagem), marca leitura e lista conversas.
 - Ajuste DB_* no topo conforme seu ambiente (XAMPP padrão: root, senha vazia)
*/

/* ---------------- DATABASE CONFIG - ajuste conforme o seu ambiente ---------------- */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'robozzle');
define('DB_USER', 'root');
define('DB_PASS', '');         // coloque sua senha do MySQL aqui
define('DB_CHARSET', 'utf8mb4');

/* ---------------- UPLOAD / ENV ---------------- */
$uploadsDir = __DIR__ . '/uploads_messages';
$uploadsWebPath = 'uploads_messages';
$maxFileSize = 2 * 1024 * 1024; // 2MB
$allowedMimes = ['image/jpeg','image/png','image/gif'];

/* ---------------- PDO CONNECTION ---------------- */
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (Throwable $e) {
    die("Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage()));
}

/* ---------------- CREATE TABLES SAFELY (no defaults for JSON) ----------------
   Essas CREATEs são idempotentes (IF NOT EXISTS).
*/
$pdo->exec("
CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('private','group') NOT NULL DEFAULT 'private',
  title VARCHAR(255) DEFAULT NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
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

/* IMPORTANT: read_by is JSON but we DO NOT set a DEFAULT value (MySQL forbids default on JSON/TEXT/BLOB) */
$pdo->exec("
CREATE TABLE IF NOT EXISTS conversation_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  content TEXT NULL,
  attachment VARCHAR(1024) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_by JSON NULL,
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ---------------- ensure uploads dir ---------------- */
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

/* ---------------- helper escape ---------------- */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------------- current user (fallback DEV) ----------------
   Em produção, remova o fallback e use sua lógica de autenticação (session).
*/
if (empty($_SESSION['id'])) {
    $row = $pdo->query("SELECT id, nome FROM users LIMIT 1")->fetch();
    if ($row) {
        $_SESSION['id'] = (int)$row['id'];
        $_SESSION['nome'] = $row['nome'];
    } else {
        die("Nenhum usuário encontrado no banco. Crie ao menos um registro em users.");
    }
}
$currentUserId = (int)$_SESSION['id'];

/* ---------------- small helper: get user suggestions (top 5) ----------------
   - If $query === '' we return the first $limit users (excluding current user).
   - If $query is non-empty, we search nome OR email and return up to $limit matches.
*/
function get_user_suggestions(PDO $pdo, ?string $query, int $limit = 5, int $exclude_user_id = 0) {
    if ($query === null) return [];
    $query = trim($query);
    if ($query === '') {
        // top users (by created_at) to be suggestions
        $sql = "SELECT id, nome, avatar_url, email FROM users WHERE id != ? ORDER BY created_at ASC LIMIT ?";
        $q = $pdo->prepare($sql);
        $q->execute([$exclude_user_id, $limit]);
        return $q->fetchAll();
    } else {
        $like = '%' . $query . '%';
        $sql = "SELECT id, nome, avatar_url, email
                FROM users
                WHERE (nome LIKE ? OR email LIKE ?) AND id != ?
                ORDER BY nome
                LIMIT ?";
        $q = $pdo->prepare($sql);
        $q->execute([$like, $like, $exclude_user_id, $limit]);
        return $q->fetchAll();
    }
}

/* ---------------- HANDLE start conversation with user (from search results) ---------------- */
if (isset($_GET['start_conversation_with'])) {
    $otherId = (int)$_GET['start_conversation_with'];
    if ($otherId && $otherId !== $currentUserId) {
        $u = $pdo->prepare("SELECT id, nome FROM users WHERE id = ?");
        $u->execute([$otherId]);
        $other = $u->fetch();
        if ($other) {
            // busca conversa privada existente entre os dois
            $q = $pdo->prepare("
                SELECT c.id
                FROM conversations c
                JOIN conversation_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
                JOIN conversation_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
                WHERE c.type = 'private'
                LIMIT 1
            ");
            $q->execute([$currentUserId, $otherId]);
            $found = $q->fetchColumn();
            if ($found) {
                header("Location: ?conversation_id=" . (int)$found);
                exit;
            } else {
                // cria conversa privada e adiciona participantes
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO conversations (type, title) VALUES ('private', NULL)")->execute();
                $convId = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                $ins->execute([$convId, $currentUserId]);
                $ins->execute([$convId, $otherId]);
                $pdo->commit();
                header("Location: ?conversation_id=" . $convId);
                exit;
            }
        }
    }
}

/* ---------------- HANDLE SEND MESSAGE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    $attachmentPath = null;
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= $maxFileSize) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (in_array($mime, $allowedMimes, true)) {
                $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/gif') ? 'gif' : 'jpg');
                $fn = bin2hex(random_bytes(8)) . '.' . $ext;
                $target = $uploadsDir . '/' . $fn;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $attachmentPath = $uploadsWebPath . '/' . $fn;
                }
            }
        }
    }

    if ($content === '' && $attachmentPath === null) {
        $_SESSION['flash_error'] = "Escreva uma mensagem ou envie um arquivo.";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Insere mensagem. Para read_by usamos JSON_ARRAY(?) para inicializar com o remetente já marcado.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO conversation_messages (conversation_id, sender_id, content, attachment, read_by) VALUES (?, ?, ?, ?, JSON_ARRAY(?))");
    $stmt->execute([$convId, $currentUserId, $content ?: null, $attachmentPath, (string)$currentUserId]);

    // atualiza last_message_at em conversations
    $pdo->prepare("UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$convId]);
    $pdo->commit();

    $_SESSION['flash_success'] = "Mensagem enviada.";
    header("Location: " . preg_replace('/#.*/','', $_SERVER['REQUEST_URI']) . "#conv-$convId");
    exit;
}

/* ---------------- USER SEARCH (left-side search bar) ----------------
   Agora usa get_user_suggestions() e traz até 5 resultados.
   Se `user_search` estiver presente na URL (mesmo que vazio), mostramos sugestões.
*/
$userSearch = isset($_GET['user_search']) ? trim($_GET['user_search']) : null;
$userResults = [];
if ($userSearch !== null) {
    // traz até 5 resultados (se vazio, retorna os 5 primeiros usuários)
    $userResults = get_user_suggestions($pdo, $userSearch, 5, $currentUserId);
}

/* ---------------- FETCH CONVERSATIONS (where user participates) ----------------
   Subselect pega última mensagem por conversa; ordenamos por último horário (c.last_message_at ou m.created_at).
*/
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
WHERE EXISTS (SELECT 1 FROM conversation_participants cp WHERE cp.conversation_id = c.id AND cp.user_id = :user_id)
ORDER BY COALESCE(c.last_message_at, m.created_at) DESC
";
$stmt = $pdo->prepare($sql);
stmt_execute:
try {
    $stmt->execute(['user_id' => $currentUserId]);
} catch (Throwable $ex) {
    // Em caso raro de erro, mostra mensagem e segue com lista vazia
    error_log("Erro ao buscar conversas: " . $ex->getMessage());
    $conversations = [];
}
if (!isset($conversations)) {
    $conversations = $stmt->fetchAll();
}

/* ---------------- helper: count unread messages ----------------
   Conta mensagens onde read_by NÃO contém o user_id e sender != user.
*/
function count_unread(PDO $pdo, $conversation_id, $user_id) {
    $sql = "SELECT COUNT(*) FROM conversation_messages WHERE conversation_id = ? AND (JSON_CONTAINS(read_by, JSON_QUOTE(?)) = 0 OR read_by IS NULL) AND sender_id != ?";
    $q = $pdo->prepare($sql);
    $q->execute([$conversation_id, (string)$user_id, $user_id]);
    return (int)$q->fetchColumn();
}

/* ---------------- SELECT CURRENT CONVERSATION & MESSAGES ---------------- */
$currentConvId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : (count($conversations) ? (int)$conversations[0]['convo_id'] : 0);
$messages = [];
$currentConv = null;
if ($currentConvId) {
    $q = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $q->execute([$currentConvId]);
    $currentConv = $q->fetch();

    // marca mensagens como lidas por esse usuário (adiciona user_id no read_by JSON se ainda não estiver)
    $markSql = "UPDATE conversation_messages
                SET read_by = JSON_ARRAY_APPEND(COALESCE(read_by, JSON_ARRAY()), '$', CAST(? AS JSON))
                WHERE conversation_id = ? AND (JSON_CONTAINS(read_by, JSON_QUOTE(?)) = 0 OR read_by IS NULL)";
    $pdo->prepare($markSql)->execute([(string)$currentUserId, $currentConvId, (string)$currentUserId]);

    // fetch messages
    $mstmt = $pdo->prepare("SELECT cm.*, u.nome AS sender_name FROM conversation_messages cm LEFT JOIN users u ON u.id = cm.sender_id WHERE cm.conversation_id = ? ORDER BY cm.created_at ASC");
    $mstmt->execute([$currentConvId]);
    $messages = $mstmt->fetchAll();
}

/* ---------------- get members of a conversation ---------------- */
function get_convo_members(PDO $pdo, $conversation_id) {
    $q = $pdo->prepare("SELECT u.id, u.nome, u.avatar_url FROM users u JOIN conversation_participants cp ON cp.user_id = u.id WHERE cp.conversation_id = ?");
    $q->execute([$conversation_id]);
    return $q->fetchAll();
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

/* flash */
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
    Pequeno CSS inline para garantir legibilidade mínima caso falte hub.css
    :root{--gap:12px;--muted:#7b8694}
    body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb;color:#0b1220}
    .chat-app{display:flex;height:100vh}
    .chats-list{width:320px;border-right:1px solid #e6e9ef;background:#fff;display:flex;flex-direction:column}
    .chats-header{padding:12px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
    .chats-search{padding:8px;border-bottom:1px solid #f0f0f2}
    .chats-search input{width:100%;padding:8px;border-radius:8px;border:1px solid #ddd}
    .chats{overflow:auto;flex:1}
    .chat-item{display:flex;gap:8px;padding:10px;border-bottom:1px solid #f2f4f6;text-decoration:none;color:inherit}
    .chat-item.active{background:linear-gradient(90deg,#eef7ff,#fff)}
    .chat-avatar,.avatar{width:40px;height:40px;border-radius:50%;background:#dfe9f9;color:#0b66b2;display:flex;align-items:center;justify-content:center;font-weight:700}
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
    .file-label input{display:none}
  </style>
</head>
<body class="<?PHP $config = $_SESSION['config']['tema'] ?? ""; echo $config ?>">
  <div class="chat-app" role="application">
    <aside class="chats-list" aria-label="Conversas">
      <div class="chats-header">
        <h3 style="margin:0">Conversas</h3>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="avatar small"><?= e(strtoupper(substr($_SESSION['nome'] ?? '',0,1))); ?></div>
        </div>
      </div>

      <?php if ($flash_success): ?><div style="padding:8px;color:green"><?php echo e($flash_success); ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div style="padding:8px;color:#b32039"><?php echo e($flash_error); ?></div><?php endif; ?>

      <div class="chats-search">
        <form method="get" action="">
          <!-- se user_search está presente na URL (mesmo vazio), mostramos sugestões -->
          <input type="search" id="chatSearch" name="user_search" placeholder="Procurar usuários (nome ou email)..." value="<?php echo e($userSearch ?? ''); ?>">
          <?php if ($userSearch !== null && $userSearch !== ''): ?>
            <a href="./" class="clear-search" title="Limpar" style="margin-left:8px">✖</a>
          <?php endif; ?>
        </form>
      </div>

      <nav class="chats" id="chatsNav">
        <?php
          // Se o parâmetro user_search está presente (mesmo vazio), mostramos os userResults (máx 5).
          if ($userSearch !== null):
            if (count($userResults) === 0): ?>
              <div style="padding:12px;color:var(--muted)">Nenhum usuário encontrado.</div>
            <?php endif;
            foreach ($userResults as $u): ?>
              <a class="chat-item user-result" href="?start_conversation_with=<?php echo (int)$u['id']; ?>">
                <div class="chat-avatar"><?php echo e(strtoupper(substr($u['nome'] ?? 'U',0,1))); ?></div>
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
            // Sem busca ativa: mostramos as conversas normais
            foreach ($conversations as $c):
              $unread = count_unread($pdo, $c['convo_id'], $currentUserId);
              $members = get_convo_members($pdo, $c['convo_id']);
              $title = $c['title'] ?: (count($members) ? $members[0]['nome'] : 'Chat');
              $preview = $c['last_content'] ? mb_strimwidth($c['last_content'], 0, 60, '...') : 'Sem mensagens ainda';
        ?>
          <a class="chat-item<?php echo ($currentConvId === (int)$c['convo_id']) ? ' active' : ''; ?>" href="?conversation_id=<?php echo (int)$c['convo_id']; ?>#conv-<?php echo (int)$c['convo_id']; ?>" id="conv-<?php echo (int)$c['convo_id']; ?>">
            <div class="chat-avatar"><?php echo e(strtoupper(substr($title,0,1))); ?></div>
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
          <div class="panel-title" style="display:flex;align-items:center;gap:12px">
            <?php
              $members = get_convo_members($pdo, $currentConvId);
              $title = $currentConv['title'] ?: (count($members) ? $members[0]['nome'] : 'Chat');
            ?>
            <div class="avatar medium"><?php echo e(strtoupper(substr($title,0,1))); ?></div>
            <div>
              <div class="title" style="font-weight:700"><?php echo e($title); ?></div>
              <div class="sub muted" style="font-size:0.9rem;color:var(--muted)"><?php echo count($members); ?> participante(s)</div>
            </div>
          </div>
        </header>

        <section class="messages" id="messages">
          <?php foreach ($messages as $m):
              $isMe = ((int)$m['sender_id'] === $currentUserId);
          ?>
            <div class="message-row <?php echo $isMe ? 'me' : 'them'; ?>">
              <?php if (!$isMe): ?><div class="msg-avatar"><?php echo e(strtoupper(substr($m['sender_name'] ?? 'U',0,1))); ?></div><?php endif; ?>
              <div class="msg-bubble">
                <?php if (!empty($m['attachment'])): ?>
                  <div class="msg-attachment" style="margin-bottom:8px"><img src="<?php echo e($m['attachment']); ?>" alt="anexo" style="max-width:240px;border-radius:8px"></div>
                <?php endif; ?>
                <?php if (!empty($m['content'])): ?><div class="msg-text"><?php echo nl2br(e($m['content'])); ?></div><?php endif; ?>
                <div class="msg-time" style="font-size:0.75rem;color:var(--muted);margin-top:6px"><?php echo e(date('d/m H:i', strtotime($m['created_at']))); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        <form class="composer" action="" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="conversation_id" value="<?php echo (int)$currentConvId; ?>">

          <label class="file-label" title="Anexar imagem" style="cursor:pointer">
            <input type="file" name="attachment" accept="image/*">
            <span class="icon">📎</span>
          </label>

          <textarea name="content" placeholder="Digite uma mensagem..." rows="1" required></textarea>

          <button type="submit" class="send-btn">Enviar</button>
        </form>
      <?php endif; ?>
    </main>
  </div>

  <script>
    (function(){ var m=document.getElementById('messages'); if(m) m.scrollTop=m.scrollHeight; })();
  </script>
</body>
</html>
