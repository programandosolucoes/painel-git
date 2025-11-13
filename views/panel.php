<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Log de depuração que imprime no código-fonte da página.
 * @param string $message A mensagem a ser logada.
 */
function echo_debug_log($message) {
    echo "\n";
}

echo_debug_log("Iniciando a validacao de caminhos...");

// === TENTA ENCONTRAR E INCLUIR OS ARQUIVOS DE DEPENDÊNCIA ===
$auth_path = '';
$config_path = '';
$lib_path_found = false;

// Tentativa 1: Caminho padrão, subindo um nível
$auth_path_attempt_1 = __DIR__ . '/../lib/auth.php';
$config_path_attempt_1 = __DIR__ . '/../lib/config.php';
echo_debug_log("Tentativa 1: Checando " . $auth_path_attempt_1);
if (file_exists($auth_path_attempt_1) && file_exists($config_path_attempt_1)) {
    $auth_path = $auth_path_attempt_1;
    $config_path = $config_path_attempt_1;
    $lib_path_found = true;
    echo_debug_log("Sucesso! Caminho 'Tentativa 1' encontrado.");
}

// Tentativa 2: Caminho, subindo dois níveis (para casos em que o index.php nao esta no mesmo diretorio do views)
if (!$lib_path_found) {
    $auth_path_attempt_2 = dirname(__DIR__) . '/lib/auth.php';
    $config_path_attempt_2 = dirname(__DIR__) . '/lib/config.php';
    echo_debug_log("Tentativa 2: Checando " . $auth_path_attempt_2);
    if (file_exists($auth_path_attempt_2) && file_exists($config_path_attempt_2)) {
        $auth_path = $auth_path_attempt_2;
        $config_path = $config_path_attempt_2;
        $lib_path_found = true;
        echo_debug_log("Sucesso! Caminho 'Tentativa 2' encontrado.");
    }
}

if (!$lib_path_found) {
    echo_debug_log("ERRO FATAL: Nenhum caminho de 'lib' valido foi encontrado. O script sera interrompido.");
    die("<h1>Erro Fatal</h1><p>Nao foi possivel encontrar os arquivos de configuracao. Por favor, verifique se a pasta 'lib' existe e se as permissoes de acesso estao corretas.</p>");
}

// Inclui os arquivos com o caminho validado
require_once $auth_path;
require_once $config_path;
echo_debug_log("Arquivos 'auth.php' e 'config.php' incluidos com sucesso.");
// =============================================================

$logged_in_user = get_logged_in_user();
$is_admin = is_admin();
$config = get_config();
$ssh_config_json = json_encode($config);

// Validador de caminho para arquivos estáticos (CSS/JS)
$relative_path_style = 'assets/style.css';
$relative_path_app = 'assets/app.js';

if (file_exists(__DIR__ . '/' . $relative_path_style)) {
    // Caminho relativo a partir de 'views/'
    $style_path = $relative_path_style;
    $app_path = $relative_path_app;
} else {
    // Caminho absoluto a partir da raiz do servidor
    $style_path = '/git/' . $relative_path_style;
    $app_path = '/git/' . $relative_path_app;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Gerenciamento Git</title>
    <link rel="stylesheet" href="<?php echo $style_path; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body 
    data-is-admin="<?php echo json_encode($is_admin); ?>" 
    data-logged-in-user="<?php echo htmlspecialchars($logged_in_user); ?>"
    data-ssh-config="<?php echo htmlspecialchars($ssh_config_json, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="alert-container"></div>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <h1 style="text-align: left; flex-grow: 1;"><i class="fas fa-server"></i> Painel Git</h1>
            <a href="logout.php" class="button btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        <p style="text-align: center; margin-top: -30px; margin-bottom: 20px; font-size: 0.9em;">(Logado como: <?php echo htmlspecialchars($logged_in_user); ?>)</p>
        
        <?php if ($is_admin): ?>
        <div class="grid">
            <div class="card">
                <h2><i class="fas fa-key"></i> Gerenciar Chave SSH</h2>
                <form id="ssh-form">
                    <div class="form-group"><label for="ssh-key">Cole sua chave SSH pública para adicionar</label><textarea id="ssh-key" placeholder="ssh-rsa AAAAB3NzaC1yc2EAAA..." required></textarea></div>
                    <button type="submit" class="btn-success"><i class="fas fa-plus"></i> Adicionar Chave</button>
                </form>
                <div class="sub-card">
                    <h3><i class="fas fa-list-ul"></i> Chaves Autorizadas</h3>
                    <ul id="ssh-key-list" class="item-list"><p>Carregando chaves...</p></ul>
                </div>
            </div>
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Criar / Importar Repositório</h2>
                <form id="create-form"><div class="form-group"><label for="repo-name">Nome</label><input type="text" id="repo-name" placeholder="meu-novo-projeto" required></div><button type="submit" class="btn-success"><i class="fas fa-plus"></i> Criar</button></form>
                <hr style="margin: 20px 0;">
                <form id="import-form"><div class="form-group"><label for="import-url">URL Externa</label><input type="url" id="import-url" placeholder="https://github.com/usuario/repo.git" required></div><button type="submit" class="btn-warning"><i class="fas fa-download"></i> Importar</button></form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 20px;">
            <h2><i class="fas fa-list"></i> Repositórios <?php echo $is_admin ? 'Existentes' : 'Autorizados'; ?></h2>
            <ul class="repo-list" id="repo-list"><p>Carregando...</p></ul>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="card" id="access-management-card" style="margin-top: 20px;">
            <h2><i class="fas fa-users-cog"></i> Gestão de Acessos</h2>
            <div class="sub-card">
                <h3><i class="fas fa-user-plus"></i> Adicionar Novo Usuário</h3>
                <form id="add-user-form">
                    <div class="form-group"><label for="new-username">Nome de Usuário</label><input type="text" id="new-username" required></div>
                    <div class="form-group"><label for="new-password">Senha</label><input type="password" id="new-password" required></div>
                    <button type="submit" class="btn-success"><i class="fas fa-user-plus"></i> Adicionar Usuário</button>
                </form>
            </div>
            <div class="sub-card">
                <h3><i class="fas fa-tasks"></i> Permissões por Repositório</h3>
                <div id="permissions-grid"><p>Carregando usuários e repositórios...</p></div>
                <button id="save-permissions-btn" class="btn-success" style="margin-top: 20px;"><i class="fas fa-save"></i> Salvar Permissões</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="repo-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-repo-name"></h2>
                <span class="close" onclick="closeModal('repo-modal')">&times;</span>
            </div>

            <div class="grid" style="align-items: flex-start;">
                <div class="card">
                    <h3>Navegação</h3>
                    <div class="form-group">
                        <label for="ref-selector">Branch / Tag</label>
                        <select id="ref-selector"></select>
                    </div>
                    <a id="download-zip-btn" class="button btn-success" href="#" target="_blank"><i class="fas fa-file-archive"></i> Baixar ZIP</a>
                    
                    <?php if ($is_admin): ?>
                    <hr style="margin: 20px 0;">
                    
                    <h3>Ações de Admin</h3>
                    
                    <div class="sub-card">
                        <h4><i class="fas fa-upload"></i> Enviar Arquivos (ZIP)</h4>
                        <form id="upload-zip-form">
                            <div class="form-group">
                                <label for="zip-file">Arquivo .zip</label>
                                <input type="file" id="zip-file" name="zip_file" accept=".zip" required>
                            </div>
                            <div class="form-group">
                                <label for="commit-message">Mensagem do Commit</label>
                                <input type="text" id="commit-message" name="commit_message" value="Deploy via painel web" required>
                            </div>
                            <button type="submit" class="btn-secondary"><i class="fas fa-upload"></i> Enviar e Commitar</button>
                        </form>
                    </div>

                    <div class="sub-card">
                        <h4><i class="fas fa-tag"></i> Criar Nova Tag</h4>
                        <form id="create-tag-form">
                            <div class="form-group">
                                <label for="commit-selector">Commit de Origem</label>
                                <select id="commit-selector" name="commit_hash" required></select>
                            </div>
                            <div class="form-group">
                                <label for="tag-name">Nome da Tag (ex: v1.0.1)</label>
                                <input type="text" id="tag-name" name="tag_name" required>
                            </div>
                            <div class="form-group">
                                <label for="tag-message">Mensagem da Tag</label>
                                <input type="text" id="tag-message" name="tag_message" required>
                            </div>
                            <button type="submit" class="btn-secondary"><i class="fas fa-tag"></i> Criar Tag</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Arquivos do Repositório:</h3>
                    <pre id="repo-files">Carregando arquivos...</pre>
                </div>
            </div>
        </div>
    </div>
    
    <button id="help-btn" class="btn-secondary" title="Ajuda"><i class="fas fa-question-circle"></i></button>
    <div id="help-modal" class="modal"><div class="modal-content help-content"><div class="modal-header"><h2>Guia Completo de Uso</h2><span class="close" onclick="closeModal('help-modal')">&times;</span></div><h3>Cenário 1: Começando um Projeto do Zero</h3><ol><li><strong>Crie o repositório</strong> no painel e <strong>copie o comando de clone</strong> exibido.</li><li>Execute o comando no seu terminal local. Você verá um aviso de que clonou um repositório vazio. <strong>Isso é normal!</strong></li><li>Execute os seguintes comandos para fazer seu primeiro push:</li><pre><code># Entre na pasta recém-criada\ncd nome-do-seu-repositorio...</code></pre></ol><hr style="margin: 20px 0;"><h3>Cenário 2: Conectando um Projeto Local Já Existente</h3><ol><li><strong>Crie um repositório VAZIO</strong> no painel com o nome do seu projeto (ex: <code>projeto-existente</code>).</li><li><strong>Copie o comando de clone SSH</strong> do painel. Vamos usar essa URL.</li><li>No seu computador, <strong>navegue até a pasta do seu projeto</strong>.</li><li>Se a pasta ainda não for um repositório Git, inicialize-a: <pre><code>git init</code></pre></li><li><strong>Adicione o seu servidor como um "remote" chamado `origin`.</strong></li><pre><code id="remote-add-example">git remote add origin ssh://...</code></pre><li>Verifique se o remote foi adicionado: <pre><code>git remote -v</code></pre></li><li>Envie seus arquivos existentes para o servidor:</li><pre><code>git add .\ngit commit -m \"Commit inicial\"\ngit push -u origin master</code></pre></li></ol></div></div>
    
    <script src="<?php echo $app_path; ?>" defer></script>
</body>
</html>
