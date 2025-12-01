<?php
// view/perfil.php
// Perfil de usuário com edição de descrição, levels clicáveis e like toggle
require_once __DIR__ . '/../services/config.php';
session_start();

/* -----------------------
   Helpers
   ----------------------- */
function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

function difficulty_color($d) {
    $d = strtolower((string)$d);
    switch ($d) {
        case 'fácil': case 'facil': case 'easy':   return '#10b981';
        case 'médio': case 'medio': case 'medium': return '#f59e0b';
        case 'hard': case 'difícil': case 'dificil': return '#ef4444';
        case 'insane': case 'insano': return '#8b5cf6';
        default: return '#6b7280';
    }
}

function cell_bg_color($colorName) {
    if (!$colorName) return '#ffffff';
    $c = strtolower($colorName);
    switch ($c) {
        case 'red':    case 'vermelho': return '#f87171';
        case 'green':  case 'verde':    return '#34d399';
        case 'blue':   case 'azul':     return '#60a5fa';
        case 'yellow': case 'amarelo':  return '#fbbf24';
        case 'gray':   case 'cinza':    return '#e5e7eb';
        default: return $colorName;
    }
}

/* -----------------------
   Conexão DB & variáveis iniciais
   ----------------------- */
$mysqli = connect_mysql();
if (!$mysqli) {
    echo '<div style="padding:20px;color:#b32039">Erro ao conectar ao banco de dados.</div>';
    exit;
}

$currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

/* -----------------------
   Processa POST actions (antes de qualquer saída)
   - actions suportadas: follow, unfollow, update_description, like_toggle
   ----------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ação: follow / unfollow
    if (isset($_POST['action']) && isset($_POST['target_user_id'])) {
        $action = $_POST['action'];
        $postTarget = (int)$_POST['target_user_id'];

        if ($action === 'follow' || $action === 'unfollow') {
            if ($currentUserId <= 0) {
                header('Location: /Projeto_Final_PHP/view/login.php');
                exit;
            }
            if ($postTarget === $currentUserId) {
                header('Location: /Projeto_Final_PHP/view/perfil.php?user_id=' . $postTarget);
                exit;
            }

            if ($action === 'follow') {
                $stmt = $mysqli->prepare("INSERT IGNORE INTO followers (follower_id, user_id) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ii', $currentUserId, $postTarget);
                    $stmt->execute();
                    $stmt->close();
                }
            } else { // unfollow
                $stmt = $mysqli->prepare("DELETE FROM followers WHERE follower_id = ? AND user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $currentUserId, $postTarget);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            header('Location: /Projeto_Final_PHP/view/perfil.php?user_id=' . $postTarget);
            exit;
        }
    }

    // ação: update_description (apenas se for dono do perfil)
    if (isset($_POST['action']) && $_POST['action'] === 'update_description' && isset($_POST['target_user_id'])) {
        $postTarget = (int)$_POST['target_user_id'];
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($currentUserId <= 0) {
            header('Location: /Projeto_Final_PHP/view/login.php');
            exit;
        }
        if ($postTarget === $currentUserId) {
            $stmt = $mysqli->prepare("UPDATE users SET descricao = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $desc, $currentUserId);
                $stmt->execute();
                $stmt->close();
            }
        }
        header('Location: /Projeto_Final_PHP/view/perfil.php?user_id=' . $postTarget);
        exit;
    }

    // ação: like_toggle (user likes/unlikes a level)
    if (isset($_POST['action']) && $_POST['action'] === 'like_toggle' && isset($_POST['level_id'])) {
        $levelId = (int)$_POST['level_id'];
        if ($currentUserId <= 0) {
            header('Location: /Projeto_Final_PHP/view/login.php');
            exit;
        }
        // verifica se já curtiu
        $q = $mysqli->prepare("SELECT 1 FROM likes WHERE user_id = ? AND level_id = ? LIMIT 1");
        $q->bind_param('ii', $currentUserId, $levelId);
        $q->execute();
        $res = $q->get_result();
        $already = (bool)$res->fetch_row();
        $q->close();

        if ($already) {
            // remove like
            $d = $mysqli->prepare("DELETE FROM likes WHERE user_id = ? AND level_id = ?");
            if ($d) {
                $d->bind_param('ii', $currentUserId, $levelId);
                $d->execute();
                $d->close();
            }
        } else {
            // adiciona like (INSERT IGNORE por segurança)
            $i = $mysqli->prepare("INSERT IGNORE INTO likes (user_id, level_id) VALUES (?, ?)");
            if ($i) {
                $i->bind_param('ii', $currentUserId, $levelId);
                $i->execute();
                $i->close();
            }
        }

        // redireciona de volta ao perfil (evita re-post)
        $redir = '/Projeto_Final_PHP/view/perfil.php';
        if (isset($_POST['redir_user_id'])) $redir .= '?user_id=' . (int)$_POST['redir_user_id'];
        header('Location: ' . $redir);
        exit;
    }
}

/* -----------------------
   A partir daqui podemos incluir header e renderizar HTML
   ----------------------- */
/* NOTA: deixei o include do header aqui (antes do HTML principal) pois header.php já gera o <head> e o header da página */
include __DIR__ . '/../header.php';

/* -----------------------
   Determina qual perfil mostrar
   ----------------------- */
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($currentUserId ?: 0);
if ($targetUserId <= 0) {
    echo '<main class="container" role="main" style="padding:32px;"><div style="color:#b32039">Perfil inválido.</div></main>';
    include __DIR__ . '/../footer.php';
    exit;
}

/* -----------------------
   Busca dados do usuário alvo
   ----------------------- */
$stmt = $mysqli->prepare("SELECT id, nome, descricao, avatar_url, followers_count, following_count, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $targetUserId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<main class="container" role="main" style="padding:32px;"><div style="color:#b32039">Usuário não encontrado.</div></main>';
    include __DIR__ . '/../footer.php';
    exit;
}

$avatarUrl = avatar_url_web($user['avatar_url'] ?? '');

/* -----------------------
   Verifica se o usuário logado já segue o perfil
   ----------------------- */
$isFollowing = false;
if ($currentUserId && $currentUserId !== (int)$user['id']) {
    $q = $mysqli->prepare("SELECT 1 FROM followers WHERE follower_id = ? AND user_id = ? LIMIT 1");
    $q->bind_param('ii', $currentUserId, $targetUserId);
    $q->execute();
    $r = $q->get_result();
    $isFollowing = (bool)$r->fetch_row();
    $q->close();
}

/* -----------------------
   Busca níveis do usuário (com level_json)
   ----------------------- */
$lvlStmt = $mysqli->prepare("SELECT id, title, descricao, difficulty, likes_count, plays_count, level_json, created_at FROM levels WHERE author_id = ? ORDER BY created_at DESC");
$lvlStmt->bind_param('i', $targetUserId);
$lvlStmt->execute();
$lvlRes = $lvlStmt->get_result();
$levelsList = $lvlRes->fetch_all(MYSQLI_ASSOC);
$lvlStmt->close();

/* -----------------------
   Para otimizar checks de like, busca todos likes do user para os levels listados
   ----------------------- */
$userLiked = [];
if ($currentUserId && !empty($levelsList)) {
    $levelIds = array_map(function($l){ return (int)$l['id']; }, $levelsList);
    // build placeholders
    $placeholders = implode(',', array_fill(0, count($levelIds), '?'));
    $types = str_repeat('i', count($levelIds) + 1);
    $sql = "SELECT level_id FROM likes WHERE user_id = ? AND level_id IN ($placeholders)";
    $stmt = $mysqli->prepare($sql);
    // bind params dynamically
    $params = array_merge([$currentUserId], $levelIds);
    $refs = [];
    foreach ($params as $i => $v) $refs[$i] = &$params[$i];
    call_user_func_array([$stmt, 'bind_param'], array_merge([ $types ], $refs));
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $userLiked[(int)$r['level_id']] = true;
    $stmt->close();
}

/* -----------------------
   Render
   ----------------------- */
?>
<body class="<?php echo $_SESSION['config']['tema'] ?? "" ?>">

<!-- Ajuste dinâmico do título da aba com o nome do usuário -->
<script>
  // define o título do documento com o nome do usuário + " - Perfil"
  document.title = <?= json_encode(($user['nome'] ?? 'Usuário') . ' - Perfil') ?>;
</script>

<main class="container" role="main" style="padding-top:28px;padding-bottom:48px; background: var(--bg);">
  <section aria-labelledby="perfil-title" style="max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:18px;align-items:center;">

    <div style="display:flex;flex-direction:column;align-items:center;gap:12px;width:100%">

      <!-- avatar + name + follow/edit -->
      <div style="display:flex;align-items:center;gap:16px;width:100%;justify-content:center;">
        <div style="width:160px;height:160px;border-radius:12px;overflow:hidden;border:1px solid var(--border);background:var(--surface);box-shadow:var(--shadow);">
          <img src="<?= h($avatarUrl) ?>" alt="<?= h($user['nome']) ?> - avatar" style="width:100%;height:100%;object-fit:cover;display:block;">
        </div>

        <div style="display:flex;flex-direction:column;align-items:flex-start;">
          <div style="display:flex;align-items:center;gap:12px;">
            <h1 id="perfil-title" style="margin:0;font-size:1.6rem;"><?= h($user['nome']) ?></h1>

            <?php if ($currentUserId && $currentUserId !== (int)$user['id']): ?>
              <form method="post" style="margin:0;">
                <input type="hidden" name="target_user_id" value="<?= (int)$user['id'] ?>">
                <?php if ($isFollowing): ?>
                  <input type="hidden" name="action" value="unfollow">
                  <button type="submit" class="btn" style="background:transparent;border:1px solid var(--border);padding:8px 12px;border-radius:8px;cursor:pointer;">Deixar de seguir</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="follow">
                  <button type="submit" class="btn--primary" style="padding:8px 12px;border-radius:8px;cursor:pointer;">Seguir</button>
                <?php endif; ?>
              </form>
            <?php elseif (!$currentUserId): ?>
              <a href="/Projeto_Final_PHP/view/login.php" class="btn" style="padding:8px 12px;border-radius:8px;">Entrar para seguir</a>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:12px;color:var(--muted);font-size:0.95rem;margin-top:8px">
            <div title="Seguidores"><strong><?= (int)$user['followers_count'] ?></strong> seguidores</div>
            <div title="Seguindo"><strong><?= (int)$user['following_count'] ?></strong> seguindo</div>
            <?php if (!empty($user['created_at'])): ?>
              <div style="opacity:0.8">membro desde <?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- descrição: se for dono do perfil mostra editor -->
      <div style="width:100%;max-width:900px;">
        <div style="background:var(--surface);border:1px solid var(--border);padding:16px;border-radius:12px;box-shadow:var(--shadow);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <h2 style="margin:0 0 8px 0;font-size:1rem">Sobre</h2>
            <?php if ($currentUserId && $currentUserId === (int)$user['id']): ?>
              <!-- botão de editar (ativa a textarea via JS) -->
              <button id="editDescBtn" class="btn" type="button" style="padding:6px 10px;border-radius:8px;">Editar</button>
            <?php endif; ?>
          </div>

          <!-- view mode -->
          <div id="descView" style="margin-top:8px;color:var(--text);line-height:1.6;">
            <?= nl2br(h($user['descricao'] ?? '')) ?: '<span style="color:var(--muted)">Sem descrição ainda.</span>' ?>
          </div>

          <?php if ($currentUserId && $currentUserId === (int)$user['id']): ?>
            <!-- edit mode -->
            <form id="descForm" method="post" style="margin-top:8px;display:none;">
              <input type="hidden" name="action" value="update_description">
              <input type="hidden" name="target_user_id" value="<?= $currentUserId ?>">
              <textarea name="description" rows="4" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);box-sizing:border-box;"><?= h($user['descricao'] ?? '') ?></textarea>
              <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end">
                <button type="button" id="cancelDesc" class="btn" style="background:transparent;border:1px solid var(--border);padding:8px 12px;border-radius:8px;">Cancelar</button>
                <button type="submit" class="btn--primary" style="padding:8px 12px;border-radius:8px;">Salvar</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Levels list (cards iguais ao index, com preview + jogar + like) -->
    <div style="width:100%;max-width:1100px;">
      <h3 style="margin:0 0 12px 0">Níveis de <?= h($user['nome']) ?></h3>

      <?php if (empty($levelsList)): ?>
        <div style="padding:12px;color:var(--muted)">Nenhum nível encontrado.</div>
      <?php else: ?>
        <div class="levels-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
          <?php foreach ($levelsList as $lvl):
              $matrix = null;
              $functions = [];
              $cell_size = 18;
              if (!empty($lvl['level_json'])) {
                  $level_json = json_decode($lvl['level_json'], true);
                  if (json_last_error() === JSON_ERROR_NONE) {
                      $matrix = $level_json['matrix'] ?? $level_json['grid'] ?? null;
                      $functions = $level_json['functions'] ?? [];
                      $cell_size = (int)($level_json['grid_cell_size'] ?? $level_json['cell_size'] ?? $cell_size);
                  }
              }
              $json_to_send = $lvl['level_json'] ?? json_encode([
                  'title' => $lvl['title'],
                  'difficulty' => $lvl['difficulty'],
                  'matrix' => $matrix ?? [],
                  'functions' => $functions
              ]);
              $json_input_value = h($json_to_send);
              $diff_color = difficulty_color($lvl['difficulty']);
              $isLiked = isset($userLiked[(int)$lvl['id']]);
          ?>
            <article class="level-card" role="article" aria-labelledby="level-title-<?= (int)$lvl['id'] ?>" style="background:var(--surface);border-radius:12px;padding:12px;box-shadow:var(--shadow);display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border);">
              <div class="level-preview" aria-hidden="true" style="width:140px;height:140px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:8px;box-sizing:border-box;border:1px solid rgba(0,0,0,0.04);">
                <?php if (is_array($matrix) && count($matrix)):
                    $rows = count($matrix);
                    $cols = max(array_map('count', $matrix));
                    $maxPreview = 120;
                    $cell = max(8, min(28, floor($maxPreview / max($rows, $cols))));
                    echo '<div class="grid-preview" style="display:grid;grid-template-columns:repeat(' . (int)$cols . ', ' . $cell . 'px);gap:2px;">';
                    for ($r = 0; $r < $rows; $r++) {
                        for ($c = 0; $c < $cols; $c++) {
                            $cellVal = $matrix[$r][$c] ?? null;
                            $bg = '#fff';
                            $text = '';
                            if (is_array($cellVal)) {
                                $bg = cell_bg_color($cellVal['color'] ?? '');
                                $sym = $cellVal['symbol'] ?? '';
                                if ($sym === 'star') $text = '★';
                                elseif ($sym === 'play' || $sym === 'player') $text = '▶';
                                else $text = ($sym && $sym !== 'none') ? h(substr($sym,0,1)) : '';
                            } elseif (is_string($cellVal) && $cellVal !== '') {
                                if (strpos($cellVal, 'star') !== false) $text = '★';
                            }
                            echo '<div class="cell" style="width:' . $cell . 'px;height:' . $cell . 'px;background:' . h($bg) . ';font-size:10px;display:flex;align-items:center;justify-content:center;border-radius:3px;">' . h($text) . '</div>';
                        }
                    }
                    echo '</div>';
                  else: ?>
                    <div style="color:var(--muted);font-size:0.95rem;text-align:center">Preview indisponível</div>
                <?php endif; ?>
              </div>

              <div class="meta" style="flex:1;min-width:0;">
                <div class="author" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                  <div class="avatar" aria-hidden="true" style="width:48px;height:48px;border-radius:50%;overflow:hidden;background:var(--border);display:flex;align-items:center;justify-content:center;font-weight:700;">
                    <img src="<?= h($avatarUrl) ?>" alt="<?= h($user['nome']) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="display:flex; gap:8px; align-items:center;">
                      <strong><?= h($lvl['title']) ?></strong>
                      <span style="font-size:0.9rem;color:var(--muted)"><?= h( ($lvl['created_at']) ? date('d/m H:i', strtotime($lvl['created_at'])) : '' ); ?></span>
                    </div>
                    <div class="desc" style="color:var(--muted);font-size:0.95rem;margin:6px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($lvl['descricao'] ?? '') ?></div>
                  </div>
                </div>

                <div class="meta-foot" style="display:flex;gap:12px;align-items:center;font-size:0.9rem;color:var(--muted);margin-top:8px;">
                  <div class="stats" style="display:flex;gap:8px;align-items:center;">
                    <form method="post" style="display:inline;margin:0;">
                      <input type="hidden" name="action" value="like_toggle">
                      <input type="hidden" name="level_id" value="<?= (int)$lvl['id'] ?>">
                      <input type="hidden" name="redir_user_id" value="<?= (int)$targetUserId ?>">
                      <?php if ($currentUserId): ?>
                        <button type="submit" title="<?= $isLiked ? 'Remover curtida' : 'Curtir' ?>" style="background:transparent;border:0;cursor:pointer;font-size:1rem;">
                          <span style="padding:6px;border-radius:6px;background:<?= $isLiked ? 'linear-gradient(180deg,#ffe9ec,#ffebef)' : 'transparent' ?>;">
                            ❤ <?= (int)$lvl['likes_count'] ?>
                          </span>
                        </button>
                      <?php else: ?>
                        <a href="/Projeto_Final_PHP/view/login.php" title="Entrar para curtir" style="text-decoration:none;color:inherit;">❤ <?= (int)$lvl['likes_count'] ?></a>
                      <?php endif; ?>
                    </form>

                    <div title="Plays">▶ <?= (int)$lvl['plays_count'] ?></div>
                  </div>

                  <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                    <div class="difficulty-badge" style="padding:6px 8px;border-radius:9999px;font-weight:700;font-size:0.85rem;color:#fff;background:<?= h($diff_color) ?>;"><?= h($lvl['difficulty'] ?: '—') ?></div>

                    <!-- Form que envia o JSON do level via POST para view/level.php -->
                    <form method="post" action="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/level.php' ?>" class="card-form" style="display:flex;margin:0;">
                      <input type="hidden" name="select_level" value="<?= $json_input_value ?>">
                      <button type="submit" class="open-btn" style="background: linear-gradient(180deg,#0b66b2,#0a58a3); color:#fff; padding:8px 12px; border-radius:8px; border:none; cursor:pointer;">Jogar</button>
                    </form>
                  </div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </section>
</main>
</body>

<script>
  // JS simples para editar descrição inline
  (function(){
    const editBtn = document.getElementById('editDescBtn');
    if (!editBtn) return;
    const descView = document.getElementById('descView');
    const descForm = document.getElementById('descForm');
    const cancelBtn = document.getElementById('cancelDesc');

    editBtn.addEventListener('click', () => {
      descView.style.display = 'none';
      descForm.style.display = 'block';
      editBtn.style.display = 'none';
    });

    if (cancelBtn) {
      cancelBtn.addEventListener('click', () => {
        descForm.style.display = 'none';
        descView.style.display = 'block';
        editBtn.style.display = 'inline-block';
      });
    }
  })();
</script>

<?php
include __DIR__ . '/../footer.php';
$mysqli->close();
