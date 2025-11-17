<?php include 'services/auth.php' ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/index.css" />
    <script src="js/index.js" defer></script>
</head>
<body>
    <header class="header header--fixed" role="banner">
    <div class="container" style="display:flex; align-items:center; gap:var(--gap);">



      <!-- lado esquerdo: logo -->

      <div class="header__left">
        <a class="header__brand" href="/" aria-label="Página inicial">
          <span class="logo-mark" aria-hidden="true">PM</span>
          <span class="sr-only">Robuzzle </span>
          <span>Home</span>
        </a>
      </div>



      <!-- centro: busca -->

      <div class="header__center" aria-hidden="false">
        <div class="search" role="search" aria-label="Buscar">
          <div class="search__wrap">
            <!-- ícone de lupa (simples SVG) -->
            <span class="search__icon" aria-hidden="true">
              <!-- SVG pequeno — sem estilo inline pesado -->
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>

            <input
              class="search__input"
              type="search"
              name="q"
              placeholder="Buscar no site"
              aria-label="Buscar"
            />
          </div>
        </div>
      </div>




      <!-- direita: nav / botões -->

      <div class="header__right" style="margin-left:auto;">
        <nav class="header__nav" role="navigation" aria-label="Navegação principal">
          <a class="icon-btn" href="#" aria-label="Notificações" title="Notificações">
            <!-- ícone de sino (simples) -->
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
              <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>

          <a class="icon-btn" href="#" aria-label="Mensagens" title="Mensagens">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 15a2 2 0 01-2 2H7l-4 3V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>

          <!-- botão principal (ex: publicar/tweet) -->
          <button class="btn" type="button" aria-label="Novo post">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Postar</span>
          </button>

          <!-- avatar -->
          <a class="icon-btn" href="pages/login.php" aria-label="Perfil" title="Perfil">
            <span class="avatar" style="display:inline-block; width:36px; height:36px; border-radius:50%; overflow:hidden; background:var(--border);">
              <!-- imagem placeholder -->
              <img src="image/images.jfif" alt="Avatar" style="width:100%;height:100%;object-fit:cover;display:block;">
            </span>
          </a>
        </nav>
      </div>
    </div>
  </header>
</body>
</html>