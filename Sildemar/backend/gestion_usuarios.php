<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'create') {
        $datos = json_decode(file_get_contents('php://input'), true);

        // Seguridad: Verificar quien hace la petición. Solo el Administrador puede crear usuarios.
        $role_solicitante = $datos['role_solicitante'] ?? 'Empleado';
        if ($role_solicitante !== 'Administrador') {
            throw new Exception("No tienes permisos para crear usuarios. Solo el Administrador puede hacerlo.");
        }

        $nombre = $datos['nombre'] ?? '';
        $email = $datos['email'] ?? '';
        $clave = $datos['clave'] ?? '';
        $rol = 'Empleado'; // Siempre se asignará el rol 'Empleado'
        $cedula = $datos['cedula'] ?? uniqid();

        $conexion->beginTransaction();

        $stmtP = $conexion->prepare("SELECT id_persona FROM persona WHERE cedula = :ced");
        $stmtP->execute([':ced' => $cedula]);
        $persona = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            $stmtInsertP = $conexion->prepare("INSERT INTO persona (cedula, nombre) VALUES (:cedula, :nombre)");
            $stmtInsertP->execute([':cedula' => $cedula, ':nombre' => $nombre]);
            $id_persona = $conexion->lastInsertId();
        } else {
            $id_persona = $persona['id_persona'];
        }

        $passHash = password_hash($clave, PASSWORD_DEFAULT);
        $sqlU = "INSERT INTO usuario (correo, contraseña, rol, id_persona) 
        VALUES (:correo, :password, :rol, :id_persona)";
        $stmtU = $conexion->prepare($sqlU);
        $stmtU->execute([
            ':correo' => $email,
            ':password' => $passHash,
            ':rol' => $rol,
            ':id_persona' => $id_persona
        ]);

        $id_usuario = $conexion->lastInsertId();

        $sqlE = "INSERT INTO empleado (cargo, id_usuario) VALUES (:cargo, :id_usuario)";
        $stmtE = $conexion->prepare($sqlE);
        $stmtE->execute([
            ':cargo' => 'Operador', // Cargo genérico por defecto
            ':id_usuario' => $id_usuario
        ]);

        $conexion->commit();
        echo json_encode(['success' => true]);

    }
    else if ($action === 'list') {
        $stmt = $conexion->query("SELECT u.id_usuario, u.correo, u.rol, p.nombre 
        FROM usuario u JOIN persona p ON u.id_persona = p.id_persona");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);

    }
    else if ($action === 'update') {
        // Asumiendo que pueden actualizar el rol y el nombre
        $datos = json_decode(file_get_contents('php://input'), true);
        $id_usuario = $datos['id_usuario'] ?? null;
        $nuevo_rol = 'Empleado'; // Siempre se asignará el rol 'Empleado'

        if (!$id_usuario)
            throw new Exception("ID de usuario no proporcionado");

        $sql = "UPDATE usuario SET rol = :rol WHERE id_usuario = :id_usuario";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':rol' => $nuevo_rol, ':id_usuario' => $id_usuario]);

        echo json_encode(['success' => true]);

    }
    else if ($action === 'delete') {
        $id_usuario = $_GET['id'] ?? null;
        if (!$id_usuario)
            throw new Exception("ID proporcionado inválido");

        // Obtenemos id_persona
        $stmtSelect = $conexion->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
        $stmtSelect->execute([':id' => $id_usuario]);
        $user_data = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        $conexion->beginTransaction();

        // Delete User
        $stmt = $conexion->prepare("DELETE FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);

        // Delete Persona si existe
        if ($user_data && $user_data['id_persona']) {
            $stmtPersona = $conexion->prepare("DELETE FROM persona WHERE id_persona = :id_persona");
            $stmtPersona->execute([':id_persona' => $user_data['id_persona']]);
        }

        $conexion->commit();
        echo json_encode(['success' => true]);
    }
}
catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>