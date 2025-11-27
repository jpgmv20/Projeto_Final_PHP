<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="../css/index.css">
</head>
<body class="<?PHP $config = $_SESSION['config']['tema'] ?? ""; echo $config ?>">
  <main>
    <section class="login-page" aria-labelledby="login-title">
      <div class="container">
        <div class="login-card" role="region" aria-labelledby="login-title">
          <div class="brand">
            <span class="logo-mark" aria-hidden="true">PM</span>
            <div>
              <h1 id="login-title">Entrar na conta</h1>
              <p style="margin:4px 0 0; color:var(--muted); font-size:0.95rem;">
                Acesse sua conta para continuar
              </p>
            </div>
          </div>

          <form class="login-form" action="../controller/controller_login.php" method="post">
            <div>
              <label for="email">E-mail</label>
              <input id="email" name="email" type="email" placeholder="email@gmail.com" required>
            </div>

            <div>
              <label for="password">Senha</label>
              <input id="password" name="password" type="password" placeholder="••••••••" required>
            </div>

            <div class="form-row">
              <label class="remember">
                <input type="checkbox" name="remember"> Lembrar-me
              </label>
              <a class="btn--link" href="#">Esqueceu a senha?</a>
            </div>

            <div class="login-actions">
              <button class="btn--primary" type="submit">Entrar</button>
              <a class="btn--link" href="register.php">Criar conta</a>
            </div>
          </form>

        </div>
      </div>
    </section>
  </main>
  <?php include_once('../footer.php');?>
</body>
</html>


