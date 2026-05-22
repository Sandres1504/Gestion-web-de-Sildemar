<?php
require_once 'c:/xampp/htdocs/Sildemar/backend/db.php';

try {
    // Corregir Suspensión
    $stmt1 = $conexion->prepare("UPDATE categoria SET nombre_categoria = 'Suspensión' WHERE nombre_categoria LIKE '%uspensi%'");
    $stmt1->execute();
    
    // Corregir Eléctrico
    $stmt2 = $conexion->prepare("UPDATE categoria SET nombre_categoria = 'Eléctrico' WHERE nombre_categoria LIKE '%l%ctrico%'");
    $stmt2->execute();
    
    echo "Base de datos actualizada correctamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
