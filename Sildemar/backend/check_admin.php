<?php
require_once 'db.php';
$stmt = $conexion->query("SELECT id_usuario, correo, rol FROM usuario");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users, JSON_PRETTY_PRINT);
?>
