k<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/git.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

try {
    $repo_name = sanitize_name($_GET['repo'] ?? '');
    $ref = $_GET['ref'] ?? 'HEAD';
    
    // A verificação de permissão já está dentro de get_repo_details
    get_repo_details($repo_name, $ref); // Se o usuário não tiver permissão, isso lançará uma exceção
    
    $repo_dir = REPOS_PATH . $repo_name . '.git';
    $zip_filename = $repo_name . '-' . sanitize_name($ref) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $command = "git -C " . escapeshellarg($repo_dir) . " archive --format=zip --prefix=" . escapeshellarg($repo_name . '/') . " " . escapeshellarg($ref);
    passthru($command);

} catch (Exception $e) {
    http_response_code(403); // Forbidden
    error_log($e->getMessage());
    echo "Acesso negado ou recurso inválido.";
}
exit;
