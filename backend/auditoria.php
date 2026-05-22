<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $query = "
        SELECT 
            a.fecha as fecha, 
            COALESCE(p.nombre, u.correo, 'Sistema') as usuario, 
            a.accion 
        FROM auditoria a
        LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
        LEFT JOIN persona p ON u.id_persona = p.id_persona
        ORDER BY a.fecha DESC
        LIMIT 100
    ";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $resultados
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar la auditoría: ' . $e->getMessage()
    ]);
}
?>
