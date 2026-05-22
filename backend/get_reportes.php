<?php
session_start();
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

        $stmt4 = $conexion->query("SELECT tasa_dolar FROM configuracion WHERE id = 1");
        $tasa = $stmt4->fetch(PDO::FETCH_ASSOC)['tasa_dolar'] ?? 0;

        echo json_encode([
            "critico" => $critico,
            "valor_inventario" => number_format($valorInv, 2, '.', ''),
            "total_productos" => $totalProd,
            "tasa_dolar" => $tasa
        ]);
    }

    /* ACTUALIZAR TASA (Solo Admin) */
    elseif ($action === 'update_rate') {
        // Verificación de seguridad
        $rol_sesion = $_SESSION['usuario_rol'] ?? '';
        if ($rol_sesion !== 'Gerente del Sistema' && $rol_sesion !== 'Gerente Operacional') {
            throw new Exception("No autorizado para cambiar la tasa.");
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $nuevaTasa = $data['tasa'] ?? 0;
        
        $stmt = $conexion->prepare("UPDATE configuracion SET tasa_dolar = ? WHERE id = 1");
        $stmt->execute([$nuevaTasa]);
        
        echo json_encode(["success" => true]);
    }

    /* OBTENER TASA PUBLICA */
    elseif ($action === 'get_rate') {
        $stmt = $conexion->query("SELECT tasa_dolar FROM configuracion WHERE id = 1");
        $tasa = $stmt->fetch(PDO::FETCH_ASSOC)['tasa_dolar'] ?? 0;
        
        echo json_encode(["success" => true, "tasa_dolar" => $tasa]);
    }

    /* 2. FILTRO DE INVENTARIO */
    elseif ($action === 'filter') {
        $tipo = $_GET['tipo'] ?? 'inventario';
        $data = [];

        if ($tipo === 'inventario') {
            $query = "SELECT nombre_producto, descripcion, stock_actual, precio, marca_repuesto as marca 
                      FROM producto ORDER BY stock_actual ASC";
            $stmt = $conexion->query($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } 
        elseif ($tipo === 'productos_top') {
            // Productos más vendidos del mes actual
            $query = "SELECT pr.nombre_producto, pr.marca_repuesto as marca, 
                             SUM(ds.cantidad) as total_vendido, 
                             SUM(ds.subtotal) as ingresos
                      FROM detalle_solicitud ds
                      JOIN producto pr ON ds.id_producto = pr.id_producto
                      JOIN solicitud s ON ds.id_solicitud = s.id_solicitud
                      WHERE s.estado = 'Entregada' AND MONTH(s.fecha_solicitud) = MONTH(CURRENT_DATE())
                      GROUP BY pr.id_producto
                      ORDER BY total_vendido DESC";
            $stmt = $conexion->query($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } 
        elseif ($tipo === 'clientes_top') {
            // Clientes que más han comprado en el mes
            $query = "SELECT p.nombre as cliente, 
                             COUNT(s.id_solicitud) as cantidad_pedidos, 
                             SUM(s.total) as total_gastado
                      FROM solicitud s
                      JOIN cliente c ON s.id_cliente = c.id_cliente
                      JOIN persona p ON c.id_persona = p.id_persona
                      WHERE s.estado = 'Entregada' AND MONTH(s.fecha_solicitud) = MONTH(CURRENT_DATE())
                      GROUP BY c.id_cliente
                      ORDER BY total_gastado DESC";
            $stmt = $conexion->query($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($data);
    }

    /* 3. SOLICITUDES PARA EL EMPLEADO (CON DATOS DE CONTACTO) */
    elseif ($action === 'empleado_solicitudes') {
        $sesionUsuario = $_SESSION['usuario_id'] ?? null;
        $rolSesion = $_SESSION['usuario_rol'] ?? '';

        if (!$sesionUsuario || $rolSesion !== 'Empleado') {
            throw new Exception("Acceso no autorizado");
        }

        $userId = intval($sesionUsuario);

        $query = "SELECT 
                    s.id_solicitud,
                    p.nombre AS cliente,
                    p.telefono,
                    p.direccion,
                    p.correo,
                    s.estado,
                    GROUP_CONCAT(pr.nombre_producto SEPARATOR ', ') AS productos,
                    SUM(ds.cantidad) AS total_items
                    FROM solicitud s
                    INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                    INNER JOIN persona p ON c.id_persona = p.id_persona
                    INNER JOIN empleado e ON s.id_vendedor = e.id_empleado
                    INNER JOIN usuario uv ON e.id_usuario = uv.id_usuario
                    LEFT JOIN detalle_solicitud ds ON s.id_solicitud = ds.id_solicitud
                    LEFT JOIN producto pr ON ds.id_producto = pr.id_producto
                    WHERE uv.id_usuario = :user_id
                    GROUP BY s.id_solicitud
                    ORDER BY s.id_solicitud DESC";

        $stmt = $conexion->prepare($query);
        $stmt->execute([':user_id' => $userId]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

}
catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de BD: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>