<?php
// git-server.php - Proxy Git HTTP (Versão Produção)
ini_set('display_errors', 0);
ini_set('output_buffering', 0);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';

$repo_base_path = __DIR__ . '/repos'; 
$git_bin_path = '/usr/bin/git'; 

// --- 1. AUTENTICAÇÃO ---
$user = $_SERVER['PHP_AUTH_USER'] ?? null;
$pass = $_SERVER['PHP_AUTH_PW'] ?? null;

if (!$user) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth_header = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    
    if ($auth_header && stripos($auth_header, 'basic ') === 0) {
        list($user, $pass) = explode(':', base64_decode(substr($auth_header, 6)));
    }
}

if (!$user || !validate_user_credentials($user, $pass)) {
    header('WWW-Authenticate: Basic realm="Git Server Access"');
    header('HTTP/1.0 401 Unauthorized');
    exit("Autenticação Necessária.");
}

// --- 2. ROTEAMENTO E PERMISSÕES ---
$path_info = $_GET['data'] ?? ''; 

if (!preg_match('#^(?:repos/)?([^/]+\.git)(/.*)?$#', $path_info, $matches)) {
    header('HTTP/1.0 404 Not Found');
    exit("Repositório não encontrado.");
}

$repo_name = $matches[1];
$git_uri   = $matches[2] ?? ''; 

if (!is_dir($repo_base_path . '/' . $repo_name)) {
    header('HTTP/1.0 404 Not Found');
    exit("Repositório não encontrado.");
}

if (!can_access_repo($user, $repo_name)) {
    header('HTTP/1.0 403 Forbidden');
    exit("Acesso Negado.");
}

// --- 3. EXECUÇÃO DO GIT (CGI MODE) ---
$env = [
    'GIT_PROJECT_ROOT' => $repo_base_path,
    'GIT_HTTP_EXPORT_ALL' => '1',
    'REMOTE_USER' => $user,
    'PATH_INFO' => '/' . $repo_name . $git_uri,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? ''
];

foreach (['CONTENT_TYPE', 'CONTENT_LENGTH', 'HTTP_GIT_PROTOCOL'] as $h) {
    if (isset($_SERVER[$h])) $env[$h] = $_SERVER[$h];
}

$command = "$git_bin_path http-backend";
$descriptors = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open($command, $descriptors, $pipes, null, $env);

if (is_resource($process)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = fopen('php://input', 'rb');
        stream_copy_to_stream($input, $pipes[0]);
        fclose($input);
    }
    fclose($pipes[0]);

    // Parser de Headers CGI para corrigir comportamento no LiteSpeed
    while ($line = fgets($pipes[1])) {
        if ($line === "\r\n" || $line === "\n") break;
        $line = trim($line);
        if (!empty($line) && stripos($line, 'Status:') !== 0) {
            header($line);
        }
    }

    fpassthru($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
} else {
    header('HTTP/1.0 500 Internal Server Error');
    echo "Erro interno no servidor Git.";
}
