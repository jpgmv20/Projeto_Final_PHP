<?php
// view/level.php
require_once __DIR__ . '/../header.php'; // integra header conforme solicitado

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// pega JSON do POST (espera-se que integramente envie $_POST['select_level'])
$raw = $_POST['select_level'] ?? null;
$config = null;

if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $config = $decoded;
    }
}

// fallback demonstrativo se nada for enviado
if (!$config) {
    $config = [
        'title' => 'Demo Level',
        'difficulty' => 'easy',
        'grid_cell_size' => 64,
        'matrix' => [
            [['color'=>'blue','symbol'=>'star'], ['color'=>'none','symbol'=>'none'], ['color'=>'green','symbol'=>'none']],
            [['color'=>'none','symbol'=>'none'], ['color'=>'red','symbol'=>'star'], ['color'=>'none','symbol'=>'none']],
            [['color'=>'green','symbol'=>'none'], ['color'=>'none','symbol'=>'none'], ['color'=>'blue','symbol'=>'play']]
        ],
        'functions' => [
            ['name'=>'F0','size'=>5],
            ['name'=>'F1','size'=>3],
        ]
    ];
}

// normaliza variáveis
$title = $config['title'] ?? 'Untitled Level';
$difficulty = strtolower($config['difficulty'] ?? 'easy');
$cellSize = (int)($config['grid_cell_size'] ?? 48);
$matrix = $config['matrix'] ?? [];
$functions = $config['functions'] ?? [];

// função utilitária: encontra ponto inicial do player (symbol 'play' ou 'player')
$startPos = ['r'=>0,'c'=>0];
$foundStart = false;
for ($r=0;$r<count($matrix) && !$foundStart;$r++){
    for ($c=0;$c<count($matrix[$r]) && !$foundStart;$c++){
        $cell = $matrix[$r][$c] ?? null;
        if (is_array($cell) && strtolower($cell['symbol'] ?? '') === 'play') {
            $startPos = ['r'=>$r,'c'=>$c];
            $foundStart = true;
        }
    }
}

// difficulty colors
$diff_colors = [
  'easy' => '#16a34a',
  'medium' => '#f59e0b',
  'hard' => '#dc2626',
  'insane' => '#7c3aed'
];
$diff_color = $diff_colors[$difficulty] ?? $diff_colors['easy'];

// pass the original JSON string for potential POST back
$level_json_string = $raw ?? json_encode($config, JSON_UNESCAPED_UNICODE);

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?> — Level</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/level.css'; ?>">
  <style>
    /* Layout local: mantém a grade isolada em linha horizontal e coloca controles abaixo */
    .level-wrap{max-width:1100px;margin:0 auto;padding:18px}
    .level-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
    .level-title{font-size:1.6rem;font-weight:700}
    .level-diff{padding:6px 10px;border-radius:9999px;color:#fff;font-weight:700}

    /* centraliza apenas a board como elemento horizontal solitário */
    .board-area{display:flex;justify-content:center;margin:18px 0}
    .board{background:transparent;padding:8px;border-radius:8px}
    .grid{display:grid;gap:6px; /* grid-template-columns inlined by PHP */ }

    /* células: só cor + ícone mínimo para star/player; removi nomes textuais */
    .cell{box-sizing:border-box;width:<?= $cellSize ?>px;height:<?= $cellSize ?>px;border-radius:8px;display:flex;align-items:center;justify-content:center;
          box-shadow:0 6px 12px rgba(10,20,40,0.04);border:1px solid rgba(0,0,0,0.04); font-weight:700; font-size:18px; color:rgba(0,0,0,0.85)}
    .c-none{background:#f3f4f6} /* espaço vazio visual */
    .c-blue{background:#60a5fa}
    .c-green{background:#34d399}
    .c-red{background:#f87171}

    /* symbol styling (only when present) */
    .cell .sym{font-size:18px;line-height:1;margin:0;padding:0}

    /* abaixo da grade: comandos à esquerda, funções à direita (em linha) */
    .controls-below{display:flex;gap:20px;align-items:flex-start;justify-content:space-between;margin-top:20px;flex-wrap:wrap}
    .controls-column{display:flex;flex-direction:column;gap:12px;min-width:220px;flex:1}
    .palette-grid{display:grid;grid-template-columns:repeat(3,48px);gap:8px}
    .cmd-btn{width:48px;height:48px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);background:#fff;cursor:pointer;font-size:18px}
    .fn-list {
        display: flex;
        flex-direction: column; /* agora ficam uma em cima da outra */
        gap: 18px;
        width: 100%;
    }

    .function {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,0,0,0.08); /* opcional: deixa mais organizado */
    }

    .fn-slots {
        display: flex;
        gap: 6px;
        flex-wrap: wrap; /* permite quebrar para baixo se tiver muitas */
    }
    .fn-slot{width:34px;height:34px;border-radius:6px;border:1px dashed rgba(0,0,0,0.08);background:#fff;cursor:pointer}
    .sequence-area{margin-top:12px}
    /* responsive */
    @media (max-width:900px){
      .controls-below{flex-direction:column}
      .palette-grid{grid-template-columns:repeat(3,40px)}
      .cmd-btn{width:40px;height:40px}
    }
  </style>
</head>

<body>
  <div style="height: var(--header-height);"></div>

  <main class="level-wrap">
    <!-- header -->
    <div class="level-head">
      <div class="level-title"><?= e($title) ?></div>
      <div><span class="level-diff" style="background: <?= e($diff_color) ?>;"><?= e(ucfirst($difficulty)) ?></span></div>
    </div>

    <!-- BOARD (isolated horizontal) -->
    <div class="board-area">
      <div class="board" role="region" aria-label="Matriz da fase">
        <?php
          $rows = is_array($matrix) ? count($matrix) : 0;
          $cols = ($rows>0 && is_array($matrix[0])) ? count($matrix[0]) : 0;
          $grid_style = "grid-template-columns: repeat(" . max(1,$cols) . ", " . ($cellSize) . "px);";
        ?>
        <div class="grid" id="grid" style="<?= e($grid_style) ?>">
          <?php
            for ($r = 0; $r < $rows; $r++) {
                for ($c = 0; $c < $cols; $c++) {
                    $cell = $matrix[$r][$c] ?? null;
                    $color = 'none'; $symbol = 'none';
                    if (is_array($cell)) {
                        $color = strtolower($cell['color'] ?? 'none');
                        $symbol = strtolower($cell['symbol'] ?? 'none');
                    }
                    $cls = ($color === 'blue') ? 'c-blue' : (($color === 'green') ? 'c-green' : (($color === 'red') ? 'c-red' : 'c-none'));

                    // map symbol: star -> ★ , play -> ▶ , else empty (no name)
                    $sym = '';
                    if ($symbol === 'star') {
                        $sym = '★';
                    } elseif ($symbol === 'play' || $symbol === 'player') {
                        $sym = '▶';
                    }

                    echo '<div class="cell ' . e($cls) . '"'
                    . ' data-row="' . $r . '"'
                    . ' data-col="' . $c . '"'
                    . ' data-color="' . e($color) . '"'
                    . ' data-symbol="' . e($symbol) . '">';
                    if ($sym !== '') {
                        echo '<span class="sym">' . e($sym) . '</span>';
                    }
                    echo '</div>';
                }
            }
          ?>
        </div>
      </div>
    </div>

    <!-- BELOW: comandos e funções (aparecem somente depois da grid, em linha) -->
    <div class="controls-below" role="region" aria-label="Controles da fase">

      <!-- comandos (paleta 3x3) -->
      <div class="controls-column" style="max-width:360px;">
        <h4>Comandos</h4>
        <div class="palette-grid" role="toolbar" aria-label="Paleta de comandos">
          <!-- ordem fixa 3x3 -->
          <button class="cmd-btn" data-cmd="TURN_LEFT" title="Girar esquerda">⤴︎</button>
          <button class="cmd-btn" data-cmd="FORWARD" title="Andar">⬆︎</button>
          <button class="cmd-btn" data-cmd="TURN_RIGHT" title="Girar direita">⤵︎</button>

          <!-- F0..F2 — habilitar somente se existir no json -->
          <?php
            $functionNames = array_map(function($f){ return $f['name'] ?? ''; }, $functions);
            for ($i=0;$i<3;$i++){
                $fname = "F{$i}";
                $enabled = in_array($fname, $functionNames);
                echo '<button class="cmd-btn" data-cmd="CALL_'.$fname.'" data-fname="'.$fname.'" '.(!$enabled ? 'disabled' : '').'>'.$fname.'</button>';
            }
          ?>

          <!-- Rollers (cores) -->
          <button class="cmd-btn" data-cmd="PAINT_RED" title="Rolo Vermelho">🟥</button>
          <button class="cmd-btn" data-cmd="PAINT_GREEN" title="Rolo Verde">🟩</button>
          <button class="cmd-btn" data-cmd="PAINT_BLUE" title="Rolo Azul">🟦</button>
        </div>

        <div style="margin-top:8px;font-size:0.9rem;color:#6b7280">
          Clique em um slot da função (à direita) e depois em um comando para preencher.
        </div>
      </div>

      <!-- funções -->
      <div class="controls-column" style="flex:1;">
        <h4>Funções</h4>
        <div class="fn-list" id="functionsList">
          <?php
            // only render functions from JSON
            foreach ($functions as $fn) {
                $fname = e($fn['name'] ?? 'F?');
                $fsize = (int)($fn['size'] ?? 0);
                echo '<div class="function" data-fname="'.$fname.'" data-fsize="'.$fsize.'">';
                echo '<div class="fn-title" style="font-weight:700">'.$fname.' <small style="font-weight:600">['.$fsize.']</small></div>';
                echo '<div class="fn-slots">';
                for ($i=0;$i<$fsize;$i++){
                    echo '<button class="fn-slot" data-index="'.$i.'" data-empty="1">▭</button>';
                }
                echo '</div>';
                echo '</div>';
            }
          ?>
        </div>

        <!-- sequência / execução simples -->
        <div class="sequence-area" style="margin-top:16px;">
          <div class="sequence-strip" id="sequenceStrip" aria-live="polite" style="min-height:40px;display:flex;gap:6px;align-items:center"></div>
          <div class="sequence-controls" style="display:flex;gap:8px;align-items:center;margin-top:8px">
            <button id="playBtn" class="btn small">Play</button>
            <button id="pauseBtn" class="btn small">Pause</button>
            <button id="resetBtn" class="btn small">Reset</button>
            <div class="seq-info" id="seqInfo" style="margin-left:12px;color:#6b7280"></div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- pass data for JS -->
  <script>
    window.LEVEL_DATA = <?php echo json_encode([
      'matrix' => $matrix,
      'start' => $startPos,
      'functions' => $functions,
      'cellSize' => $cellSize,
      'level_json_raw' => $level_json_string
    ], JSON_UNESCAPED_UNICODE); ?>;
  </script>

  <script src="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/js/level.js'; ?>"></script>
</body>
</html>
