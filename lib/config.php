<?php
define('PRIVATE_PATH', realpath(__DIR__ . '/../_private') . '/');
define('REPOS_PATH', realpath(__DIR__ . '/../repos') . '/');

function get_user_home_path() {
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $userInfo = posix_getpwuid(posix_geteuid());
        if ($userInfo && !empty($userInfo['dir'])) { return $userInfo['dir']; }
    }
    if (!empty($_SERVER['HOME'])) { return $_SERVER['HOME']; }
    return dirname(__DIR__); 
}

function get_config() {
    $config_file = PRIVATE_PATH . 'config.json';
    if (!file_exists($config_file)) {
        $default_user = function_exists('get_current_user') ? get_current_user() : 'usuario';
        $raw_host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $default_host = explode(':', $raw_host)[0];
        return [
            'ssh_user' => $default_user,
            'ssh_host' => $default_host,
            'ssh_port' => '22',
            'repo_base_path' => REPOS_PATH
        ];
    }
    return json_decode(file_get_contents($config_file), true);
}

function save_config($data) {
    $config = [
        'ssh_user' => trim($data['ssh_user'] ?? ''),
        'ssh_host' => trim($data['ssh_host'] ?? ''),
        'ssh_port' => trim($data['ssh_port'] ?? '22'),
        'repo_base_path' => trim($data['repo_base_path'] ?? '')
    ];
    if (!empty($config['repo_base_path']) && substr($config['repo_base_path'], -1) !== '/') {
        $config['repo_base_path'] .= '/';
    }
    if (!is_dir(PRIVATE_PATH)) mkdir(PRIVATE_PATH, 0775, true);
    file_put_contents(PRIVATE_PATH . 'config.json', json_encode($config, JSON_PRETTY_PRINT));
}

function get_permissions() {
    $permissions_file = PRIVATE_PATH . 'permissions.json';
    $default_perms = ['admin_user' => 'root', 'user_permissions' => new stdClass()];
    if (!file_exists($permissions_file)) {
        if (!is_dir(PRIVATE_PATH)) mkdir(PRIVATE_PATH, 0775, true);
        file_put_contents($permissions_file, json_encode($default_perms, JSON_PRETTY_PRINT));
        chmod($permissions_file, 0660);
        return $default_perms;
    }
    $content = file_get_contents($permissions_file);
    $data = json_decode($content, true);
    if (empty($content) || json_last_error() !== JSON_ERROR_NONE || !isset($data['admin_user'])) {
        file_put_contents($permissions_file, json_encode($default_perms, JSON_PRETTY_PRINT));
        return $default_perms;
    }
    return $data;
}
