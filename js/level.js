// js/level.js (VERSÃO CORRIGIDA)
// Espera window.LEVEL_DATA ser definido no PHP
(function(){
  const LD = window.LEVEL_DATA || {};
  const matrix = JSON.parse(JSON.stringify(LD.matrix || [])); // clone
  const start = LD.start || {r:0,c:0};
  const functionsDef = LD.functions || [];
  const cellSize = LD.cellSize || 48;

  // MODEL
  const funcs = {}; // { "F0": ["FORWARD","TURN_LEFT", ...] }
  functionsDef.forEach(f => {
    const name = f.name || 'F?';
    const size = parseInt(f.size || 0,10);
    funcs[name] = new Array(size).fill(null);
  });

  // UI nodes (guardados com null-check)
  const gridEl = document.getElementById('grid');
  const functionsList = document.getElementById('functionsList');
  const paletteBtns = Array.from(document.querySelectorAll('.palette-grid .cmd-btn'));
  const sequenceStrip = document.getElementById('sequenceStrip');
  const playBtn = document.getElementById('playBtn');
  const pauseBtn = document.getElementById('pauseBtn');
  const resetBtn = document.getElementById('resetBtn');
  const seqInfo = document.getElementById('seqInfo');

  // selected slot
  let selectedSlot = null; // { fname, index, el }

  // player state
  const player = {
    r: start.r || 0,
    c: start.c || 0,
    dir: 0, // 0=up,1=right,2=down,3=left
    color: null, // current roller color
    collected: 0
  };

  // find stars count
  function countStars(){
    let s=0;
    for (let r=0;r<matrix.length;r++){
      for (let c=0;c<(matrix[r]||[]).length;c++){
        const cell = matrix[r][c] || {};
        if ((cell.symbol||'') === 'star') s++;
      }
    }
    return s;
  }

  // render functions slots from model -> DOM
  function renderFunctions(){
    if (!functionsList) return;
    document.querySelectorAll('#functionsList .function').forEach(fnEl=>{
      const fname = fnEl.getAttribute('data-fname');
      const slots = fnEl.querySelectorAll('.fn-slot');
      slots.forEach((slotEl, idx)=>{
        const val = (funcs[fname] && funcs[fname][idx]) ? funcs[fname][idx] : null;
        slotEl.textContent = val ? labelForCmd(val) : '▭';
        slotEl.dataset.empty = val ? "0" : "1";
      });
    });
  }

  // helper label for command code
  function labelForCmd(code){
    if (!code) return '▭';
    switch(code){
      case 'TURN_LEFT': return '⤴︎';
      case 'FORWARD': return '⬆︎';
      case 'TURN_RIGHT': return '⤵︎';
      case 'PAINT_RED': return 'PR';
      case 'PAINT_GREEN': return 'PG';
      case 'PAINT_BLUE': return 'PB';
      default:
        if (typeof code === 'string' && code.startsWith && code.startsWith('CALL_')) return code.replace('CALL_','');
        return code;
    }
  }

  // attach click handlers to each slot (if present)
  document.querySelectorAll('.fn-slot').forEach(slotEl=>{
    slotEl.addEventListener('click', function(e){
      // remove old selection
      document.querySelectorAll('.fn-slot.selected').forEach(n=>n.classList.remove('selected'));
      slotEl.classList.add('selected');
      selectedSlot = {
        el: slotEl,
        fname: slotEl.closest('.function').getAttribute('data-fname'),
        index: parseInt(slotEl.getAttribute('data-index'),10)
      };
      e.stopPropagation();
    });
  });

  // palette click: if a slot selected, set cmd there; else append to first empty slot of first function
  paletteBtns.forEach(btn=>{
    btn.addEventListener('click', function(){
      const cmd = btn.dataset.cmd;
      if (!cmd) return;
      // if calling a function that doesn't exist, ignore
      if (cmd.startsWith('CALL_')) {
        const fname = cmd.replace('CALL_','');
        if (!funcs.hasOwnProperty(fname)) {
          alert('Função '+fname+' não definida para este nível.');
          return;
        }
      }
      if (selectedSlot) {
        funcs[selectedSlot.fname][selectedSlot.index] = cmd;
        selectedSlot.el.textContent = labelForCmd(cmd);
        selectedSlot.el.dataset.empty = "0";
      } else {
        // find first function with empty slot and fill
        let placed = false;
        for (let fname in funcs) {
          for (let i=0;i<funcs[fname].length;i++){
            if (!funcs[fname][i]) {
              funcs[fname][i] = cmd;
              // update DOM
              const fnEl = document.querySelector('.function[data-fname="'+fname+'"]');
              if (fnEl) {
                const slotBtn = fnEl.querySelector('.fn-slot[data-index="'+i+'"]');
                if (slotBtn) {
                  slotBtn.textContent = labelForCmd(cmd);
                  slotBtn.dataset.empty = "0";
                }
              }
              placed = true;
              break;
            }
          }
          if (placed) break;
        }
        if (!placed) {
          alert('Não há espaço livre nas funções para adicionar esse comando.');
        }
      }
      updateSequencePreview();
    });
  });

  // click outside removes selection
  document.addEventListener('click', function(e){
    if (!e.target.closest('.fn-slot')) {
      document.querySelectorAll('.fn-slot.selected').forEach(n=>n.classList.remove('selected'));
      selectedSlot = null;
    }
  });

  // render grid initial
  function renderGrid(){
    if (!gridEl) return;
    const cells = gridEl.querySelectorAll('.cell');
    cells.forEach(cel => {
      const r = parseInt(cel.dataset.row,10);
      const c = parseInt(cel.dataset.col,10);
      const cell = (matrix[r] && matrix[r][c]) ? matrix[r][c] : {};
      cel.dataset.color = (cell.color || 'none');
      cel.dataset.symbol = (cell.symbol || 'none');

      // ensure a .sym span exists (create if missing)
      let symspan = cel.querySelector('.sym');
      if (!symspan) {
        symspan = document.createElement('span');
        symspan.className = 'sym';
        symspan.style.pointerEvents = 'none';
        // keep existing text if any
        cel.appendChild(symspan);
      }

      // set symbol text (use ▶ for player)
      if ((cell.symbol || '') === 'star') {
        symspan.textContent = '★';
      } else if ((cell.symbol || '') === 'play' || (cell.symbol || '') === 'player') {
        symspan.textContent = '▶';
      } else {
        // empty symbol
        symspan.textContent = '';
      }
    });
    // place player marker
    updatePlayerMarker();
  }

  function updatePlayerMarker(){
    if (!gridEl) return;
    // remove existing player marks
    document.querySelectorAll('.cell .player-indicator').forEach(el=>el.remove());
    const sel = document.querySelector(`.cell[data-row="${player.r}"][data-col="${player.c}"]`);
    if (sel) {
      // add a small overlay for player
      const pi = document.createElement('div');
      pi.className = 'player-indicator';
      pi.style.position = 'absolute';
      pi.style.pointerEvents = 'none';
      pi.style.fontWeight = '700';
      pi.style.fontSize = '14px';
      pi.style.transform = 'translateY(-24%)';
      pi.textContent = '▶';
      sel.style.position = 'relative';
      sel.appendChild(pi);
    }
  }

  // sequence preview: flatten main execution starting point -> F0 if exists else first available
  function buildExecutionStackSequence() {
    let startFn = Object.keys(funcs)[0] || null;
    if (funcs.hasOwnProperty('F0')) startFn = 'F0';
    if (!startFn) return [];
    const MAX_EXPAND = 300;
    const res = [];
    let steps = 0;
    const callStack = [{name:startFn, pos:0}];
    while (callStack.length && steps < MAX_EXPAND) {
      const frame = callStack[callStack.length-1];
      const farr = funcs[frame.name] || [];
      if (frame.pos >= farr.length) {
        callStack.pop();
        continue;
      }
      const cmd = farr[frame.pos];
      frame.pos++;
      if (!cmd) continue;
      if (cmd.startsWith && cmd.startsWith('CALL_')) {
        const called = cmd.replace('CALL_','');
        if (funcs.hasOwnProperty(called)) {
          callStack.push({name:called, pos:0});
        }
      } else {
        res.push(cmd);
      }
      steps++;
    }
    return res;
  }

  function updateSequencePreview() {
    if (!sequenceStrip || !seqInfo) return;
    const seq = buildExecutionStackSequence();
    sequenceStrip.innerHTML = '';
    seq.forEach((cmd, i) => {
      const div = document.createElement('div');
      div.className = 'seq-item';
      div.textContent = labelForCmd(cmd);
      sequenceStrip.appendChild(div);
    });
    seqInfo.textContent = `Steps: ${seq.length} | Stars remaining: ${countStars()}`;
  }

  // Execution engine (unchanged logic but guarded)
  let execTimer = null;
  let execQueue = [];
  let execPointer = 0;
  let isRunning = false;
  const STEP_INTERVAL = 2000;
  const MAX_STEPS = 500;

  function buildExecQueueFromStart() {
    execQueue = buildExecutionStackSequence();
    execPointer = 0;
  }

  function executeNextStep() {
    if (execPointer >= execQueue.length) {
      stopExecution();
      return;
    }
    const cmd = execQueue[execPointer++];
    // visual highlight current seq item
    if (sequenceStrip) {
      const items = sequenceStrip.querySelectorAll('.seq-item');
      items.forEach((it,i)=> it.classList.toggle('active', i===execPointer-1));
    }

    interpretCommand(cmd);
    updatePlayerMarker();
    if (seqInfo) seqInfo.textContent = `Steps: ${execQueue.length} | Step ${execPointer}/${execQueue.length} | Stars remaining: ${countStars()}`;

    if (countStars() === 0) {
      stopExecution();
      alert('Parabéns — todas as estrelas coletadas!');
    }
    if (execPointer >= MAX_STEPS) {
      stopExecution();
      alert('Limite de passos atingido.');
    }
  }

  function interpretCommand(cmd) {
    switch(cmd) {
      case 'TURN_LEFT':
        player.dir = (player.dir + 3) % 4;
        break;
      case 'TURN_RIGHT':
        player.dir = (player.dir + 1) % 4;
        break;
      case 'FORWARD':
        let nr = player.r, nc = player.c;
        if (player.dir === 0) nr--;
        else if (player.dir === 1) nc++;
        else if (player.dir === 2) nr++;
        else if (player.dir === 3) nc--;
        if (nr < 0 || nr >= matrix.length || nc < 0 || nc >= (matrix[nr]||[]).length) {
          resetLevel(true);
          return;
        }
        player.r = nr; player.c = nc;
        const cell = matrix[player.r][player.c] || {};
        const cellColor = (cell.color || 'none');
        const cellSymbol = (cell.symbol || 'none');
        if (cellColor === 'none' && cellSymbol !== 'star' && cellSymbol !== 'play' && cellSymbol !== 'player') {
          resetLevel(true);
          return;
        }
        if (cellSymbol === 'star') {
          matrix[player.r][player.c]['symbol'] = null;
          const cellEl = document.querySelector('.cell[data-row="'+player.r+'"][data-col="'+player.c+'"] .sym');
          if (cellEl) cellEl.textContent = '';
          player.collected++;
        }
        break;
      case 'PAINT_RED':
      case 'PAINT_GREEN':
      case 'PAINT_BLUE':
        const color = cmd === 'PAINT_RED' ? 'red' : (cmd === 'PAINT_GREEN' ? 'green' : 'blue');
        if (!matrix[player.r]) matrix[player.r] = [];
        if (!matrix[player.r][player.c]) matrix[player.r][player.c] = {};
        matrix[player.r][player.c]['color'] = color;
        const cel = document.querySelector('.cell[data-row="'+player.r+'"][data-col="'+player.c+'"]');
        if (cel) {
          cel.classList.remove('c-none','c-red','c-blue','c-green');
          if (color==='red') cel.classList.add('c-red'); else if (color==='green') cel.classList.add('c-green'); else if (color==='blue') cel.classList.add('c-blue');
          cel.dataset.color = color;
        }
        break;
      default:
        break;
    }
  }

  function startExecution() {
    if (isRunning) return;
    buildExecQueueFromStart();
    if (!execQueue.length) {
      alert('Nenhum comando disponível para executar.');
      return;
    }
    isRunning = true;
    execPointer = 0;
    updateSequencePreview();
    execTimer = setInterval(executeNextStep, STEP_INTERVAL);
  }

  function stopExecution() {
    isRunning = false;
    if (execTimer) { clearInterval(execTimer); execTimer = null; }
  }

  function resetLevel(withAlert=false) {
    if (withAlert) {
      alert('Nível reiniciado.');
    }
    location.reload();
  }

  // buttons (guard for null)
  if (playBtn) playBtn.addEventListener('click', ()=> { startExecution(); });
  if (pauseBtn) pauseBtn.addEventListener('click', ()=> { stopExecution(); });
  if (resetBtn) resetBtn.addEventListener('click', ()=> { resetLevel(false); });

  // initial render
  renderFunctions();
  renderGrid();
  updateSequencePreview();

  // hover effects (guard)
  document.querySelectorAll('.cell').forEach(c=>{
    c.addEventListener('mouseenter', ()=> c.style.transform = 'translateY(-3px)');
    c.addEventListener('mouseleave', ()=> c.style.transform = 'none');
  });

})();
