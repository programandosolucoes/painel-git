<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Instalação - Painel Git</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-container" style="max-width: 500px;">
        <?php if (isset($install_success) && $install_success): ?>
            <h1 style="color: var(--success);"><i class="fas fa-check-circle"></i> Sucesso!</h1>
            <p style="text-align: center; margin-bottom: 20px;">Configuração salva. Anote sua senha.</p>
            <div class="alert" style="background-color: #e3fcef; color: #155724; border: 1px solid #c3e6cb; padding: 15px;">
                <strong>Usuário:</strong> root<br>
                <strong>Senha:</strong> <span style="font-family:monospace; font-size:1.2em;"><?php echo htmlspecialchars($admin_password); ?></span>
            </div>
            <a href="login.php" class="button btn-success" style="text-align:center; display:block; width:100%; margin-top: 20px; text-decoration:none;">Ir para o Login</a>
        <?php else: ?>
            <h1 style="margin-bottom: 10px;"><i class="fas fa-cogs"></i> Configuração</h1>
            <p style="text-align: center; margin-bottom: 20px; color: #666; font-size: 0.9em;">Ajuste os dados do servidor SSH.</p>
            <?php if (!empty($error_message)): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="install" value="1">
                <div class="form-group">
                    <label>Host SSH (Domínio ou IP)</label>
                    <input type="text" name="ssh_host" value="<?php echo htmlspecialchars($current_config['ssh_host']); ?>" required>
                </div>
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Usuário Sistema</label>
                        <input type="text" name="ssh_user" value="<?php echo htmlspecialchars($current_config['ssh_user']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Porta SSH</label>
                        <input type="number" name="ssh_port" value="<?php echo htmlspecialchars($current_config['ssh_port']); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Caminho Repositórios</label>
                    <input type="text" name="repo_base_path" value="<?php echo htmlspecialchars($current_config['repo_base_path']); ?>" required>
                </div>
                <button type="submit" class="btn-success" style="width: 100%; justify-content: center; margin-top: 10px;">Salvar e Criar Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
