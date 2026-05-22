<?php
require_once 'secure_session.php';
header('Content-Type: application/json');
require_once 'db.php';
require_once 'logger.php'; // Assuming logger is available for auditing

$action = $_GET['action'] ?? '';

// Ensure user is logged in and trying to access/update their own profile
$current_user_id = $_SESSION['usuario_id'] ?? null;
$current_user_rol = $_SESSION['usuario_rol'] ?? null;

if (!$current_user_id) {
    echo json_encode(["success" => false, "message" => "Acceso denegado. No hay sesión activa."]);
    exit;
}

try {
    if ($action === 'get') {
            // Allow calling without an explicit id and fall back to the current session user id
            $id_usuario_param = $_GET['id'] ?? $current_user_id;

        if ($id_usuario_param != $current_user_id) {
            throw new Exception("Acceso no autorizado para ver este perfil.");
        }

        $stmt = $conexion->prepare("
            SELECT u.id_usuario, u.correo, u.primer_login, p.cedula, p.nombre, p.telefono, p.direccion, r.nombre_rol as rol
            FROM usuario u
            JOIN persona p ON u.id_persona = p.id_persona
            JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = :id_usuario
        ");
            $stmt->execute([':id_usuario' => $id_usuario_param]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            echo json_encode(["success" => true, "data" => $userData]);
        } else {
            throw new Exception("Usuario no encontrado.");
        }
    } elseif ($action === 'update') {
        $data = json_decode(file_get_contents('php://input'), true);

        $id_usuario_to_update = $data['id_usuario'] ?? null;
        $correo = trim($data['correo'] ?? '');
        $clave = $data['clave'] ?? ''; // New password, can be empty if not changing
        $telefono = trim($data['telefono'] ?? '');
        $direccion = trim($data['direccion'] ?? '');

        if ($id_usuario_to_update != $current_user_id) {
            throw new Exception("Acceso no autorizado para actualizar este perfil.");
        }

        // Fetch current user data to get id_persona
        $stmtUser = $conexion->prepare("SELECT id_persona, primer_login FROM usuario WHERE id_usuario = :id_usuario");
        $stmtUser->execute([':id_usuario' => $current_user_id]);
        $userCurrentData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userCurrentData) {
            throw new Exception("Usuario no encontrado para actualizar.");
        }
        $id_persona = $userCurrentData['id_persona'];
        $primer_login = $userCurrentData['primer_login'];

        // Validate email format
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }

        // Check if email already exists for another user
        $stmtCheckEmail = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo AND id_usuario != :id_usuario");
        $stmtCheckEmail->execute([':correo' => $correo, ':id_usuario' => $current_user_id]);
        if ($stmtCheckEmail->fetch()) {
            throw new Exception("El correo electrónico ya está registrado por otro usuario.");
        }

        $conexion->beginTransaction();

        // Update persona data
        $stmtUpdatePersona = $conexion->prepare("
            UPDATE persona SET telefono = :telefono, direccion = :direccion
            WHERE id_persona = :id_persona
        ");
        $stmtUpdatePersona->execute([
            ':telefono' => $telefono,
            ':direccion' => $direccion,
            ':id_persona' => $id_persona
        ]);

        // Update usuario data (correo and password if provided)
        $updateUserSql = "UPDATE usuario SET correo = :correo";
        $updateUserParams = [':correo' => $correo, ':id_usuario' => $current_user_id];

        if (!empty($clave)) {
            // Password validation
            $hasUpper = preg_match('/[A-Z]/', $clave);
            $hasLower = preg_match('/[a-z]/', $clave);
            $hasNumber = preg_match('/[0-9]/', $clave);
            $hasSpecial = preg_match('/[^A-Za-z0-9;:{}\[\]\']/', $clave); // Excludes forbidden chars
            $hasForbidden = preg_match('/[;:{}\[\]\']/', $clave);

            if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
                throw new Exception('La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial.');
            }
            if ($hasForbidden) {
                throw new Exception("La contraseña no puede contener los siguientes caracteres: ; : { } [ ] '");
            }

            $passHash = password_hash($clave, PASSWORD_DEFAULT);
            $updateUserSql .= ", password = :password";
            $updateUserParams[':password'] = $passHash;

            // If it was the first login, mark it as not first login anymore
            if ($primer_login == 1) {
                $updateUserSql .= ", primer_login = 0";
            }
        }
        $updateUserSql .= " WHERE id_usuario = :id_usuario";
        $stmtUpdateUser = $conexion->prepare($updateUserSql);
        $stmtUpdateUser->execute($updateUserParams);

        $conexion->commit();

        // Log audit trail
        registrarAuditoria($conexion, $current_user_id, "Actualizó su perfil de usuario (Rol: $current_user_rol)");

        echo json_encode(["success" => true, "message" => "Perfil actualizado correctamente."]);

    } else {
        throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>