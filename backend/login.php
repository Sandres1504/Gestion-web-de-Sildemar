<?php
require_once 'db.php';

header('Content-Type: application/json');

$correo = filter_var(trim($_POST['correo'] ?? ''), FILTER_VALIDATE_EMAIL);
$clave = trim($_POST['clave'] ?? '');

if (!$correo || !$clave) {
    echo json_encode(["success" => false, "message" => "Correo y clave son requeridos."]);
    exit;
}

require_once 'secure_session.php';

try {
    session_regenerate_id(true);

    // Buscamos el usuario y su nombre real en la tabla persona
    $stmt = $conexion->prepare("SELECT u.id_usuario, r.nombre_rol as rol, u.password, p.nombre, u.primer_login 
                                FROM usuario u 
                                JOIN persona p ON u.id_persona = p.id_persona 
                                JOIN roles r ON u.id_rol = r.id_rol
                                WHERE u.correo = ?");
    $stmt->execute([$correo]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($clave, $user['password'])) {
        echo json_encode(["success" => false, "message" => "Correo o contraseña incorrectos."]);
    } else {
        // Estandarizamos variables de sesión
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['usuario_rol'] = $user['rol'];
        $_SESSION['usuario_nombre'] = $user['nombre'];

        $es_primer_login = (int)$user['primer_login'];

        // Si es el primer login, lo marcamos como 0 para la próxima vez
        if ($es_primer_login === 1) {
            $updateStmt = $conexion->prepare("UPDATE usuario SET primer_login = 0 WHERE id_usuario = ?");
            $updateStmt->execute([$user['id_usuario']]);
        }

        echo json_encode([
            "success" => true,
            "id_usuario" => $user['id_usuario'],
            "rol" => $user['rol'],
            "nombre" => $user['nombre'],
            "primer_login" => $es_primer_login
        ]);
    }
}
catch (PDOException $e) {
    error_log("[LOGIN] Error de BD: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Error de BD. Contacte al administrador."]);
}
?>