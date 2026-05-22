<?php
require_once __DIR__ . '/secure_session.php';
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.html");
    exit();
}
?>