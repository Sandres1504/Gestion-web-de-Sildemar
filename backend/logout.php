<?php
require_once __DIR__ . '/secure_session.php';
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 42000, '/');
header("Location: ../index.html");
exit();
?>