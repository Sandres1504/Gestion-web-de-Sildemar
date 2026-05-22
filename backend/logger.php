<?php
/**
 * Registra una acción en la tabla de auditoría.
 * 
 * @param PDO $conexion Objeto de conexión PDO activo.
 * @param int|null $id_usuario ID del usuario que realiza la acción (puede obtenerse de la sesión o payload).
 * @param string $accion Descripción detallada de la acción realizada.
 */
function registrarAuditoria($conexion, $id_usuario, $accion) {
    try {
        $stmt = $conexion->prepare("INSERT INTO auditoria (id_usuario, accion) VALUES (:id_usuario, :accion)");
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':accion' => $accion
        ]);
    } catch (PDOException $e) {
        // En un caso real se podría loguear a un archivo de texto si falla la BD.
        error_log("Error al registrar auditoría: " . $e->getMessage());
    }
}
?>
