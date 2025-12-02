<?php
// header.php
// Cabeçalho compartilhado — normaliza avatar via get_avatar.php (por id) e faz restore de sessão via cookie remember_me

// Inicia sessão (sempre antes de qualquer output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/services/config.php';

/**
 * Normaliza a URL do avatar:
 * - Se $user_or_path for numérico (ID do usuário) -> retorna URL para controller/get_avatar.php?id=ID
 * - Se vazio -> retorna imagem fallback absoluta
 * - Se já for URL http(s) -> retorna inalterado
 * - Se for caminho relativo -> monta URL absoluta dentro do Projeto_Final_PHP
 */
if (!function_exists('avatar_url_web')) {
    function avatar_url_web(string $user_or_path = ''): string {
        $default = 'image/images.jfif';

        // se vazio devolve fallback absoluto
        if ($user_or_path === '' || $user_or_path === null) {
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
        }

        // se for apenas dígitos -> tratamos como user_id e retornamos o endpoint que serve o BLOB
        if (ctype_digit((string)$user_or_path)) {
            $id = intval($user_or_path);
            if ($id <= 0) {
                return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $default;
            }
            return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/controller/get_avatar.php?id=' . $id;
        }

        // se já for URL absoluta
        if (preg_match('#^https?://#i', $user_or_path)) {
            return $user_or_path;
        }

        // remove barras iniciais e repetições do projeto
        $p = ltrim($user_or_path, '/');
        $p = preg_replace('#^(?:Projeto_Final_PHP/)+#i', '', $p);

        return 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/' . $p;
    }
}

// -- restaurar sessão automaticamente se existir cookie remember_me --
// cookie formato esperado: "<user_id>:<token>"
if (!isset($_SESSION['id']) && isset($_COOKIE['remember_me'])) {

    // proteção: explode apenas se conter ':' e parte esquerda for numérica
    if (strpos($_COOKIE['remember_me'], ':') !== false) {
        list($raw_user_id, $token) = explode(":", $_COOKIE['remember_me'], 2);
        $user_id = intval($raw_user_id);
        if ($user_id > 0 && is_string($token) && $token !== '') {
            $token_hash = hash('sha256', $token);

            $mysqli = connect_mysql();

            if ($mysqli) {
                $sql = "
                    SELECT users.id, users.nome, users.descricao, users.email, users.config
                    FROM user_tokens
                    JOIN users ON users.id = user_tokens.user_id
                    WHERE user_tokens.user_id = ?
                      AND user_tokens.token_hash = ?
                      AND user_tokens.expires_at > NOW()
                    LIMIT 1
                ";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("is", $user_id, $token_hash);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($user = $result->fetch_assoc()) {

                            // recria a sessão (não armazenamos avatar_url aqui — usamos avatar_url_web($_SESSION['id']))
                            $_SESSION['id'] = (int)$user["id"];
                            $_SESSION['nome'] = $user["nome"];
                            $_SESSION['descricao'] = $user["descricao"];
                            $_SESSION['email'] = $user["email"];
                            // se config for JSON guardado como string no DB, decodifique; se já for array/obj, mantenha
                            $_SESSION['config'] = is_string($user["config"]) ? json_decode($user["config"], true) : $user["config"];
                        } else {
                            // token inválido → apaga o cookie
                            setcookie("remember_me", "", time() - 3600, "/");
                        }
                    } else {
                        // erro ao executar -> removemos cookie para evitar loops
                        setcookie("remember_me", "", time() - 3600, "/");
                    }
                    $stmt->close();
                }
                $mysqli->close();
            }
        } else {
            // cookie malformado -> apaga para evitar re-execuções
            setcookie("remember_me", "", time() - 3600, "/");
        }
    } else {
        // cookie sem ':' -> apaga
        setcookie("remember_me", "", time() - 3600, "/");
    }
}

?>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/index.css'; ?>" />
  <style>
    /* user menu dropdown - posicionado abaixo do avatar */
    .user-menu-wrap { position: relative; display: inline-block; }

    /* menu escondido por padrão */
    #user-menu {
      position: absolute;
      display: none;                 /* escondido por padrão */
      min-width: 180px;
      max-width: 320px;
      background: var(--surface, #ffffff);
      color: var(--text, #0b1220);
      border: 1px solid var(--border, rgba(0,0,0,0.08));
      box-shadow: 0 10px 30px rgba(2,6,23,0.12);
      border-radius: 8px;
      z-index: 1500;
      padding: 6px;
      transform-origin: top center;
      transition: transform .14s ease, opacity .14s ease;
      opacity: 0;
      transform: translateY(-6px) scale(0.98);
      left: 50%;
      top: calc(100% + 8px);
      transform: translateX(-50%) translateY(0) scale(1);
    }

    /* quando aberto */
    #user-menu.open {
      display: block;
      opacity: 1;
      transform: translateX(-50%) translateY(6px) scale(1);
    }

    #user-menu .menu-item { padding:8px; border-radius:6px; display:flex; align-items:center; gap:8px; }
    #user-menu .menu-item .label { margin-left:6px; }
    #user-menu .logout-btn { display:block; text-align:center; padding:8px 10px; border-radius:6px; text-decoration:none; color:#fff; background: linear-gradient(180deg, var(--brand-blue), var(--brand-blue-dark)); }

    /* small responsive tweak */
    @media (max-width:480px) {
      #user-menu { left: 8px; right: 8px; transform: none; top: calc(100% + 8px); }
      #user-menu.open { transform: none; }
    }

    /* estilo básico para o toggle (simplicidade) */
    .toggle input[type="checkbox"] { width: 16px; height: 16px; }
  </style>
</head>

<header class="header header--fixed" role="banner">
  <div class="container" style="display:flex; align-items:center; gap:var(--gap);">

    <!-- lado esquerdo: logo -->
    <div class="header__left">
      <a class="header__brand" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/'; ?>" aria-label="Página inicial">
        <span class="logo-mark" aria-hidden="true">PM</span>
        <span class="sr-only">Robozzle </span>
        <span>Home</span>
      </a>
    </div>

    <!-- centro: busca -->
    <div class="header__center" aria-hidden="false">
      <div class="search" role="search" aria-label="Buscar">
        <div class="search__wrap">
          <span class="search__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M21 21l-4.35-4.35"
                    stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="11" cy="11" r="6"
                      stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>

          <input
            class="search__input"
            type="search"
            name="q"
            id="globalSearch"
            placeholder="Buscar no site"
            aria-label="Buscar"
            autocomplete="off"
          />

<style>
  .search__wrap { position: relative; }
  #searchResults {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 8px);
    background: var(--surface, #ffffff);
    color: var(--text, #0b1220);
    border: 1px solid rgba(0,0,0,0.08);
    box-shadow: 0 10px 30px rgba(2,6,23,0.12);
    border-radius: 8px;
    z-index: 1200;
    max-height: 320px;
    overflow: auto;
    display: none;
    padding: 6px;
    backdrop-filter: none;
  }
  #searchResults .search-result { background: transparent; transition: background .12s; }
  #searchResults .search-result:hover { background: rgba(11,102,178,0.04); }
</style>

<div id="searchResults" class="search-results" role="listbox" aria-label="Resultados da busca"></div>

        </div>
      </div>
    </div>

    <!-- direita: nav / botões -->
    <div class="header__right" style="margin-left:auto;">
      <nav class="header__nav" role="navigation" aria-label="Navegação principal">

        <?php
          if (isset($_SESSION['id']))
          {
            $url = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/login.php';
            $url2 = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/hub.php';
            $url3 = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/post.php';
            $url4 = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/notify.php';
            $logoutUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/services/logout.php';
            $perfilUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/perfil.php';

            $username = htmlspecialchars($_SESSION['nome'] ?? 'Usuário', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            // Normaliza avatar URL com a função utilitária (passando user id)
            $stored = $_SESSION['id'] ?? '';
            $avatar_url = avatar_url_web((string)$stored);

            echo <<<HTML

            <a class="icon-btn" href="$url2" aria-label="Mensagens" title="Mensagens">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 15a2 2 0 01-2 2H7l-4 3V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </a>

            <a href="$url3">
              <button class="btn" type="button" aria-label="Novo post">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                  <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Postar</span>
              </button>
            </a>

            <div class="user-menu-wrap" style="margin-left:8px; display:inline-block; vertical-align:middle;">
              <button id="user-menu-trigger" class="icon-btn" aria-haspopup="true" aria-expanded="false" style="display:flex;align-items:center;gap:8px; background:transparent; border:0; padding:6px; cursor:pointer;">
                <span style="font-weight:bold;margin-right:6px;">$username</span>
                <span class="avatar" style="display:inline-block; width:36px; height:36px; border-radius:50%; overflow:hidden; background:var(--border);">
                  <img src="$avatar_url" alt="Avatar" style="width:100%;height:100%;object-fit:cover;display:block;">
                </span>
              </button>

              <div id="user-menu" class="user-menu" aria-hidden="true" role="menu" tabindex="-1">
                  <div class="menu-item">
                      <label class="toggle" style="display:flex;align-items:center;gap:8px;cursor:pointer">
                          <input type="checkbox" id="toggleTheme">
                          <span class="label">Tema escuro</span>
                      </label>
                  </div>

                  <div style="padding:8px;">
                    <a class="logout-btn" href="$perfilUrl">Perfil</a>
                  </div>

                  <div style="padding:8px;">
                    <a class="logout-btn" href="$logoutUrl">Logout</a>
                  </div>
              </div>
            </div>

            HTML;
          }
          else
          {
            $url = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/login.php';
            $url2 = 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/register.php';

            echo <<<HTML
              <a class="icon-btn" href="$url" aria-label="Perfil" title="Perfil">
                <button class="btn" type="button" aria-label="Login_btn">
                  <span>Login</span>
                </button>
              </a>

              <a class="icon-btn" href="$url2" aria-label="Perfil" title="Perfil">
                <button class="btn--register" type="button" aria-label="Login_btn">
                  <span>Register</span>
                </button>
              </a>

            HTML;
          }
        ?>
      </nav>
    </div>
</header>


<script>
(function(){
  const input = document.getElementById('globalSearchInput') || document.querySelector('.search__input');
  const resultsBox = document.getElementById('searchResults');
  if (!input || !resultsBox) return;

  let timer = null;
  let lastQ = '';

  function createResultNode(item) {
    const el = document.createElement('div');
    el.className = 'search-result';
    el.style.display = 'flex';
    el.style.gap = '8px';
    el.style.alignItems = 'center';
    el.style.padding = '6px';
    el.style.borderRadius = '6px';
    el.style.cursor = 'pointer';

    const avatar = document.createElement('div');
    avatar.style.width = '36px';
    avatar.style.height = '36px';
    avatar.style.borderRadius = '50%';
    avatar.style.overflow = 'hidden';
    avatar.style.flex = '0 0 36px';
    const img = document.createElement('img');
    img.src = item.avatar_url || ('<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/image/images.jfif'; ?>');
    img.alt = item.title || item.nome || '';
    img.style.width = '100%';
    img.style.height = '100%';
    img.style.objectFit = 'cover';
    avatar.appendChild(img);

    const body = document.createElement('div');
    body.style.flex = '1';
    body.style.minWidth = '0';
    const title = document.createElement('div');
    title.style.fontWeight = '700';
    title.style.fontSize = '0.95rem';
    title.style.whiteSpace = 'nowrap';
    title.style.overflow = 'hidden';
    title.style.textOverflow = 'ellipsis';
    title.textContent = item.title || item.nome || '—';
    const sub = document.createElement('div');
    sub.style.color = '#6b7280';
    sub.style.fontSize = '0.85rem';
    sub.style.whiteSpace = 'nowrap';
    sub.style.overflow = 'hidden';
    sub.style.textOverflow = 'ellipsis';
    sub.textContent = item.sub || '';

    const type = document.createElement('div');
    type.textContent = (item.type === 'level') ? 'Level' : 'Perfil';
    type.style.fontSize = '0.75rem';
    type.style.padding = '3px 8px';
    type.style.borderRadius = '999px';
    type.style.background = '#f3f4f6';
    type.style.color = '#374151';
    type.style.marginLeft = '8px';

    body.appendChild(title);
    body.appendChild(sub);

    el.appendChild(avatar);
    el.appendChild(body);
    el.appendChild(type);

    el.addEventListener('click', function(){
      if (item.type === 'level') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/level.php'; ?>';
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'select_level';
        inp.value = item.level_json || '';
        form.appendChild(inp);
        document.body.appendChild(form);
        form.submit();
      } else if (item.type === 'user') {
        window.location.href = '<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/view/perfil.php'; ?>?user_id=' + encodeURIComponent(item.id);
      }
    });

    return el;
  }

  function showResults(list) {
    resultsBox.innerHTML = '';
    if (!Array.isArray(list) || !list.length) {
      resultsBox.style.display = 'none';
      return;
    }
    list.forEach(it => resultsBox.appendChild(createResultNode(it)));
    resultsBox.style.display = 'block';
  }

  function clearResults() {
    resultsBox.innerHTML = '';
    resultsBox.style.display = 'none';
  }

  async function doSearch(q) {
    if (!q || q.trim().length < 1) {
      clearResults();
      return;
    }
    try {
      const url = '<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/services/search.php'; ?>?q=' + encodeURIComponent(q);
      const resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) { clearResults(); return; }
      const data = await resp.json();
      showResults(data.results || []);
    } catch (err) {
      console.error('Search error', err);
      clearResults();
    }
  }

  input.addEventListener('input', function(){
    const q = input.value.trim();
    if (q === lastQ) return;
    lastQ = q;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => doSearch(q), 220);
  });

  document.addEventListener('click', function(e){
    if (!resultsBox.contains(e.target) && e.target !== input) {
      clearResults();
    }
  });

  let focused = -1;
  input.addEventListener('keydown', function(e){
    const items = Array.from(resultsBox.querySelectorAll('.search-result'));
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      focused = Math.min(items.length - 1, focused + 1);
      items.forEach((it,i)=> it.style.outline = (i===focused) ? '2px solid rgba(11,102,178,0.15)' : 'none');
      items[focused].scrollIntoView({block:'nearest'});
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      focused = Math.max(0, focused - 1);
      items.forEach((it,i)=> it.style.outline = (i===focused) ? '2px solid rgba(11,102,178,0.15)' : 'none');
      items[focused].scrollIntoView({block:'nearest'});
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (focused >= 0 && items[focused]) items[focused].click();
    }
  });

  // Avatar menu + theme toggle handling (mantive seu código)
  document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.getElementById('user-menu-trigger');
    const menu = document.getElementById('user-menu');
    const toggleTheme = document.getElementById('toggleTheme');

    if (!trigger || !menu) return;

    menu.classList.remove('open');
    menu.setAttribute('aria-hidden', 'true');
    trigger.setAttribute('aria-expanded', 'false');

    function positionMenu() {
      const trigRect = trigger.getBoundingClientRect();
      const wrap = trigger.closest('.user-menu-wrap');
      const wrapRect = wrap ? wrap.getBoundingClientRect() : { left: 0, top: 0 };
      const menuRect = menu.getBoundingClientRect();

      const leftCenter = (trigRect.left + trigRect.right) / 2 - wrapRect.left;
      menu.style.left = leftCenter + 'px';
      menu.style.top = (trigRect.bottom - wrapRect.top + 8) + 'px';

      const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
      const absoluteLeft = wrapRect.left + leftCenter - (menuRect.width / 2 || 90);

      if (absoluteLeft + menuRect.width > viewportWidth - 8) {
        const overflow = (absoluteLeft + menuRect.width) - (viewportWidth - 8);
        menu.style.left = (leftCenter - overflow) + 'px';
      } else if (absoluteLeft < 8) {
        const shift = 8 - absoluteLeft;
        menu.style.left = (leftCenter + shift) + 'px';
      }
    }

    trigger.addEventListener('click', (ev) => {
      ev.stopPropagation();
      const opening = !menu.classList.contains('open');
      if (opening) {
        positionMenu();
        menu.classList.add('open');
        menu.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        if (toggleTheme) toggleTheme.checked = document.body.classList.contains('theme-dark');
      } else {
        menu.classList.remove('open');
        menu.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && !trigger.contains(e.target)) {
        menu.classList.remove('open');
        menu.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        menu.classList.remove('open');
        menu.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    window.addEventListener('resize', () => { if (menu.classList.contains('open')) positionMenu(); });
    window.addEventListener('scroll', () => { if (menu.classList.contains('open')) positionMenu(); }, true);

    if (toggleTheme) {
      toggleTheme.checked = document.body.classList.contains('theme-dark');
      toggleTheme.addEventListener('change', () => {
        const isDark = toggleTheme.checked;
        const classes = new Set(document.body.className.split(/\s+/).filter(Boolean));
        if (isDark) classes.add('theme-dark'); else classes.delete('theme-dark');
        document.body.className = Array.from(classes).join(' ');
        document.documentElement.className = Array.from(classes).join(' ');

        fetch('<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/controller/update_theme.php'; ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'theme=' + encodeURIComponent(isDark ? 'theme-dark' : '')
        }).catch(()=>{/* ignore errors */});
      });
    }
  });
})();
</script>
