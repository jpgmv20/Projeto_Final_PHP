<?php
// view/level_editor.php
require_once __DIR__ . '/../header.php'; // mantém header igual ao level.php

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/*
 Accept an existing level JSON from POST['select_level'] or GET['select_level'] (for editing).
 If none provided, initialize a default editable level.
*/
$raw = $_POST['select_level'] ?? ($_GET['select_level'] ?? null);
$config = null;
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $config = $decoded;
    } else {
        // if invalid JSON, ignore and start fresh
        $raw = null;
    }
}

if (!$config) {
    // default editable level 5x5
    $config = [
        'title' => 'Nova Fase',
        'difficulty' => 'easy',
        'grid_cell_size' => 48,
        'matrix' => array_map(function($r){
            return array_map(function($c){ return ['color'=>'none','symbol'=>'none']; }, range(0,4));
        }, range(0,4)), // 5x5
        'functions' => [
            ['name'=>'F0','size'=>5],
            ['name'=>'F1','size'=>3],
        ]
    ];
    $raw = json_encode($config, JSON_UNESCAPED_UNICODE);
}

// normalize
$title = $config['title'] ?? 'Untitled Level';
$difficulty = $config['difficulty'] ?? 'easy';
$cellSize = (int)($config['grid_cell_size'] ?? 48);
$matrix = $config['matrix'] ?? [];
$functions = $config['functions'] ?? [];

// Ensure matrix is rectangular and well-formed
if (!is_array($matrix) || count($matrix) === 0) {
    $matrix = array_map(function($r){ return array_map(function($c){ return ['color'=>'none','symbol'=>'none']; }, range(0,4)); }, range(0,4));
}
$rows = count($matrix);
$cols = is_array($matrix[0]) ? count($matrix[0]) : 0;
for ($r=0;$r<$rows;$r++){
    if (!is_array($matrix[$r])) $matrix[$r] = array_fill(0, $cols, ['color'=>'none','symbol'=>'none']);
    else {
        for ($c=0;$c<$cols;$c++){
            if (!isset($matrix[$r][$c]) || !is_array($matrix[$r][$c])) $matrix[$r][$c] = ['color'=>'none','symbol'=>'none'];
            // normalize keys
            $matrix[$r][$c]['color'] = strtolower($matrix[$r][$c]['color'] ?? 'none');
            $matrix[$r][$c]['symbol'] = strtolower($matrix[$r][$c]['symbol'] ?? 'none');
        }
    }
}

// Ensure functions array consistent: name 'F0'.. and size numeric
$functionsNorm = [];
if (is_array($functions) && count($functions)>0) {
    foreach ($functions as $i => $f) {
        $name = isset($f['name']) ? (string)$f['name'] : ('F' . $i);
        $size = isset($f['size']) ? max(0, (int)$f['size']) : 0;
        $functionsNorm[] = ['name' => $name, 'size' => $size];
    }
} else {
    // default two functions
    $functionsNorm = [['name'=>'F0','size'=>5],['name'=>'F1','size'=>3]];
}
$functions = $functionsNorm;

// pass JSON string for client-side
$level_json_string = $raw ?? json_encode(['title'=>$title,'difficulty'=>$difficulty,'grid_cell_size'=>$cellSize,'matrix'=>$matrix,'functions'=>$functions], JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Level Editor — <?= e($title) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/level.css'; ?>">
  <style>
    /* reutiliza (e compatibiliza) com level.php */
    .level-wrap{max-width:1100px;margin:0 auto;padding:18px}
    .level-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
    .level-title{font-size:1.6rem;font-weight:700}
    .level-diff{padding:6px 10px;border-radius:9999px;color:#fff;font-weight:700}

    .board-area{display:flex;justify-content:center;margin:18px 0}
    .board{background:transparent;padding:8px;border-radius:8px}
    .grid{display:grid;gap:6px}

    .cell{box-sizing:border-box;width:<?= $cellSize ?>px;height:<?= $cellSize ?>px;border-radius:8px;display:flex;align-items:center;justify-content:center;
          box-shadow:0 6px 12px rgba(10,20,40,0.04);border:1px solid rgba(0,0,0,0.04); font-weight:700; font-size:18px; color:rgba(0,0,0,0.85);cursor:pointer}
    .c-none{background:#f3f4f6}
    .c-blue{background:#60a5fa}
    .c-green{background:#34d399}
    .c-red{background:#f87171}

    .cell .sym{font-size:18px;line-height:1;margin:0;padding:0}

    .controls-below{display:flex;gap:20px;align-items:flex-start;justify-content:space-between;margin-top:20px;flex-wrap:wrap}
    .controls-column{display:flex;flex-direction:column;gap:12px;min-width:220px;flex:1}
    .palette-grid{display:grid;grid-template-columns:repeat(5,48px);gap:8px}
    .cmd-btn{width:48px;height:48px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);background:#fff;cursor:pointer;font-size:18px}
    .fn-list { display:flex; flex-direction:column; gap:12px; width:100%; }
    .function { display:flex; flex-direction:column; gap:8px; padding:8px; border:1px solid rgba(0,0,0,0.06); border-radius:8px; background:#fff;}
    .fn-slots { display:flex; gap:6px; flex-wrap:wrap; margin-top:6px; }
    .fn-slot{width:34px;height:34px;border-radius:6px;border:1px dashed rgba(0,0,0,0.08);background:#fff;cursor:default;display:flex;align-items:center;justify-content:center}
    .small { padding:6px 10px; border-radius:6px; border:1px solid rgba(0,0,0,0.08); background:#fff; cursor:pointer; }

    .editor-meta { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .editor-meta input[type="text"], .editor-meta select, .editor-meta input[type="number"] { padding:8px; border-radius:6px; border:1px solid rgba(0,0,0,0.08); }

    .preview-thumb { width:120px; height:120px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fff; font-size:12px; color:#6b7280; }
    @media (max-width:900px){
      .controls-below{flex-direction:column}
      .palette-grid{grid-template-columns:repeat(5,40px)}
      .cmd-btn{width:40px;height:40px}
    }
  </style>
</head>
<body class="<?php echo $_SESSION['config']['tema'] ?? "" ?>">
  <div style="height: var(--header-height);"></div>

  <main class="level-wrap">
    <form id="editorForm" action="post.php" method="post">
      <!-- hidden field that will contain the JSON to be sent to post.php -->
      <input type="hidden" name="select_level" id="select_level">

      <div class="level-head">
        <div style="display:flex;flex-direction:column;gap:6px;">
          <div class="level-title">Editor de Fase — <input id="levelTitle" name="level_title" type="text" value="<?= e($title) ?>" style="font-size:1rem;padding:6px;border-radius:6px;border:1px solid rgba(0,0,0,0.08)"></div>
          <div class="editor-meta">
            <label>
              Dificuldade:
              <select id="levelDifficulty" name="level_difficulty">
                <option value="easy" <?= $difficulty==='easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $difficulty==='medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $difficulty==='hard' ? 'selected' : '' ?>>Hard</option>
                <option value="insane" <?= $difficulty==='insane' ? 'selected' : '' ?>>Insane</option>
              </select>
            </label>

            <label>
              Tamanho da célula:
              <input id="cellSizeInput" type="number" min="24" max="128" value="<?= e($cellSize) ?>">
            </label>

            <label>
              Linhas:
              <input id="rowsInput" type="number" min="1" max="40" value="<?= count($matrix) ?: 5 ?>">
            </label>

            <label>
              Colunas:
              <input id="colsInput" type="number" min="1" max="40" value="<?= (count($matrix)>0 ? count($matrix[0]) : 5) ?>">
            </label>

            <button type="button" id="resizeBtn" class="small">Redimensionar Grade</button>

            <div style="margin-left:12px;display:flex;gap:8px;align-items:center">
              <div class="preview-thumb" id="previewThumb" title="Preview da fase">
                <canvas id="previewCanvas" width="120" height="120" style="display:block"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
          <div>
            <button type="button" id="backBtn" class="small" onclick="location.href='../index.php'">Voltar sem salvar</button>
          </div>
          <div style="display:flex;gap:8px">
            <button type="button" id="confirmBtn" class="small">Confirmar e retornar</button>
          </div>
        </div>
      </div>

      <!-- BOARD -->
      <div class="board-area">
        <div class="board" role="region" aria-label="Editor da Matriz">
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
                      $sym = '';
                      if ($symbol === 'star') $sym = '★';
                      elseif ($symbol === 'play' || $symbol === 'player') $sym = '▶';

                      echo '<div class="cell ' . e($cls) . '"'
                      . ' data-row="' . $r . '"'
                      . ' data-col="' . $c . '"'
                      . ' data-color="' . e($color) . '"'
                      . ' data-symbol="' . e($symbol) . '">';
                      if ($sym !== '') echo '<span class="sym">' . e($sym) . '</span>';
                      echo '</div>';
                  }
              }
            ?>
          </div>
        </div>
      </div>

      <!-- CONTROLS -->
      <div class="controls-below" role="region" aria-label="Controles da fase">
        <div class="controls-column" style="max-width:420px;">
          <h4>Paleta</h4>
          <div class="palette-grid" id="palette">
            <!-- apenas cores e símbolos: sem comandos de função -->
            <button class="cmd-btn" data-tool="PAINT_BLUE" title="Pintar azul">🟦</button>
            <button class="cmd-btn" data-tool="PAINT_GREEN" title="Pintar verde">🟩</button>
            <button class="cmd-btn" data-tool="PAINT_RED" title="Pintar vermelho">🟥</button>

            <button class="cmd-btn" data-tool="SYMBOL_STAR" title="Colocar estrela">★</button>
            <button class="cmd-btn" data-tool="SYMBOL_PLAY" title="Player">▶</button>
          </div>

          <div style="margin-top:8px;font-size:0.9rem;color:#6b7280">
            Clique em uma célula para alterar cor/símbolo. Segure <kbd>SHIFT</kbd> ao clicar para alternar apenas o símbolo.
          </div>
        </div>

        <div class="controls-column" style="flex:1;">
          <h4>Funções</h4>

          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <label style="display:flex;gap:6px;align-items:center">
              Nº de funções:
              <input id="numFunctions" type="number" min="0" max="12" value="<?= count($functions) ?>" style="width:80px;padding:6px;border-radius:6px;border:1px solid rgba(0,0,0,0.08)">
            </label>
            <button id="applyNumFns" type="button" class="small">Aplicar</button>
          </div>

          <div class="fn-list" id="functionsList">
            <!-- JS irá popular -->
          </div>

        </div>
      </div>
    </form>
  </main>

  <script>
    // initial data from PHP
    const INIT = <?php echo json_encode([
      'matrix' => $matrix,
      'functions' => $functions,
      'cellSize' => $cellSize,
      'title' => $title,
      'difficulty' => $difficulty,
      'level_json_raw' => $level_json_string
    ], JSON_UNESCAPED_UNICODE); ?>;

    // state
    let state = {
      title: INIT.title || 'Nova Fase',
      difficulty: INIT.difficulty || 'easy',
      cellSize: INIT.cellSize || 48,
      matrix: INIT.matrix || [[]],
      functions: [], // will be normalized below into objects {name, size, slots:[]}
      selectedTool: null // e.g. 'PAINT_BLUE' or 'SYMBOL_STAR'
    };

    function makeMatrix(rows, cols) {
      const m = [];
      for (let r=0; r<rows; r++) {
        const row = [];
        for (let c=0; c<cols; c++) row.push({ color: 'none', symbol: 'none' });
        m.push(row);
      }
      return m;
    }

    // normalize incoming functions into internal state with slots array
    (function initFunctions(){
      if (Array.isArray(INIT.functions) && INIT.functions.length>0) {
        state.functions = INIT.functions.map((f,i) => {
          const size = Math.max(0, parseInt(f.size || 0, 10));
          const name = ('F' + i);
          return { name: name, size: size, slots: Array(size).fill(null) };
        });
      } else {
        // fallback default
        state.functions = [{name:'F0',size:5,slots:Array(5).fill(null)},{name:'F1',size:3,slots:Array(3).fill(null)}];
      }
    })();

    // ensure matrix valid
    if (!Array.isArray(state.matrix) || state.matrix.length === 0) state.matrix = makeMatrix(5,5);
    if (!Array.isArray(state.matrix[0])) state.matrix = makeMatrix(5,5);

    // DOM refs
    const gridEl = document.getElementById('grid');
    const rowsInput = document.getElementById('rowsInput');
    const colsInput = document.getElementById('colsInput');
    const resizeBtn = document.getElementById('resizeBtn');
    const cellSizeInput = document.getElementById('cellSizeInput');
    const levelTitle = document.getElementById('levelTitle');
    const levelDifficulty = document.getElementById('levelDifficulty');
    const functionsList = document.getElementById('functionsList');
    const numFunctionsInput = document.getElementById('numFunctions');
    const applyNumFnsBtn = document.getElementById('applyNumFns');
    const confirmBtn = document.getElementById('confirmBtn');
    const select_level = document.getElementById('select_level');
    const previewCanvas = document.getElementById('previewCanvas');

    // render grid
    function renderGrid() {
      const rows = state.matrix.length;
      const cols = state.matrix[0].length;
      gridEl.style.gridTemplateColumns = `repeat(${cols}, ${state.cellSize}px)`;
      gridEl.innerHTML = '';
      for (let r=0;r<rows;r++){
        for (let c=0;c<cols;c++){
          const cell = state.matrix[r][c];
          const el = document.createElement('div');
          el.className = 'cell ' + clsForColor(cell.color);
          el.dataset.row = r; el.dataset.col = c; el.dataset.color = cell.color; el.dataset.symbol = cell.symbol;
          if (cell.symbol === 'star') el.innerHTML = '<span class="sym">★</span>';
          if (cell.symbol === 'play' || cell.symbol === 'player') el.innerHTML = '<span class="sym">▶</span>';
          el.addEventListener('click', onCellClick);
          gridEl.appendChild(el);
        }
      }
      drawPreview();
    }

    function clsForColor(c){
      if (c === 'blue') return 'c-blue';
      if (c === 'green') return 'c-green';
      if (c === 'red') return 'c-red';
      return 'c-none';
    }

    function onCellClick(ev){
      const el = ev.currentTarget;
      const r = parseInt(el.dataset.row,10);
      const c = parseInt(el.dataset.col,10);
      const cell = state.matrix[r][c];

      // painting/symbol logic
      if (state.selectedTool && state.selectedTool.startsWith('PAINT_')) {
        const color = state.selectedTool.split('_')[1].toLowerCase();
        state.matrix[r][c].color = color;
        renderGrid();
        return;
      }
      if (state.selectedTool && state.selectedTool.startsWith('SYMBOL_')) {
        const sym = state.selectedTool.split('_')[1].toLowerCase();
        state.matrix[r][c].symbol = sym === 'play' ? 'play' : (sym === 'player' ? 'player' : (sym==='star'?'star':'none'));
        renderGrid();
        return;
      }

      // shift toggles symbol only
      if (ev.shiftKey) {
        cell.symbol = nextSymbol(cell.symbol);
        renderGrid();
        return;
      }

      // otherwise cycle color
      cell.color = nextColor(cell.color);
      renderGrid();
    }

    function nextSymbol(sym) {
      if (sym === 'none') return 'star';
      if (sym === 'star') return 'play';
      return 'none';
    }
    function nextColor(col) {
      if (col === 'none') return 'blue';
      if (col === 'blue') return 'green';
      if (col === 'green') return 'red';
      return 'none';
    }

    // palette clicks (only colors and symbols)
    document.getElementById('palette').addEventListener('click', (ev) => {
      const btn = ev.target.closest('button');
      if (!btn) return;
      const tool = btn.dataset.tool;
      state.selectedTool = tool;
      // visual feedback
      Array.from(document.querySelectorAll('#palette .cmd-btn')).forEach(b => b.style.boxShadow = '');
      btn.style.boxShadow = 'inset 0 0 0 2px rgba(11,102,178,0.12)';
    });

    // render functions list from state.functions (slots are visual only)
    function renderFunctions() {
      functionsList.innerHTML = '';
      state.functions.forEach((fn, fnIndex) => {
        const container = document.createElement('div');
        container.className = 'function';
        const titleRow = document.createElement('div');
        titleRow.style.display = 'flex';
        titleRow.style.alignItems = 'center';
        titleRow.style.justifyContent = 'space-between';

        const left = document.createElement('div');
        left.innerHTML = `<strong>${eHTML(fn.name)}</strong>`;

        const right = document.createElement('div');
        right.innerHTML = `Tamanho máximo: <input type="number" min="0" max="50" value="${fn.size}" data-fnindex="${fnIndex}" class="fn-size-input" style="width:80px;padding:6px;border-radius:6px;border:1px solid rgba(0,0,0,0.08)">`;

        titleRow.appendChild(left);
        titleRow.appendChild(right);
        container.appendChild(titleRow);

        const slotsWrap = document.createElement('div');
        slotsWrap.className = 'fn-slots';

        // ensure slots length equals size (slots are visual placeholders only)
        if (!Array.isArray(fn.slots) || fn.slots.length !== fn.size) fn.slots = Array(fn.size).fill(null);

        fn.slots.forEach((slot, i) => {
          const b = document.createElement('div');
          b.className = 'fn-slot';
          b.title = 'Slot (apenas visual no editor)';
          b.innerText = slot ? slot : '▭';
          slotsWrap.appendChild(b);
        });

        container.appendChild(slotsWrap);

        functionsList.appendChild(container);
      });

      // wire fn-size inputs change
      Array.from(document.querySelectorAll('.fn-size-input')).forEach(inp => {
        inp.addEventListener('change', (e) => {
          const idx = parseInt(e.target.dataset.fnindex,10);
          let val = Math.max(0, Math.min(50, parseInt(e.target.value || 0,10)));
          e.target.value = val;
          // adjust state.functions[idx].size and slots array
          state.functions[idx].size = val;
          if (!Array.isArray(state.functions[idx].slots)) state.functions[idx].slots = [];
          if (state.functions[idx].slots.length > val) state.functions[idx].slots = state.functions[idx].slots.slice(0,val);
          else while (state.functions[idx].slots.length < val) state.functions[idx].slots.push(null);
          renderFunctions(); // re-render to refresh slots
        });
      });
    }

    // apply number of functions
    applyNumFnsBtn.addEventListener('click', () => {
      const n = Math.max(0, Math.min(12, parseInt(numFunctionsInput.value || 0, 10)));
      const newFns = [];
      for (let i=0;i<n;i++){
        const existing = state.functions[i];
        if (existing) {
          // keep size if present
          const size = existing.size || 0;
          newFns.push({ name: 'F' + i, size: size, slots: Array(size).fill(null) });
        } else {
          newFns.push({ name: 'F' + i, size: 3, slots: Array(3).fill(null) });
        }
      }
      state.functions = newFns;
      renderFunctions();
    });

    // resizing grid
    resizeBtn.addEventListener('click', () => {
      const rows = Math.max(1, Math.min(40, parseInt(rowsInput.value || 5,10)));
      const cols = Math.max(1, Math.min(40, parseInt(colsInput.value || 5,10)));
      const newM = makeMatrix(rows, cols);
      for (let r=0;r<Math.min(rows, state.matrix.length); r++){
        for (let c=0;c<Math.min(cols, state.matrix[0].length); c++){
          newM[r][c] = state.matrix[r][c];
        }
      }
      state.matrix = newM;
      renderGrid();
    });

    // cell size change
    cellSizeInput.addEventListener('change', () => {
      const v = Math.max(24, Math.min(128, parseInt(cellSizeInput.value || 48,10)));
      state.cellSize = v;
      // update CSS size for .cell elements
      document.querySelectorAll('.cell').forEach(el => { el.style.width = v + 'px'; el.style.height = v + 'px'; });
      renderGrid();
    });

    // Confirm: build JSON and submit via POST to post.php
    confirmBtn.addEventListener('click', () => {
      const fOut = state.functions.map(fn => ({ name: fn.name, size: fn.size }));
      const payload = {
        title: (levelTitle.value || state.title),
        difficulty: (levelDifficulty.value || state.difficulty),
        grid_cell_size: state.cellSize,
        matrix: state.matrix,
        functions: fOut
      };
      const json = JSON.stringify(payload);
      select_level.value = json;
      document.getElementById('editorForm').submit();
    });

    // preview drawing (tiny canvas)
    function drawPreview(){
      const canvas = previewCanvas;
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      const rows = state.matrix.length;
      const cols = state.matrix[0].length;
      const w = canvas.width, h = canvas.height;
      ctx.clearRect(0,0,w,h);
      const cellW = Math.max(2, Math.floor(w/cols));
      const cellH = Math.max(2, Math.floor(h/rows));
      for (let r=0;r<rows;r++){
        for (let c=0;c<cols;c++){
          const x = c*cellW;
          const y = r*cellH;
          const col = state.matrix[r][c].color || 'none';
          if (col === 'blue') ctx.fillStyle = '#60a5fa';
          else if (col === 'green') ctx.fillStyle = '#34d399';
          else if (col === 'red') ctx.fillStyle = '#f87171';
          else ctx.fillStyle = '#f3f4f6';
          ctx.fillRect(x+1,y+1,cellW-2,cellH-2);

          // draw simple star/player marker if present
          const sym = state.matrix[r][c].symbol || 'none';
          if (sym === 'star') {
            ctx.fillStyle = '#fef3c7';
            ctx.font = Math.max(8, Math.floor(Math.min(cellW,cellH)*0.7)) + 'px sans-serif';
            ctx.fillText('★', x + Math.floor(cellW*0.2), y + Math.floor(cellH*0.8));
          } else if (sym === 'play' || sym === 'player') {
            ctx.fillStyle = '#ffffff';
            ctx.font = Math.max(8, Math.floor(Math.min(cellW,cellH)*0.7)) + 'px sans-serif';
            ctx.fillText('▶', x + Math.floor(cellW*0.25), y + Math.floor(cellH*0.75));
          }
        }
      }
    }

    // initialization render
    (function init(){
      // set inputs from INIT
      document.getElementById('rowsInput').value = state.matrix.length;
      document.getElementById('colsInput').value = state.matrix[0].length;
      document.getElementById('cellSizeInput').value = state.cellSize;
      document.getElementById('numFunctions').value = state.functions.length;
      document.getElementById('levelTitle').value = state.title;
      document.getElementById('levelDifficulty').value = state.difficulty;
      renderFunctions();
      renderGrid();
    })();

    // helpers
    function eHTML(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

    // deselect palette when click outside
    document.addEventListener('click', (ev) => {
      if (!ev.target.closest('#palette')) {
        state.selectedTool = null;
        Array.from(document.querySelectorAll('#palette .cmd-btn')).forEach(b => b.style.boxShadow = '');
      }
    });

    // prevent accidental form submission
    document.getElementById('editorForm').addEventListener('submit', (e) => {
      if (!select_level.value) {
        // prevent native submit; submission only from confirmBtn
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
