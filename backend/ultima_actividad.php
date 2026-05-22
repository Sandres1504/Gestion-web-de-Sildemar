<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $stmt = $conexion->prepare("
        SELECT a.accion, a.fecha, p.nombre as usuario
        FROM auditoria a
        LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
        LEFT JOIN persona p ON u.id_persona = p.id_persona
        ORDER BY a.fecha DESC
        LIMIT 5
    ");
    $stmt->execute();
    $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'actividad' => $actividad]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>