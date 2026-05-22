<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // Total de usuarios
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM usuario");
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de empleados (usuarios con rol Empleado = id_rol 3)
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE id_rol = 3");
    $total_empleados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'total_usuarios' => $total_usuarios,
        'total_empleados' => $total_empleados
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>