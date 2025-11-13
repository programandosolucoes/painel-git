<?php
require_once __DIR__ . '/lib/auth.php';

// Limpa a sessão e redireciona
session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;
