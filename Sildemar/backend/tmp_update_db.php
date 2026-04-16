<?php
require_once 'db.php';

try {
    $conexion->exec("ALTER TABLE usuario ADD COLUMN codigo_recuperacion VARCHAR(10) NULL");
    $conexion->exec("ALTER TABLE usuario ADD COLUMN expiracion_codigo TIMESTAMP NULL");
    echo "SUCCESS";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS (Already exists)";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
?>
