<?php
require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/views/login.php';
