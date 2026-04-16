<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        // Obtenemos solo productos con stock disponible
        $stmt = $conexion->query("SELECT * FROM producto WHERE stock_actual > 0");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos as &$p) {
            $p['marca_repuesto'] = "N/A";
            $p['marca_carro'] = "N/A";
            $p['transmision'] = "N/A";
            $p['codigo'] = "PROD-" . $p['id_producto'];

            if (!empty($p['descripcion'])) {
                $desc = json_decode($p['descripcion'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($desc)) {
                    $p['marca_repuesto'] = $desc['marca_repuesto'] ?? "N/A";
                    $p['marca_carro'] = $desc['marca_vehiculo'] ?? "N/A";
                    $p['transmision'] = $desc['transmision'] ?? "N/A";
                    if (!empty($desc['codigo'])) {
                        $p['codigo'] = $desc['codigo'];
                    }
                }
            }
        }

        echo json_encode($productos);
    }

    elseif ($action === 'check_cedula') {
        $cedula = $_GET['cedula'] ?? '';
        if (!$cedula) throw new Exception("Cedula requerida");
        
        $stmt = $conexion->prepare("SELECT nombre FROM persona WHERE cedula = :ced");
        $stmt->execute([':ced' => $cedula]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            echo json_encode(["success" => true, "exists" => true, "nombre" => $persona['nombre']]);
        } else {
            echo json_encode(["success" => true, "exists" => false]);
        }
    }

    elseif ($action === 'confirmar') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (empty($data['carrito']))
            throw new Exception("El carrito está vacío");

        $conexion->beginTransaction();

        // 1. Obtener o insertar persona y cliente
        // Refuerzo de Seguridad XSS: Limpieza obligatoria antes de base de datos
        $cedula = htmlspecialchars(trim($data['cedula'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nombre = htmlspecialchars(trim($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $direccion = isset($data['direccion']) ? htmlspecialchars(trim($data['direccion']), ENT_QUOTES, 'UTF-8') : null;
        $telefono = isset($data['telefono']) ? htmlspecialchars(trim($data['telefono']), ENT_QUOTES, 'UTF-8') : null;

        $stmtP = $conexion->prepare("SELECT id_persona FROM persona WHERE cedula = :ced");
        $stmtP->execute([':ced' => $cedula]);
        $persona = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            $stmtInsertP = $conexion->prepare("INSERT INTO persona (cedula, nombre, direccion, telefono) VALUES (:ced, :nom, :dir, :tel)");
            $stmtInsertP->execute([':ced' => $cedula, ':nom' => $nombre, ':dir' => $direccion, ':tel' => $telefono]);
            $id_persona = $conexion->lastInsertId();

            $stmtInsertC = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (:id_p)");
            $stmtInsertC->execute([':id_p' => $id_persona]);
            $id_cliente = $conexion->lastInsertId();
        }
        else {
            $id_persona = $persona['id_persona'];
            $stmtC = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_persona = :id_p");
            $stmtC->execute([':id_p' => $id_persona]);
            $cliente = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$cliente) {
                $stmtInsertC = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (:id_p)");
                $stmtInsertC->execute([':id_p' => $id_persona]);
                $id_cliente = $conexion->lastInsertId();
            }
            else {
                $id_cliente = $cliente['id_cliente'];
            }
        }

        // 2. Crear solicitud general
        $totalGeneral = array_reduce($data['carrito'], function ($sum, $item) {
            return $sum + ($item['precio'] * $item['cant']);
        }, 0);

        $sqlSol = "INSERT INTO solicitud (id_cliente, total, estado, fecha_solicitud) VALUES (:id_cli, :tot, 'Pendiente', NOW())";
        $stmtSol = $conexion->prepare($sqlSol);
        $stmtSol->execute([':id_cli' => $id_cliente, ':tot' => $totalGeneral]);
        $id_solicitud = $conexion->lastInsertId();


        foreach ($data['carrito'] as $item) {
            $totalItem = $item['precio'] * $item['cant'];

            // 3. Insertar detalle
            $sqlDetalle = "INSERT INTO detalle_solicitud (id_solicitud, id_producto, cantidad, precio_unitario, subtotal) 
                    VALUES (:id_sol, :id_p, :cant, :precio, :subtot)";
            $stmtD = $conexion->prepare($sqlDetalle);
            $stmtD->execute([
                ':id_sol' => $id_solicitud,
                ':id_p' => $item['id_producto'],
                ':cant' => $item['cant'],
                ':precio' => $item['precio'],
                ':subtot' => $totalItem
            ]);

            // 4. Restar stock
            $upd = $conexion->prepare("UPDATE producto SET stock_actual = stock_actual - :cant 
            WHERE id_producto = :id_p AND stock_actual >= :cant");
            $upd->execute([':cant' => $item['cant'], ':id_p' => $item['id_producto']]);

            if ($upd->rowCount() === 0) {
                throw new Exception("Stock insuficiente para ID: " . $item['id_producto']);
            }
        }

        $conexion->commit();
        echo json_encode(["success" => true]);
    }
}
catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction())
        $conexion->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>