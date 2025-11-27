<?php

include('header.php'); // mantém o header (já cuida de sessão, etc.)
require_once __DIR__ . '/services/config.php';

$mysqli = connect_mysql();

if (!$mysqli) {
    // Em produção você pode redirecionar ou mostrar mensagem amigável
    echo "<div style='padding:20px;color:#b32039'>Erro ao conectar ao banco de dados.</div>";
    include 'footer.php';
    exit;
}

// Busca todos os levels publicados (ou retire a condição se quer todos)
$sql = "SELECT l.id, l.author_id, l.title, l.descricao, l.difficulty, l.likes_count, l.plays_count, l.level_json, l.published, l.created_at,
               u.nome AS author_name, u.avatar_url AS author_avatar
        FROM levels l
        LEFT JOIN users u ON u.id = l.author_id
        ORDER BY l.created_at DESC
        ";
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
$levels = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// helper: escape output
function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// helper: map difficulty -> color class/style
function difficulty_color($d) {
    $d = strtolower((string)$d);
    switch ($d) {
        case 'fácil':
        case 'facil':
        case 'easy':   return '#10b981'; // verde
        case 'médio':
        case 'medio':
        case 'medium': return '#f59e0b'; // amarelo/laranja
        case 'hard':
        case 'difícil':
        case 'dificil':return '#ef4444'; // vermelho
        case 'insane':
        case 'isane':
        case 'insano': return '#8b5cf6'; // roxo
        default:       return '#6b7280'; // cinza (muted)
    }
}

// helper: map color name -> css background
function cell_bg_color($colorName) {
    if (!$colorName) return '#ffffff';
    $c = strtolower($colorName);
    switch ($c) {
        case 'red':    case 'vermelho': return '#f87171';
        case 'green':  case 'verde':    return '#34d399';
        case 'blue':   case 'azul':     return '#60a5fa';
        case 'yellow': case 'amarelo':  return '#fbbf24';
        case 'gray':   case 'cinza':    return '#e5e7eb';
        default: return $colorName; // se já for uma cor CSS
    }
}

// tamanho do preview da célula (px)
$preview_cell = 18;

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Home — Levels</title>
  <link rel="stylesheet" href="css/index.css" />
  <style>
    /* Estilos mínimos para os cards/preview (inline para facilitar) */
    .levels-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-top: 18px; }
    .level-card { background: var(--surface, #fff); border-radius: 12px; padding: 12px; box-shadow: var(--shadow, 0 6px 18px rgba(15,23,36,0.06)); display:flex; gap:12px; align-items:flex-start; }
    .level-card .meta { flex: 1; min-width:0; }
    .level-card .author { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
    .level-card .author .avatar { width:48px; height:48px; border-radius:50%; overflow:hidden; background:var(--border,#e6ecf0); display:flex; align-items:center; justify-content:center; font-weight:700; }
    .level-card .title { font-weight:700; margin:0; font-size:1rem; }
    .level-card .desc { color:var(--muted,#6b7280); font-size:0.95rem; margin:6px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .level-card .meta-foot { display:flex; gap:12px; align-items:center; font-size:0.9rem; color:var(--muted,#6b7280); margin-top:8px; }
    .level-preview { width:140px; height:140px; background:#f3f4f6; border-radius:8px; display:flex; align-items:center; justify-content:center; padding:8px; box-sizing:border-box; border:1px solid rgba(0,0,0,0.04); }
    .grid-preview { display:grid; gap:2px; background:transparent; }
    .grid-preview .cell { display:flex; align-items:center; justify-content:center; border-radius:3px; font-size:10px; color:rgba(0,0,0,0.8); }
    .open-btn { background: linear-gradient(180deg,#0b66b2,#0a58a3); color:#fff; padding:8px 12px; border-radius:8px; border:none; cursor:pointer; }
    .difficulty-badge { padding:6px 8px; border-radius:9999px; font-weight:700; font-size:0.85rem; color:#fff; }
    form.card-form { display:flex; width:100%; height:100%; border:0; background:transparent; padding:0; margin:0; }
    .card-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .stats { display:flex; gap:8px; align-items:center; font-size:0.9rem; color:var(--muted,#6b7280); }
    /* responsivo */
    @media (max-width:720px) {
      .level-preview { display:none; } /* esconde preview em telas pequenas */
    }
  </style>
</head>
<body class="<?php $config = $_SESSION['config']['tema'] ?? ""; echo h($config); ?>">

  <div style="height: var(--header-height);"></div>

  <main class="container" role="main" style="padding-top: var(--gap-lg, 24px);">
    <section aria-labelledby="welcome-title">
      <h1 id="welcome-title" style="text-align:center">Níveis disponíveis</h1>

      <div class="levels-grid">
        <?php if (empty($levels)): ?>
          <div style="grid-column:1/-1;padding:20px;color:var(--muted,#6b7280)">Nenhum nível encontrado.</div>
        <?php endif; ?>

        <?php foreach ($levels as $lvl):
            // decode do JSON do level (pode falhar se o campo estiver mal formado)
            $level_json = null;
            $matrix = null;
            $functions = [];
            $cell_size = 20;
            if (!empty($lvl['level_json'])) {
                $level_json = json_decode($lvl['level_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $level_json = null;
                } else {
                    $matrix = $level_json['matrix'] ?? $level_json['grid'] ?? null;
                    $functions = $level_json['functions'] ?? [];
                    $cell_size = (int)($level_json['grid_cell_size'] ?? $level_json['cell_size'] ?? $cell_size);
                }
            }

            // fallback avatar
            // Normaliza caminho — evita duplicação de Projeto_Final_PHP
            $author_avatar = $lvl['author_avatar'] ?? '';
            if (empty($author_avatar)) {
                $avatar_src = '/Projeto_Final_PHP/image/images.jfif';
            } else {
                // Remove barras no começo
                $path = ltrim($author_avatar, '/');

                // Remove repetições Projeto_Final_PHP/
                $path = preg_replace('#^(Projeto_Final_PHP/)+#i', 'Projeto_Final_PHP/', $path);

                // Se já começa com Projeto_Final_PHP/, adiciona apenas uma barra
                if (strpos($path, 'Projeto_Final_PHP/') === 0) {
                    $avatar_src = '/' . $path;
                } else {
                    // Caso o banco tenha salvo só "image/arquivo.jpg"
                    $avatar_src = '/Projeto_Final_PHP/' . $path;
                }
            }

            // prepare JSON string to send via POST (escape for HTML attribute)
            $json_to_send = $lvl['level_json'] ?? json_encode([
                'title' => $lvl['title'],
                'difficulty' => $lvl['difficulty'],
                'matrix' => $matrix ?? [],
                'functions' => $functions
            ]);
            $json_input_value = h($json_to_send);

            // difficulty badge color
            $diff_color = difficulty_color($lvl['difficulty']);
        ?>
          <article class="level-card" role="article" aria-labelledby="level-title-<?= (int)$lvl['id'] ?>">
            <div class="level-preview" aria-hidden="true">
              <?php if (is_array($matrix) && count($matrix)): 
                    // compute cols/rows
                    $rows = count($matrix);
                    $cols = max(array_map('count', $matrix));
                    // limit preview cell size to fit in preview area
                    $maxPreview = 120; // px
                    $cell = max(8, min(28, floor($maxPreview / max($rows, $cols))));
                    echo '<div class="grid-preview" style="grid-template-columns: repeat(' . (int)$cols . ', ' . $cell . 'px);">';
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
                                // could be simple token like "red_star"
                                // best-effort parsing:
                                if (strpos($cellVal, 'star') !== false) $text = '★';
                            }
                            echo '<div class="cell" style="width:' . $cell . 'px;height:' . $cell . 'px;background:' . h($bg) . ';font-size:10px;display:flex;align-items:center;justify-content:center;">' . h($text) . '</div>';
                        }
                    }
                    echo '</div>';
                  else: ?>
                    <div style="color:var(--muted,#6b7280);font-size:0.95rem;text-align:center">Preview indisponível</div>
              <?php endif; ?>
            </div>

            <div class="meta">
              <div class="author">
                <div class="avatar" aria-hidden="true">
                  <img src="<?= h($avatar_src) ?>" alt="<?= h($lvl['author_name'] ?? 'Autor') ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
                <div>
                  <div style="display:flex; gap:8px; align-items:center;">
                    <strong><?= h($lvl['author_name'] ?? 'Autor'); ?></strong>
                    <span style="font-size:0.9rem;color:var(--muted,#6b7280)"><?= h( ($lvl['created_at']) ? date('d/m H:i', strtotime($lvl['created_at'])) : '' ); ?></span>
                  </div>
                  <h3 id="level-title-<?= (int)$lvl['id'] ?>" class="title"><?= h($lvl['title']); ?></h3>
                </div>
              </div>

              <p class="desc"><?= h($lvl['descricao'] ?? '') ?></p>

              <div class="meta-foot">
                <div class="stats">
                  <span title="Likes">❤ <?= (int)$lvl['likes_count'] ?></span>
                  <span title="Plays">▶ <?= (int)$lvl['plays_count'] ?></span>
                </div>

                <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                  <div class="difficulty-badge" style="background: <?= h($diff_color) ?>;"><?= h($lvl['difficulty'] ?: '—') ?></div>

                  <!-- Form que envia o JSON do level via POST para view/level.php -->
                  <form method="post" action="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/level.php' ?>" class="card-form">
                    <input type="hidden" name="select_level" value="<?= $json_input_value ?>">
                    <div class="card-right">
                      <button type="submit" class="open-btn">Abrir</button>
                      <?php if (!empty($functions) && is_array($functions)): ?>
                        <small style="color:var(--muted,#6b7280);font-size:0.8rem">
                          Funções: <?= h(implode(', ', array_map(function($f){ return $f['name'] ?? ''; }, $functions))); ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

    </section>
  </main>

<?php
include 'footer.php';
?>
