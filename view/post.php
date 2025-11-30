<?php
// view/post.php
require_once __DIR__ . '/../services/config.php';
session_start();

/* proteção: exige usuário logado (se foi acessado por URL direta, volta ao index) */
if (empty($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

/* flash messages (simples) */
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* receive select_level if editor returned it */
$select_level_raw = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_level']) && trim($_POST['select_level']) !== '') {
    $select_level_raw = trim($_POST['select_level']);
} elseif (isset($_GET['select_level']) && trim($_GET['select_level']) !== '') {
    $select_level_raw = trim($_GET['select_level']);
}

/* helper escape */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Postar — Projeto</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/post.css' ?>">
  <style>
    .phase-button { display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); background:#fff; cursor:pointer; }
    .phase-thumb { width:140px; height:140px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f8fafc; }
    .muted { color:#6b7280; font-size:0.9rem; }
    .flash.success{padding:10px;background:#ecfdf5;color:#064e3b;border-radius:8px;margin-bottom:12px}
    .flash.error{padding:10px;background:#fff1f2;color:#7f1d1d;border-radius:8px;margin-bottom:12px}
    .form-row{margin-bottom:12px}
    .input, select { padding:8px;border-radius:6px;border:1px solid rgba(0,0,0,0.08); width:100%; box-sizing:border-box; }
    .btn--primary{background:#0b66b2;color:#fff;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .btn--link{background:transparent;border:none;color:#0b66b2;text-decoration:underline;cursor:pointer}
  </style>
</head>
<body class="<?php $config = $_SESSION['config']['tema'] ?? ""; echo e($config) ?>">
  <main class="main post-page" style="max-width:900px;margin:24px auto;padding:18px">
    <div class="post-wrapper">
      <h1>Criar novo post</h1>

      <?php if ($flash_success): ?><div class="flash success"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div class="flash error"><?= e($flash_error) ?></div><?php endif; ?>

      <!-- Main form: envia para o controller que faz a gravação e redireciona -->
      <form action="../controller/controller_new_level.php" method="post">
        <div class="form-row">
          <label><strong>Título</strong></label>
          <input class="input" type="text" name="title" maxlength="200" value="<?= e($_POST['title'] ?? '') ?>" required>
        </div>

        <div class="form-row">
          <label><strong>Dificuldade</strong></label>
          <select name="difficulty" class="input">
            <option value="easy" <?= (isset($_POST['difficulty']) && $_POST['difficulty']==='easy') ? 'selected' : '' ?>>Easy</option>
            <option value="medium" <?= (isset($_POST['difficulty']) && $_POST['difficulty']==='medium') ? 'selected' : '' ?>>Medium</option>
            <option value="hard" <?= (isset($_POST['difficulty']) && $_POST['difficulty']==='hard') ? 'selected' : '' ?>>Hard</option>
            <option value="insane" <?= (isset($_POST['difficulty']) && $_POST['difficulty']==='insane') ? 'selected' : '' ?>>Insane</option>
          </select>
        </div>

        <div class="form-row">
          <label><strong>Fase</strong></label>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <?php if (!empty($select_level_raw)): ?>
              <div class="phase-thumb" id="phaseThumbWrap">
                <canvas id="phaseCanvas" width="140" height="140" style="display:block"></canvas>
              </div>

              <!-- botão que abre o editor via POST (cria form temporário em JS) -->
              <button type="button" class="phase-button" id="editLevelBtn" title="Editar fase">Editar fase</button>

              <!-- este hidden fica DENTRO do form principal para que o controller receba o select_level -->
              <input type="hidden" name="select_level" id="select_level_hidden" value="<?= e($select_level_raw) ?>">
            <?php else: ?>
              <a href="level_editor.php" class="phase-button" style="text-decoration:none">Criar fase</a>
            <?php endif; ?>
            <div class="muted">Ao criar a fase você volta para esta tela; confirme e publique para salvar.</div>
          </div>
        </div>

        <div style="margin-top:12px;">
          <!-- action name still sent so controller knows it's publish -->
          <button class="btn--primary" type="submit" name="action" value="publish">Publicar</button>
          <a class="btn--link" href="../index.php">Voltar</a>
        </div>
      </form>

    </div>
  </main>

  <script>
    // funçao que cria um form temporário e envia por POST para level_editor.php com o JSON da fase
    (function(){
      const btn = document.getElementById('editLevelBtn');
      if (!btn) return;
      btn.addEventListener('click', function(){
        const jsonField = document.getElementById('select_level_hidden');
        const levelJson = jsonField ? jsonField.value : '';
        // cria form temporário
        const f = document.createElement('form');
        f.method = 'post';
        f.action = 'level_editor.php';
        f.style.display = 'none';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'select_level';
        input.value = levelJson;
        f.appendChild(input);

        document.body.appendChild(f);
        f.submit();
      });
    })();

    // draw preview if select_level present
    (function(){
      const raw = <?= json_encode($select_level_raw !== null ? $select_level_raw : null, JSON_UNESCAPED_UNICODE) ?>;
      if (!raw) return;
      let data;
      try { data = JSON.parse(raw); } catch(e){ return; }
      const matrix = data.matrix || [];
      const canvas = document.getElementById('phaseCanvas');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      const w = canvas.width, h = canvas.height;
      ctx.clearRect(0,0,w,h);

      const rows = matrix.length || 1;
      const cols = (rows>0 && Array.isArray(matrix[0])) ? matrix[0].length : 1;
      const cellW = Math.max(2, Math.floor(w/cols));
      const cellH = Math.max(2, Math.floor(h/rows));

      for (let r=0; r<rows; r++){
        for (let c=0; c<cols; c++){
          const cell = (matrix[r] && matrix[r][c]) ? matrix[r][c] : { color: 'none' };
          const col = (cell.color || 'none');
          if (col === 'blue') ctx.fillStyle = '#60a5fa';
          else if (col === 'green') ctx.fillStyle = '#34d399';
          else if (col === 'red') ctx.fillStyle = '#f87171';
          else ctx.fillStyle = '#f3f4f6';
          const x = c*cellW;
          const y = r*cellH;
          ctx.fillRect(x+1,y+1,cellW-2,cellH-2);

          // draw simple symbol markers if present
          const sym = (cell.symbol || '').toLowerCase();
          if (sym === 'star' || sym === 'play' || sym === 'player') {
            ctx.fillStyle = '#000000';
            ctx.font = Math.max(10, Math.floor(Math.min(cellW, cellH) * 0.5)) + 'px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const label = (sym === 'star') ? '★' : (sym === 'play' || sym === 'player') ? '▶' : '';
            ctx.fillText(label, x + cellW/2, y + cellH/2 + 1);
          }
        }
      }
    })();
  </script>
</body>
</html>
