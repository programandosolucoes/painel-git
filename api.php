<?php
session_start();
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/git.php';

header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida.'];

// Ação pública de login
if ($action === 'login') {
    try { handle_login(); } catch (Exception $e) { $_SESSION['error_message'] = $e->getMessage(); header('Location: login.php'); exit; }
}

// Ações protegidas
try {
    if (!is_logged_in()) { throw new Exception('Autenticação necessária.'); }
    
    $is_admin_check = is_admin();
    $admin_only_actions = [
        'create_repo', 'import_repo', 'delete_repo', 'add_ssh_key', 
        'get_ssh_keys', 'delete_ssh_key', 'save_permissions', 'add_user', 
        'delete_user', 'get_users_and_permissions', 'create_tag', 'upload_zip'
    ];
    if (in_array($action, $admin_only_actions) && !$is_admin_check) {
        throw new Exception('Acesso negado. Apenas administradores podem executar esta ação.');
    }

    switch ($action) {
        case 'list_repos':
            $response = ['success' => true, 'repos' => get_repo_list()];
            break;
        case 'get_repo_details':
            $response = ['success' => true] + get_repo_details($_GET['repo'] ?? '', $_GET['ref'] ?? 'HEAD');
            break;
        case 'get_users_and_permissions':
            $permissions_data = get_permissions();
            $response = ['success' => true, 'users' => array_values(array_diff(get_htpasswd_users(), [$permissions_data['admin_user']])), 'permissions' => $permissions_data['user_permissions']];
            break;
        case 'create_repo':
            $response = create_repo($_POST['name'] ?? '');
            break;
        case 'import_repo':
            $response = import_repo($_POST['url'] ?? '');
            break;
        case 'delete_repo':
            $response = delete_repo($_POST['name'] ?? '');
            break;
        case 'add_ssh_key':
            $response = add_ssh_key($_POST['key'] ?? '');
            break;
        case 'get_ssh_keys':
            $response = get_ssh_keys();
            break;
        case 'delete_ssh_key':
            $response = delete_ssh_key($_POST['key'] ?? '');
            break;
        case 'add_user':
            $response = add_user($_POST['username'] ?? '', $_POST['password'] ?? '');
            break;
        case 'delete_user':
            $response = delete_user($_POST['username'] ?? '');
            break;
        case 'save_permissions':
            $perms_from_client = json_decode($_POST['permissions'] ?? '[]', true);
            $response = save_permissions($perms_from_client);
            break;
        
        // --- NOVAS ACTIONS ADICIONADAS AQUI ---
        case 'create_tag':
            $response = create_tag(
                $_POST['repo_name'] ?? '',
                $_POST['tag_name'] ?? '',
                $_POST['commit_hash'] ?? '',
                $_POST['tag_message'] ?? ''
            );
            break;

        case 'upload_zip':
            $response = handle_zip_upload(
                $_POST['repo_name'] ?? '',
                $_POST['commit_message'] ?? 'Deploy via upload de ZIP',
                $_FILES['zip_file'] ?? null
            );
            break;

        default:
             throw new Exception('Ação desconhecida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
