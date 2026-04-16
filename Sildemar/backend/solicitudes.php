<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'create') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data)
            throw new Exception("Datos inválidos");

        // Iniciamos transacción para asegurar la integridad
        $conexion->beginTransaction();

        // 1. Obtener precio actual para guardar el total en la solicitud
        $sqlPrecio = "SELECT precio FROM producto WHERE id_producto = :id_p";
        $stmtPrecio = $conexion->prepare($sqlPrecio);
        $stmtPrecio->execute([':id_p' => $data['id_producto']]);
        $producto = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

        if (!$producto)
            throw new Exception("Producto no encontrado");
        $total = $producto['precio'] * $data['cantidad'];

        // 2. Insertar la solicitud (Estado inicial: Pendiente)
        $sqlSol = "INSERT INTO solicitudes (cliente, id_producto, cantidad, fecha, estado, total) 
        VALUES (:cliente, :id_p, :cant, NOW(), 'Pendiente', :total)";
        $stmtSol = $conexion->prepare($sqlSol);
        $stmtSol->execute([
            ':cliente' => $data['cliente'],
            ':id_p' => $data['id_producto'],
            ':cant' => $data['cantidad'],
            ':total' => $total
        ]);

        // 3. Restar el stock del inventario
        $sqlStock = "UPDATE producto SET stock_actual = stock_actual - :cant 
        WHERE id_producto = :id_p AND stock_actual >= :cant";
        $stmtStock = $conexion->prepare($sqlStock);
        $stmtStock->execute([
            ':cant' => $data['cantidad'],
            ':id_p' => $data['id_producto']
        ]);

        if ($stmtStock->rowCount() === 0) {
            throw new Exception("Stock insuficiente para realizar la venta");
        }

        $conexion->commit();
        echo json_encode(["success" => true]);
    }

    elseif ($action === 'list') {
        $sql = "SELECT s.id_solicitud, s.estado, s.total, DATE_FORMAT(s.fecha_solicitud, '%Y-%m-%d %H:%i') as fecha, p.nombre as cliente 
                FROM solicitud s 
                JOIN cliente c ON s.id_cliente = c.id_cliente 
                JOIN persona p ON c.id_persona = p.id_persona 
                ORDER BY s.id_solicitud DESC";
        $stmt = $conexion->query($sql);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $solicitudes]);
    }

    elseif ($action === 'update_status') {
        $id = $_GET['id'] ?? null;
        $nuevoEstado = $_GET['estado'] ?? '';

        if ($nuevoEstado === 'En Proceso') $nuevoEstado = 'Aprobada';
        if ($nuevoEstado === 'Completada') $nuevoEstado = 'Entregada';

        if (!$id || !$nuevoEstado) throw new Exception("Faltan parámetros");

        $conexion->beginTransaction();

        $stmtSelect = $conexion->prepare("SELECT estado FROM solicitud WHERE id_solicitud = :id FOR UPDATE");
        $stmtSelect->execute([':id' => $id]);
        $solicitud = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) throw new Exception("Solicitud no encontrada");

        if ($solicitud['estado'] !== $nuevoEstado) {
            // Si se rechaza, devolvemos el stock al inventario
            if ($nuevoEstado === 'Rechazada' && $solicitud['estado'] !== 'Rechazada') {
                $stmtDetalles = $conexion->prepare("SELECT id_producto, cantidad FROM detalle_solicitud WHERE id_solicitud = :id");
                $stmtDetalles->execute([':id' => $id]);
                $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

                foreach ($detalles as $det) {
                    $sqlStock = "UPDATE producto SET stock_actual = stock_actual + :cant WHERE id_producto = :id_p";
                    $conexion->prepare($sqlStock)->execute([':cant' => $det['cantidad'], ':id_p' => $det['id_producto']]);
                }
            }
        }

        $sql = "UPDATE solicitud SET estado = :estado WHERE id_solicitud = :id";
        $conexion->prepare($sql)->execute([':estado' => $nuevoEstado, ':id' => $id]);

        $conexion->commit();
        echo json_encode(["success" => true]);
    }

    elseif ($action === 'delete') {
        $id = $_GET['id'] ?? null;
        if (!$id) throw new Exception("ID proporcionado inválido");

        $conexion->beginTransaction();

        $stmtSelect = $conexion->prepare("SELECT estado FROM solicitud WHERE id_solicitud = :id");
        $stmtSelect->execute([':id' => $id]);
        $solicitud = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if ($solicitud) {
            if ($solicitud['estado'] !== 'Rechazada') {
                $stmtDetalles = $conexion->prepare("SELECT id_producto, cantidad FROM detalle_solicitud WHERE id_solicitud = :id");
                $stmtDetalles->execute([':id' => $id]);
                $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

                foreach ($detalles as $det) {
                    $sqlStock = "UPDATE producto SET stock_actual = stock_actual + :cant WHERE id_producto = :id_p";
                    $conexion->prepare($sqlStock)->execute([':cant' => $det['cantidad'], ':id_p' => $det['id_producto']]);
                }
            }

            $sql = "DELETE FROM solicitud WHERE id_solicitud = :id";
            $conexion->prepare($sql)->execute([':id' => $id]);
        }

        $conexion->commit();
        echo json_encode(["success" => true]);
    }

    elseif ($action === 'details') {
        $id = $_GET['id'] ?? null;
        if (!$id) throw new Exception("ID proporcionado inválido");

        $sql = "SELECT d.cantidad, d.precio_unitario, d.subtotal, p.id_producto, p.nombre_producto, p.descripcion 
                FROM detalle_solicitud d
                JOIN producto p ON d.id_producto = p.id_producto
                WHERE d.id_solicitud = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id' => $id]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($detalles as &$item) {
            $item['codigo'] = "PROD-" . $item['id_producto'];
            if (!empty($item['descripcion'])) {
                $desc = json_decode($item['descripcion'], true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($desc['codigo'])) {
                    $item['codigo'] = $desc['codigo'];
                }
            }
            unset($item['descripcion']);
        }

        echo json_encode(["success" => true, "data" => $detalles]);
    }

}

catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction())
        $conexion->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>