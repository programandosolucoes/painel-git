<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/config.php';

if (is_first_run()) {
    $install_success = false;
    $admin_password = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
        try {
            save_config($_POST);
            $admin_password = generate_initial_admin_user();
            $install_success = true;
        } catch (Exception $e) {
            $error_message = "Erro ao salvar: " . $e->getMessage();
        }
    }
    $current_config = get_config();
    require __DIR__ . '/views/first_run.php';
    exit;
}

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/views/panel.php';
