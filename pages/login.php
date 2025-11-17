<?php
require_once __DIR__ . '/../services/config.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <div class="login-container">
        <form class="login-card" action="<?= BASE_URL ?>/services/auth.php" method="POST">
            <h1>Entrar</h1>

            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <button class="btn-login" type="submit">Fazer Login</button>

            <div class="login-footer">
                <p>Não tem conta? <a href="#">Cadastre-se</a></p>
            </div>
        </form>
    </div>
</body>
</html>

<?php include __DIR__ . '../footer.php';?>