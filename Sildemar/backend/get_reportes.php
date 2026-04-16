<?php
header('Content-Type: application/json');
require_once 'db.php'; 

$action = $_GET['action'] ?? '';

try {
    /* 1. ESTADÍSTICAS GENERALES */
    if ($action === 'stats') {
        $stmt1 = $conexion->query("SELECT COUNT(*) as total FROM producto WHERE stock_actual < 5");
        $critico = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt2 = $conexion->query("SELECT SUM(precio * stock_actual) as total FROM producto");
        $valorInv = $stmt2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stmt3 = $conexion->query("SELECT COUNT(*) as total FROM producto");
        $totalProd = $stmt3->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            "critico" => $critico,
            "valor_inventario" => number_format($valorInv, 2, '.', ''),
            "total_productos" => $totalProd
        ]);
    }

    /* 2. FILTRO DE INVENTARIO */
    elseif ($action === 'filter') {
        $tipo = $_GET['tipo'] ?? 'inventario';

        // Seleccionamos directamente marca_repuesto de la columna
        $query = "SELECT nombre_producto, descripcion, stock_actual, precio, marca_repuesto as marca 
        FROM producto";
        
        if ($tipo === 'bajo-stock') {
            $query .= " WHERE stock_actual < 5";
        }
        $query .= " ORDER BY stock_actual ASC";

        $stmt = $conexion->query($query);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /* 3. SOLICITUDES PARA EL EMPLEADO (CON DATOS DE CONTACTO) */
    elseif ($action === 'empleado_solicitudes') {
        
        $query = "SELECT 
                    s.id_solicitud,
                    p.nombre AS cliente,
                    p.telefono,
                    p.direccion,
                    u.correo,
                    s.estado,
                    GROUP_CONCAT(pr.nombre_producto SEPARATOR ', ') AS productos,
                    SUM(ds.cantidad) AS total_items
                    FROM solicitud s
                    INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                    INNER JOIN persona p ON c.id_persona = p.id_persona
                    LEFT JOIN usuario u ON p.id_persona = u.id_persona
                    LEFT JOIN detalle_solicitud ds ON s.id_solicitud = ds.id_solicitud
                    LEFT JOIN producto pr ON ds.id_producto = pr.id_producto
                    GROUP BY s.id_solicitud
                    ORDER BY s.id_solicitud DESC";

        $stmt = $conexion->prepare($query);
        $stmt->execute();

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

}
catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>