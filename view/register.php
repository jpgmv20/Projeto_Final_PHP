<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Register</title>
  <link rel="stylesheet" href="../css/index.css">
  <script src="../js/register.js" defer></script>
</head>

<body class="<?PHP $config = $_SESSION['config']['tema'] ?? ""; echo $config ?>">

  <main>
    <section class="login-page" aria-labelledby="login-title">
      <div class="container">
        <div class="login-card" role="region" aria-labelledby="login-title">
          <div class="brand">
            <span class="logo-mark" aria-hidden="true">PM</span>
            <div>
              <h1 id="login-title">Criar conta</h1>
              <p style="margin:4px 0 0; color:var(--muted); font-size:0.95rem;">
                Crie sua conta para continuar
              </p>
            </div>
          </div>

          <form class="login-form" action="../controller/controller_register.php" method="post" enctype="multipart/form-data">

            <div class="avatar-container">
              <!-- Alem de ter que ajeitar o do avatar, seta o input com type file com a imagem carregada pra poder pegar no php -->
                <input type="hidden" name="MAX_FILE_SIZE" value="99999999"/>

                <input type="file" id="avatar_input" name="avatar" accept="image/*" hidden>
                <input type="url" id="avatar_url" name="avatar_url" placeholder="Ou insira uma URL da web" class="avatar-url-input" hidden>


                <div class="avatar-circle" onclick="openAvatarOptions()">
                    <img id="avatar_preview"
                        src="../image/images.jfif"
                        alt="avatar">
                </div>

                <div class="avatar-buttons">
                  <!-- Me diz que posso trocar isso por um input type hidden -->
                    <button type="button" onclick="chooseFile()">Upload</button>
                    <button type="button" onclick="enterUrl()">URL</button>
                </div>
            </div>

            <div>
              <label for="username">username</label>
              <input id="username" name="username" type="text" placeholder="Username" required>
            </div>

            <div>
              <label for="email">E-mail</label>
              <input id="email" name="email" type="email" placeholder="email@gmail.com" required>
            </div>

            <div>
              <label for="password">Senha</label>
              <input id="password" name="password" type="password" placeholder="••••••••" required>
            </div>

            <div>
              <label for="password">Confimar senha</label>
              <input id="passwordConfirm" name="passwordConfirm" type="password" placeholder="••••••••" required>
            </div>
            
            <div class="login-actions">
              <button class="btn--primary" type="submit">Criar</button>
              <a class="btn--link" href="login.php">Login</a>
            </div>
          </form>
        </div>
      </div>
    </section>
  </main>
  <?php include_once('../footer.php');?>
</body>
</html>


