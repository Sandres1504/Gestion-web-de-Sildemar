<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cedula = $_POST['cedula'];
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $clave = $_POST['clave']; // Se recibira como "Clave de Acceso"
    $rol = 'Empleado'; // Siempre se asignará el rol 'Empleado'

    try {
        $conexion->beginTransaction();

        // 1. Insertar en Persona
        $stmt1 = $conexion->prepare("INSERT INTO persona (cedula, nombre) VALUES (?, ?)");
        $stmt1->execute([$cedula, $nombre]);
        $id_persona = $conexion->lastInsertId();

        // 2. Encriptar Clave
        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);

        // 3. Insertar en Usuario con el ROL elegido
        $stmt2 = $conexion->prepare("INSERT INTO usuario (correo, contraseña, rol, id_persona) VALUES (?, ?, ?, ?)");
        $stmt2->execute([$correo, $clave_hash, $rol, $id_persona]);

        $conexion->commit();
        echo json_encode(["success" => true, "message" => "Personal registrado con exito"]);

    }
    catch (Exception $e) {
        $conexion->rollBack();
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
}
?>