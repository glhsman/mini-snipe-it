<?php
require_once __DIR__ . '/../src/Helpers/Auth.php';
\App\Helpers\Auth::startSession();
\App\Helpers\Auth::logout();
header('Location: login.php');
exit;
