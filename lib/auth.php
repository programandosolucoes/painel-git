<?php
session_start();
require_once __DIR__ . '/config.php';

function is_first_run() { return !file_exists(PRIVATE_PATH . '.htpasswd'); }

function generate_initial_admin_user() {
    $password = bin2hex(random_bytes(8)); $username = 'root';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $htpasswd_content = "{$username}:{$hash}" . PHP_EOL;
    if (!is_dir(PRIVATE_PATH)) mkdir(PRIVATE_PATH, 0775, true);
    file_put_contents(PRIVATE_PATH . '.htpasswd', $htpasswd_content);
    chmod(PRIVATE_PATH . '.htpasswd', 0660);
    get_permissions(); get_config();
    return $password;
}

function is_logged_in() {
    $session_timeout = 86400; // 24 horas
    if (isset($_SESSION['user'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
            session_unset(); session_destroy(); return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function get_logged_in_user() { return $_SESSION['user'] ?? null; }
function is_admin() { $permissions = get_permissions(); $user = get_logged_in_user(); return ($user && isset($permissions['admin_user']) && $user === $permissions['admin_user']); }

function get_htpasswd_users() {
    clearstatcache();
    $htpasswd_file = PRIVATE_PATH . '.htpasswd';
    if (!file_exists($htpasswd_file)) return [];
    $lines = file($htpasswd_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $users = [];
    foreach ($lines as $line) { if(strpos($line, ':') !== false) { $users[] = explode(':', $line)[0]; } }
    return $users;
}

function handle_login() {
    $htpasswd_file = PRIVATE_PATH . '.htpasswd';
    $username = $_POST['username'] ?? ''; $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) { throw new Exception('Usuário e senha são obrigatórios.'); }
    if (!file_exists($htpasswd_file)) { throw new Exception('Sistema não configurado.'); }
    
    $lines = file($htpasswd_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($file_user, $file_hash) = explode(':', $line, 2);
        if ($file_user === $username && password_verify($password, $file_hash)) {
            $_SESSION['user'] = $username; $_SESSION['last_activity'] = time();
            header('Location: index.php'); exit;
        }
    }
    throw new Exception('Credenciais inválidas.');
}

function add_user($username, $password) {
    $htpasswd_file = PRIVATE_PATH . '.htpasswd'; $permissions = get_permissions();
    $username = sanitize_name($username);
    if (empty($username) || empty($password)) throw new Exception('Usuário e senha são obrigatórios.');
    $users = get_htpasswd_users();
    if (in_array($username, $users) || (!empty($permissions['admin_user']) && $username === $permissions['admin_user'])) {
        throw new Exception('Usuário já existe ou é reservado para admin.');
    }
    $content_to_add = '';
    if (file_exists($htpasswd_file) && filesize($htpasswd_file) > 0) {
        $current_content = file_get_contents($htpasswd_file);
        if (substr($current_content, -1) !== "\n") { $content_to_add .= PHP_EOL; }
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $content_to_add .= "{$username}:{$hash}" . PHP_EOL;
    file_put_contents($htpasswd_file, $content_to_add, FILE_APPEND | LOCK_EX);
    chmod($htpasswd_file, 0660);
    return ['success' => true, 'message' => "Usuário '{$username}' adicionado com sucesso."];
}

function delete_user($username) {
    $htpasswd_file = PRIVATE_PATH . '.htpasswd'; $permissions = get_permissions();
    $username = sanitize_name($username);
    if (empty($username) || $username === $permissions['admin_user']) throw new Exception('Nome de usuário inválido ou não permitido.');
    $lines = file($htpasswd_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_lines = [];
    foreach ($lines as $line) { if (strpos($line, $username . ':') !== 0) $new_lines[] = $line; }
    file_put_contents($htpasswd_file, implode(PHP_EOL, $new_lines) . PHP_EOL, LOCK_EX);
    unset($permissions['user_permissions'][$username]);
    file_put_contents(PRIVATE_PATH . 'permissions.json', json_encode($permissions, JSON_PRETTY_PRINT), LOCK_EX);
    return ['success' => true, 'message' => "Usuário '{$username}' removido com sucesso."];
}

// --- FUNÇÕES PARA GIT HTTP SERVER (ADICIONADO) ---

function validate_user_credentials($username, $password) {
    $htpasswd_file = PRIVATE_PATH . '.htpasswd';
    if (!file_exists($htpasswd_file)) return false;

    $lines = file($htpasswd_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) continue;
        list($file_user, $file_hash) = explode(':', $line, 2);
        
        if ($file_user === $username && password_verify($password, $file_hash)) {
            return true;
        }
    }
    return false;
}

function can_access_repo($username, $repo_name) {
    $permissions = get_permissions();
    
    // 1. Admin (root) tem acesso total
    if (isset($permissions['admin_user']) && $username === $permissions['admin_user']) {
        return true;
    }

    // 2. Verifica permissões específicas do usuário
    $user_perms = $permissions['user_permissions'][$username] ?? [];
    
    // Normaliza o nome (com ou sem .git) para garantir a comparação
    $repo_clean = str_replace('.git', '', $repo_name);
    
    return in_array($repo_clean, $user_perms);
}
