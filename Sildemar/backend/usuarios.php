<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $stmt = $conexion->query("SELECT u.id_usuario, p.nombre as nombre_usuario, u.correo, u.rol FROM usuario u JOIN persona p ON u.id_persona = p.id_persona");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'create') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Seguridad: Verificar quien hace la petición. Solo el Administrador puede crear usuarios.
        $role_solicitante = $data['role_solicitante'] ?? 'Empleado';
        if ($role_solicitante !== 'Administrador') {
            throw new Exception("No tienes permisos para crear usuarios. Solo el Administrador puede hacerlo.");
        }

        if (!$data)
            throw new Exception("Datos incompletos");

        $conexion->beginTransaction();
        $cedula = uniqid();
        $sqlP = "INSERT INTO persona (cedula, nombre) VALUES (:cedula, :nombre)";
        $stmtP = $conexion->prepare($sqlP);
        $stmtP->execute([':cedula' => $cedula, ':nombre' => $data['nombre']]);
        $id_persona = $conexion->lastInsertId();

        $sql = "INSERT INTO usuario (correo, contraseña, rol, id_persona) VALUES (:corr, :cla, :rol, :id_persona)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':corr' => $data['correo'],
            ':cla' => password_hash($data['clave'], PASSWORD_DEFAULT), // Encriptación de seguridad
            ':rol' => 'Empleado', // Siempre se asignará el rol 'Empleado'
            ':id_persona' => $id_persona
        ]);
        $conexion->commit();
        echo json_encode(["success" => true]);
    }

    elseif ($action === 'delete') {
        $id = $_GET['id'] ?? null;
        if (!$id)
            throw new Exception("ID no proporcionado");

        $stmt = $conexion->prepare("DELETE FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["success" => true]);
    }
}
catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>