<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

// Funções de ajuda
function sanitize_name($name) { $name = str_replace('.git', '', $name); return preg_replace('/[^a-zA-Z0-9\._-]/', '', $name); }
function run_command($command) { exec($command . " 2>&1", $output, $return_var); if ($return_var !== 0) { throw new Exception("Erro: " . implode("\n", $output)); } return $output; }
function delete_dir($dirPath) { if (!is_dir($dirPath)) return; $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($files as $fileinfo) { $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink'); $todo($fileinfo->getRealPath()); } rmdir($dirPath); }

function get_repo_list() {
    $repos_path = REPOS_PATH;
    $all_repos_paths = is_dir($repos_path) ? glob($repos_path . '*.git') : [];
    $all_repos = $all_repos_paths ? array_map(fn($p) => basename($p, '.git'), $all_repos_paths) : [];
    if (is_admin()) { return $all_repos; }
    $permissions = get_permissions();
    $logged_in_user = get_logged_in_user();
    $user_perms = $permissions['user_permissions'][$logged_in_user] ?? [];
    return array_values(array_intersect($all_repos, $user_perms));
}

// ATUALIZADO: Agora busca também o histórico de commits
function get_repo_details($repo_name, $ref = 'HEAD') {
    $repo_dir = REPOS_PATH . sanitize_name($repo_name) . '.git';
    if (!is_dir($repo_dir)) throw new Exception('Repositório não encontrado.');
    if (!is_admin()) {
        $permissions = get_permissions(); $logged_in_user = get_logged_in_user();
        $user_perms = $permissions['user_permissions'][$logged_in_user] ?? [];
        if (!in_array($repo_name, $user_perms)) throw new Exception('Acesso negado a este repositório.');
    }
    exec("git -C " . escapeshellarg($repo_dir) . " rev-parse --verify HEAD 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        return ['is_empty' => true, 'files' => 'Este repositório está vazio.', 'branches' => [], 'tags' => [], 'log' => []];
    }
    $all_refs_raw = run_command("git -C " . escapeshellarg($repo_dir) . " for-each-ref --format='%(refname:short)' refs/heads refs/tags");
    if ($ref !== 'HEAD' && !in_array($ref, $all_refs_raw)) $ref = 'HEAD';
    $files = run_command("git -C " . escapeshellarg($repo_dir) . " ls-tree -r --name-only " . escapeshellarg($ref));
    $log = run_command("git -C " . escapeshellarg($repo_dir) . " log --pretty=format:'%H|%s|%an|%cr' -n 20 " . escapeshellarg($ref));
    return [
        'is_empty' => false, 'files' => implode("\n", $files),
        'branches' => run_command("git -C " . escapeshellarg($repo_dir) . " for-each-ref --format='%(refname:short)' refs/heads"),
        'tags' => run_command("git -C " . escapeshellarg($repo_dir) . " for-each-ref --format='%(refname:short)' refs/tags"),
        'log' => $log,
    ];
}

function create_repo($repo_name) {
    $repo_name = sanitize_name($repo_name);
    if (empty($repo_name)) throw new Exception('Nome do repositório inválido.');
    $repo_dir = REPOS_PATH . $repo_name . '.git';
    if (is_dir($repo_dir)) throw new Exception("Repositório '{$repo_name}' já existe.");
    run_command("git init --bare " . escapeshellarg($repo_dir));
    return ['success' => true, 'message' => "Repositório '{$repo_name}' criado."];
}

function import_repo($import_url) {
    $import_url = filter_var($import_url, FILTER_VALIDATE_URL);
    if (!$import_url) throw new Exception('URL inválida.');
    $repo_name = sanitize_name(basename($import_url, '.git'));
    $repo_dir = REPOS_PATH . $repo_name . '.git';
    if (is_dir($repo_dir)) throw new Exception("Repositório '{$repo_name}' já existe.");
    run_command("git clone --mirror " . escapeshellarg($import_url) . " " . escapeshellarg($repo_dir));
    return ['success' => true, 'message' => "Repositório '{$repo_name}' importado."];
}

function delete_repo($repo_name) {
    $repo_name = sanitize_name($repo_name);
    $repo_dir = REPOS_PATH . $repo_name . '.git';
    if (!is_dir($repo_dir) || strpos(realpath($repo_dir), REPOS_PATH) !== 0) throw new Exception('Repositório inválido.');
    delete_dir($repo_dir);
    return ['success' => true, 'message' => "Repositório '{$repo_name}' excluído."];
}

function save_permissions($perms_from_client) {
    if (is_null($perms_from_client)) throw new Exception('Dados de permissão inválidos.');
    $permissions = get_permissions();
    $permissions['user_permissions'] = $perms_from_client;
    file_put_contents(PRIVATE_PATH . 'permissions.json', json_encode($permissions, JSON_PRETTY_PRINT), LOCK_EX);
    return ['success' => true, 'message' => 'Permissões salvas com sucesso!'];
}

function add_ssh_key($key) {
    $home_path = get_user_home_path();
    if (!$home_path) throw new Exception('Não foi possível determinar o diretório home do usuário.');
    $ssh_path = $home_path . '/.ssh';
    $auth_keys_file = $ssh_path . '/authorized_keys';
    
    $key = trim($key);
    if (empty($key) || !preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256)/', $key)) throw new Exception('Chave SSH inválida.');
    
    if (!is_dir($ssh_path)) run_command("mkdir -m 700 " . escapeshellarg($ssh_path));
    
    $current_keys = file_exists($auth_keys_file) ? file_get_contents($auth_keys_file) : '';
    if (strpos($current_keys, $key) !== false) throw new Exception('Esta chave SSH já foi adicionada.');
    
    if (!empty($current_keys) && substr($current_keys, -1) !== "\n") {
        file_put_contents($auth_keys_file, PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    file_put_contents($auth_keys_file, $key . PHP_EOL, FILE_APPEND | LOCK_EX);
    chmod($auth_keys_file, 0600);
    return ['success' => true, 'message' => 'Chave SSH adicionada com sucesso!'];
}

function get_ssh_keys() {
    $home_path = get_user_home_path();
    if (!$home_path) throw new Exception('Não foi possível determinar o diretório home do usuário.');
    $auth_keys_file = $home_path . '/.ssh/authorized_keys';

    if (!file_exists($auth_keys_file) || !is_readable($auth_keys_file)) {
        return ['success' => true, 'keys' => []];
    }
    $keys = file($auth_keys_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return ['success' => true, 'keys' => array_values(array_filter($keys))];
}

function delete_ssh_key($key_to_delete) {
    $home_path = get_user_home_path();
    if (!$home_path) throw new Exception('Não foi possível determinar o diretório home do usuário.');
    $auth_keys_file = $home_path . '/.ssh/authorized_keys';
    
    if (!file_exists($auth_keys_file)) throw new Exception('Arquivo de chaves não encontrado.');

    $key_to_delete = trim($key_to_delete);
    if (empty($key_to_delete)) throw new Exception('Nenhuma chave fornecida para exclusão.');

    $keys = file($auth_keys_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_keys = []; $found = false;
    foreach ($keys as $key) {
        if (trim($key) === $key_to_delete) { $found = true; } else { $new_keys[] = $key; }
    }
    if (!$found) throw new Exception('Chave não encontrada no arquivo.');
    
    $new_content = empty($new_keys) ? '' : implode(PHP_EOL, $new_keys) . PHP_EOL;
    file_put_contents($auth_keys_file, $new_content);
    return ['success' => true, 'message' => 'Chave SSH removida com sucesso.'];
}

// ==========================================================
// =========== NOVAS FUNÇÕES ADICIONADAS AQUI ===============
// ==========================================================
function create_tag($repo_name, $tag_name, $commit_hash, $tag_message) {
    $repo_dir = REPOS_PATH . sanitize_name($repo_name) . '.git';
    $tag_name = sanitize_name($tag_name); // Reutiliza a função de sanitização
    $tag_message = escapeshellarg($tag_message);
    
    if (empty($tag_name) || empty($commit_hash)) {
        throw new Exception('Nome da tag e hash do commit são obrigatórios.');
    }
    if (!preg_match('/^[a-f0-9]{7,40}$/', $commit_hash)) {
        throw new Exception('Hash de commit inválido.');
    }
    
    // O comando 'git tag' é executado diretamente no repositório bare
    run_command("git -C " . escapeshellarg($repo_dir) . " tag -a " . escapeshellarg($tag_name) . " -m " . $tag_message . " " . escapeshellarg($commit_hash));
    // É necessário fazer um push da tag para o 'origin' do bare repo para que seja visível
    run_command("git -C " . escapeshellarg($repo_dir) . " push origin " . escapeshellarg($tag_name));
    
    return ['success' => true, 'message' => "Tag '{$tag_name}' criada com sucesso."];
}

function handle_zip_upload($repo_name, $commit_message, $uploaded_file) {
    if (empty($uploaded_file) || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do arquivo ZIP. Verifique o tamanho do arquivo e as permissões.');
    }
    
    $repo_dir_bare = REPOS_PATH . sanitize_name($repo_name) . '.git';
    $tmp_dir = PRIVATE_PATH . 'tmp_clone_' . uniqid();
    if (is_dir($tmp_dir)) delete_dir($tmp_dir);
    mkdir($tmp_dir, 0755, true);

    try {
        // 1. Clona o repositório bare para a pasta temporária
        run_command("git clone " . escapeshellarg($repo_dir_bare) . " " . escapeshellarg($tmp_dir));
        
        // 2. Extrai o ZIP por cima dos arquivos clonados
        $zip = new ZipArchive;
        if ($zip->open($uploaded_file['tmp_name']) === TRUE) {
            $zip->extractTo($tmp_dir);
            $zip->close();
        } else {
            throw new Exception('Não foi possível abrir o arquivo ZIP.');
        }

        // 3. Executa os comandos Git dentro da pasta temporária
        run_command("git -C " . escapeshellarg($tmp_dir) . " add -A");
        
        $status_output = shell_exec("git -C " . escapeshellarg($tmp_dir) . " status --porcelain");
        if (empty($status_output)) {
            return ['success' => true, 'message' => 'Nenhuma alteração detectada para commitar.'];
        }
        
        run_command("git -C " . escapeshellarg($tmp_dir) . " commit -m " . escapeshellarg($commit_message));
        // Assume que o push é para a branch 'master'. Ajuste se necessário.
        run_command("git -C " . escapeshellarg($tmp_dir) . " push origin master");

    } finally {
        // 4. Limpeza: apaga a pasta temporária
        delete_dir($tmp_dir);
    }
    
    return ['success' => true, 'message' => 'Arquivos enviados e commitados com sucesso!'];
}
