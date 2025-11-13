<?php

define('IN_INDEX', true);

include 'header.php';

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Home</title>
  <link rel="stylesheet" href="css/index.css" />
  <script src="js/index.js" defer></script>
</head>

<body class="theme-dark">
  
  <!-- espaço para compensar header fixo -->

  <div style="height: var(--header-height);"></div>

  <!-- Conteúdo principal -->

  <main class="container" role="main" style="padding-top: var(--gap-lg);">
    <section aria-labelledby="welcome-title">
      <h1 id="welcome-title" style="text-align: center;">Bem-vindo!</h1>



      <!-- exemplo de card simples (tweet-like) -->

      <article class="card" style="background:var(--surface); border-radius:var(--radius); padding:var(--gap); margin-top:var(--gap); box-shadow:var(--shadow);">
        <div style="display:flex; gap:12px; align-items:flex-start;">
          <div class="avatar" aria-hidden="true" style="width:48px;height:48px;">
            <img src="image/images.jfif" alt="Avatar" style="width:100%;height:100%;object-fit:cover;display:block;">
          </div>
          <div style="flex:1;">
            <div style="display:flex; gap:8px; align-items:center;">
              <strong>Han Isle</strong>
              <span style="color:var(--muted); font-size:0.95rem;">@usuario · 1h</span>
            </div>
            <p style="margin:8px 0 0 0;">Fase nova</p>
          </div>
        </div>
      </article>

      

    </section>

  </main>
</body>
</html>

<?php
include 'footer.php';
?>