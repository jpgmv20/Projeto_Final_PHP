<?php
session_start();

/**
 * post.php
 * Form + processamento simples para criar posts (titulo, conteúdo, tags, imagem)
 * Salva posts em posts.json e imagens em uploads/
 */

/* Config */
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('POSTS_FILE', __DIR__ . '/posts.json');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 MB
$allowedMimes = ['image/jpeg','image/png','image/gif'];

/* Garante uploads dir */
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/* CSRF simples */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$errors = [];
$success = false;

/* Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        $errors[] = "Token inválido. Recarregue a página e tente novamente.";
    }

    // Dados
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags_raw = trim($_POST['tags'] ?? '');

    if ($title === '') $errors[] = "Título é obrigatório.";
    if ($content === '') $errors[] = "Conteúdo é obrigatório.";
    if (mb_strlen($title) > 200) $errors[] = "Título muito longo (máx 200 caracteres).";

    // Processa upload (opcional)
    $imagePath = null;
    if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erro no upload da imagem.";
        } else {
            if ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = "Imagem muito grande (máx 2 MB).";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowedMimes, true)) {
                    $errors[] = "Formato de imagem não permitido. Use JPG, PNG ou GIF.";
                } else {
                    // extensão segura
                    $ext = '';
                    switch ($mime) {
                        case 'image/jpeg': $ext = 'jpg'; break;
                        case 'image/png': $ext = 'png'; break;
                        case 'image/gif': $ext = 'gif'; break;
                    }
                    $basename = bin2hex(random_bytes(8));
                    $filename = $basename . '.' . $ext;
                    $target = UPLOAD_DIR . '/' . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $target)) {
                        $errors[] = "Falha ao salvar a imagem no servidor.";
                    } else {
                        $imagePath = 'uploads/' . $filename; // caminho relativo para uso no HTML
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        // Sanitização para armazenamento (guardamos conteúdo cru mas escapamos ao imprimir)
        $tags = array_filter(array_map('trim', explode(',', $tags_raw)));

        // Carrega posts existentes
        $posts = [];
        if (file_exists(POSTS_FILE)) {
            $raw = file_get_contents(POSTS_FILE);
            $posts = json_decode($raw, true) ?: [];
        }

        $post = [
            'id' => time() . '-' . bin2hex(random_bytes(4)),
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
            'image' => $imagePath,
            'created_at' => date('c')
        ];

        // adiciona no início
        array_unshift($posts, $post);

        // salva
        if (file_put_contents(POSTS_FILE, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            $errors[] = "Falha ao salvar post.";
        } else {
            $success = true;
            // limpa valores para o form
            $title = $content = $tags_raw = '';
        }
    }
}

/* Carrega posts para exibir (últimos 10) */
$postsList = [];
if (file_exists(POSTS_FILE)) {
    $raw = file_get_contents(POSTS_FILE);
    $postsList = array_slice(json_decode($raw, true) ?: [], 0, 10);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Criar post — Projeto</title>
  <link rel="stylesheet" href="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/Projeto_Final_PHP/css/post.css' ?>">
</head>

<body class="<?PHP $config = $_SESSION['config']['tema'] ?? ""; echo $config ?>">
  <main class="main post-page">
    <div class="post-wrapper">
      <h1 class="post-title">Criar novo post</h1>

      <?php if ($success): ?>
        <div class="flash success">Post criado com sucesso.</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="flash error">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?php echo htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form class="post-form" action="" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <label class="form-row">
          <span>Título</span>
          <input class="input" type="text" name="title" maxlength="200" value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
        </label>

        <label class="form-row">
          <span>Conteúdo</span>
          <textarea class="textarea" name="content" rows="6" required><?php echo htmlspecialchars($content ?? ''); ?></textarea>
        </label>

        <label class="form-row">
          <span>Tags (separe por vírgula)</span>
          <input class="input" type="text" name="tags" value="<?php echo htmlspecialchars($tags_raw ?? ''); ?>">
        </label>

        <label class="form-row">
          <span>Imagem (opcional)</span>
          <input class="input" type="file" name="image" accept="image/*">
          <small class="muted">JPG / PNG / GIF — máx 2 MB</small>
        </label>

        <div class="form-actions">
          <button class="btn--primary" type="submit">Publicar</button>
          <a href="./" class="btn--link">Voltar</a>
        </div>
      </form>

      <?php if (!empty($postsList)): ?>
        <section class="post-list">
          <h2>Últimos posts</h2>
          <?php foreach ($postsList as $p): ?>
            <article class="post-card">
              <?php if (!empty($p['image'])): ?>
                <figure class="post-image">
                  <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
                </figure>
              <?php endif; ?>
              <div class="post-body">
                <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                <div class="post-meta"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?><?php if (!empty($p['tags'])): ?> • <?php echo htmlspecialchars(implode(', ', $p['tags'])); ?><?php endif; ?></div>
                <p><?php echo nl2br(htmlspecialchars(mb_strimwidth($p['content'], 0, 350, '...'))); ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
