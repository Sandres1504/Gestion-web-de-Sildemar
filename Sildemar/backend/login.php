<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$correo = $_POST['correo'] ?? '';
$clave = $_POST['clave'] ?? '';

try {
    $stmt = $conexion->prepare("SELECT id_usuario, rol, contraseña FROM usuario WHERE correo = ?");
    $stmt->execute([$correo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "El correo no existe en la base de datos."]);
    }
    else if (!password_verify($clave, $user['contraseña'])) {
        echo json_encode(["success" => false, "message" => "La clave es incorrecta para este correo."]);
    }
    else {
        $_SESSION['id_usuario'] = $user['id_usuario'];
        $_SESSION['rol'] = $user['rol'];
        echo json_encode(["success" => true, "rol" => $user['rol']]);
    }
}
catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de BD: " . $e->getMessage()]);
}
?>