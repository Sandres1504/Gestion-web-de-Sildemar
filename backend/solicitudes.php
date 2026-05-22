<?php
ob_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once 'db.php';
require_once 'logger.php';






$action = $_GET['action'] ?? '';

try {

    // 1. LISTAR SOLICITUDES
    if ($action === 'list') {
        // --- Lógica de Auto-Eliminación (48 horas) ---
        // Buscamos solicitudes pendientes de más de 48 horas
        $sqlCheck = "SELECT s.id_solicitud, p.nombre as cliente, p.telefono as cliente_telefono 
                     FROM solicitud s 
                     INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                     INNER JOIN persona p ON c.id_persona = p.id_persona
                     WHERE s.estado = 'Pendiente' 
                     AND s.fecha_solicitud < DATE_SUB(NOW(), INTERVAL 48 HOUR)";
        
        $stmtCheck = $conexion->query($sqlCheck);
        $eliminadas = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($eliminadas)) {
            $ids = array_column($eliminadas, 'id_solicitud');
            $idsStr = implode(',', $ids);
            
            // Eliminamos las solicitudes (detalle_solicitud se elimina por cascada en la BD)
            $conexion->query("DELETE FROM solicitud WHERE id_solicitud IN ($idsStr)");
            
            // Registrar en auditoría
            foreach($ids as $id_del) {
                registrarAuditoria($conexion, 0, "SISTEMA eliminó automáticamente solicitud ID: $id_del por expiración de 48h");
            }
        }
        // ---------------------------------------------

        $sql = "SELECT 
                    s.id_solicitud, 
                    p.nombre AS cliente, 
                    p.telefono AS cliente_telefono,
                    s.fecha_solicitud AS fecha, 
                    s.estado, 
                    s.total,
                    pv.nombre AS vendedor_nombre,
                    pv.cedula AS vendedor_cedula
                FROM solicitud s
                INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                INNER JOIN persona p ON c.id_persona = p.id_persona
                LEFT JOIN empleado e ON s.id_vendedor = e.id_empleado
                LEFT JOIN usuario uv ON e.id_usuario = uv.id_usuario
                LEFT JOIN persona pv ON uv.id_persona = pv.id_persona
                WHERE (s.archivada = 0 OR s.archivada IS NULL)
                ORDER BY s.fecha_solicitud DESC";

        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($solicitudes as &$sol) {
            $sol['total'] = floatval($sol['total']);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            "success" => true, 
            "data" => $solicitudes,
            "eliminadas" => $eliminadas // Enviamos las que acabamos de borrar
        ]);
        exit;
    }

    // 2. DETALLES
    elseif ($action === 'details') {

        $id_solicitud = $_GET['id'] ?? 0;
        if (!$id_solicitud) throw new Exception("ID requerido");

        $sql = "SELECT 
                    pr.nombre_producto, 
                    d.precio_unitario, 
                    d.cantidad, 
                    d.subtotal
                FROM detalle_solicitud d
                INNER JOIN producto pr ON d.id_producto = pr.id_producto
                WHERE d.id_solicitud = :id
                ORDER BY d.id_detalle";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id' => $id_solicitud]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular la suma real desde los detalles
        $sumaReal = 0;
        foreach ($detalles as &$det) {
            $det['precio_unitario'] = floatval($det['precio_unitario']);
            $det['cantidad'] = intval($det['cantidad']);
            $det['subtotal'] = floatval($det['subtotal']);
            $sumaReal += $det['subtotal'];
        }

        // Obtener el total guardado en la tabla solicitud
        $sqlTotal = "SELECT total FROM solicitud WHERE id_solicitud = :id";
        $stmtTotal = $conexion->prepare($sqlTotal);
        $stmtTotal->execute([':id' => $id_solicitud]);
        $totalGuardado = $stmtTotal->fetch(PDO::FETCH_ASSOC);
        
        $totalGuardadoValor = $totalGuardado ? floatval($totalGuardado['total']) : 0;

        if (ob_get_length()) ob_clean();
        echo json_encode([
            "success" => true, 
            "data" => $detalles,
            "detalles_count" => count($detalles),
            "suma_real_subtotales" => $sumaReal,
            "total_guardado_bd" => $totalGuardadoValor,
            "hay_discrepancia" => ($sumaReal != $totalGuardadoValor)
        ]);
        exit;
    }

    // 3. ACTUALIZAR ESTADO
    elseif ($action === 'update_status') {

        $id_solicitud = $_GET['id'] ?? 0;
        $estado = $_GET['estado'] ?? '';

        if (!$id_solicitud || !$estado) throw new Exception("Datos incompletos");

        $valid_states = ['Pendiente', 'Aprobada', 'Rechazada', 'Entregada'];
        if (!in_array($estado, $valid_states)) throw new Exception("Estado inválido");

        $stmt = $conexion->prepare("UPDATE solicitud SET estado = :estado WHERE id_solicitud = :id");
        $stmt->execute([
            ':estado' => $estado,
            ':id' => $id_solicitud
        ]);

        $activo_id = $_GET['activo_id'] ?? null;
        if ($activo_id) {
            registrarAuditoria($conexion, $activo_id, "Gerente Operacional cambió estado de solicitud ID: " . $id_solicitud . " a " . $estado);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true]);
        exit;

    }

    // 4. ARCHIVAR SOLICITUDES (OCULTAR DE LA VISTA)
    elseif ($action === 'archive') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $where = ["1=1"];
        $params = [];

        if (!empty($data['fecha_desde'])) {
            $where[] = "s.fecha_solicitud >= ?";
            $params[] = $data['fecha_desde'] . " 00:00:00";
        }
        if (!empty($data['fecha_hasta'])) {
            $where[] = "s.fecha_solicitud <= ?";
            $params[] = $data['fecha_hasta'] . " 23:59:59";
        }
        if (!empty($data['id_desde'])) {
            $where[] = "s.id_solicitud >= ?";
            $params[] = $data['id_desde'];
        }
        if (!empty($data['id_hasta'])) {
            $where[] = "s.id_solicitud <= ?";
            $params[] = $data['id_hasta'];
        }
        if (!empty($data['cliente'])) {
            $where[] = "(p.nombre LIKE ? OR p.cedula LIKE ?)";
            $busqueda = "%" . $data['cliente'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        if (!empty($data['vendedor'])) {
            $where[] = "pv.cedula LIKE ?";
            $params[] = "%" . $data['vendedor'] . "%";
        }
        if (!empty($data['estado'])) {
            $where[] = "s.estado = ?";
            $params[] = $data['estado'];
        }

        $sql = "UPDATE solicitud s
                INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                INNER JOIN persona p ON c.id_persona = p.id_persona
                LEFT JOIN empleado e ON s.id_vendedor = e.id_empleado
                LEFT JOIN usuario uv ON e.id_usuario = uv.id_usuario
                LEFT JOIN persona pv ON uv.id_persona = pv.id_persona
                SET s.archivada = 1 
                WHERE " . implode(" AND ", $where);

        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        $activo_id = $data['activo_id'] ?? 0;
        registrarAuditoria($conexion, $activo_id, "Archivó $count solicitudes masivamente.");

        echo json_encode(["success" => true, "count" => $count]);
        exit;
    }

    // 5. LISTAR ARCHIVADAS
    elseif ($action === 'list_archived') {
        $sql = "SELECT 
                    s.id_solicitud, 
                    p.nombre AS cliente, 
                    p.telefono AS cliente_telefono,
                    s.fecha_solicitud AS fecha, 
                    s.estado, 
                    s.total,
                    pv.nombre AS vendedor_nombre,
                    pv.cedula AS vendedor_cedula
                FROM solicitud s
                INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                INNER JOIN persona p ON c.id_persona = p.id_persona
                LEFT JOIN empleado e ON s.id_vendedor = e.id_empleado
                LEFT JOIN usuario uv ON e.id_usuario = uv.id_usuario
                LEFT JOIN persona pv ON uv.id_persona = pv.id_persona
                WHERE s.archivada = 1
                ORDER BY s.fecha_solicitud DESC";

        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($solicitudes as &$sol) {
            $sol['total'] = floatval($sol['total']);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true, "data" => $solicitudes]);
        exit;
    }

    // 6. RESTAURAR SOLICITUDES (QUITAR DEL ARCHIVO)
    elseif ($action === 'restore') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $where = ["s.archivada = 1"];
        $params = [];

        if (!empty($data['id_solicitud'])) {
            $where[] = "s.id_solicitud = ?";
            $params[] = $data['id_solicitud'];
        } else {
            if (!empty($data['fecha_desde'])) {
                $where[] = "s.fecha_solicitud >= ?";
                $params[] = $data['fecha_desde'] . " 00:00:00";
            }
            if (!empty($data['fecha_hasta'])) {
                $where[] = "s.fecha_solicitud <= ?";
                $params[] = $data['fecha_hasta'] . " 23:59:59";
            }
            if (!empty($data['id_desde'])) {
                $where[] = "s.id_solicitud >= ?";
                $params[] = $data['id_desde'];
            }
            if (!empty($data['id_hasta'])) {
                $where[] = "s.id_solicitud <= ?";
                $params[] = $data['id_hasta'];
            }
            if (!empty($data['cliente'])) {
                $where[] = "(p.nombre LIKE ? OR p.cedula LIKE ?)";
                $busqueda = "%" . $data['cliente'] . "%";
                $params[] = $busqueda;
                $params[] = $busqueda;
            }
            if (!empty($data['vendedor'])) {
                $where[] = "(pv.nombre LIKE ? OR pv.cedula LIKE ?)";
                $busqueda = "%" . $data['vendedor'] . "%";
                $params[] = $busqueda;
                $params[] = $busqueda;
            }
            if (!empty($data['estado'])) {
                $where[] = "s.estado = ?";
                $params[] = $data['estado'];
            }
        }

        $sql = "UPDATE solicitud s
                INNER JOIN cliente c ON s.id_cliente = c.id_cliente
                INNER JOIN persona p ON c.id_persona = p.id_persona
                LEFT JOIN empleado e ON s.id_vendedor = e.id_empleado
                LEFT JOIN usuario uv ON e.id_usuario = uv.id_usuario
                LEFT JOIN persona pv ON uv.id_persona = pv.id_persona
                SET s.archivada = 0 
                WHERE " . implode(" AND ", $where);

        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        $activo_id = $data['activo_id'] ?? 0;
        registrarAuditoria($conexion, $activo_id, "Restauró $count solicitudes del archivo.");

        echo json_encode(["success" => true, "count" => $count]);
        exit;
    }

    // DEFAULT
    throw new Exception("Acción no válida");

} catch (Exception $e) {

    if (ob_get_length()) ob_clean();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>