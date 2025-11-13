<?php
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Painel Git</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-container">
        <h1>Painel Git</h1>
        <?php if ($error_message): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="api.php" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
