<?php
ob_start();
require_once 'secure_session.php';
header('Content-Type: application/json');
// Evitar cacheo para que los cambios en stock y tasa se vean al momento
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once 'db.php';

$action = $_GET['action'] ?? '';


try {
    // 1. LISTAR PRODUCTOS (CATÁLOGO)
    if ($action === 'list') {
        // Sincronizar con ID 1
        $stmtTasa = $conexion->query("SELECT tasa_dolar FROM configuracion WHERE id = 1");
        $resTasa = $stmtTasa->fetch(PDO::FETCH_ASSOC);
        
        if (!$resTasa) throw new Exception("Configuración de tasa no encontrada");
        $tasa = (float)$resTasa['tasa_dolar'];

        // Obtener productos con stock
        $stmt = $conexion->query("SELECT id_producto, codigo, nombre_producto, marca_repuesto, marca_carro, modelo_vehiculo, categoria, ano AS anio_carro, transmision, precio, stock_actual, imagen FROM producto WHERE stock_actual > 0");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos as &$p) {
            $p['precio_bs'] = round($p['precio'] * $tasa, 2);
            $p['marca_repuesto'] = $p['marca_repuesto'] ?? "N/A";
            $p['marca_carro'] = $p['marca_carro'] ?? "Universal";
            $p['modelo_vehiculo'] = $p['modelo_vehiculo'] ?? "N/A";
            $p['transmision'] = $p['transmision'] ?? "N/A";
            $p['anio_carro'] = $p['anio_carro'] ?? "N/A";
            $p['codigo'] = $p['codigo'] ?? ("PROD-" . $p['id_producto']);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true, "productos" => $productos, "tasa_dolar" => $tasa]);
        exit;
    }

    // 2. VERIFICAR SI LA CÉDULA EXISTE
    elseif ($action === 'check_cedula') {
        $cedula = $_GET['cedula'] ?? '';
        if (!$cedula) throw new Exception("Cédula requerida");
        
        // Buscamos en persona y también su correo si tiene usuario
        $stmt = $conexion->prepare("
            SELECT p.nombre, p.telefono, p.correo, p.direccion
            FROM persona p
            WHERE p.cedula = :ced
        ");
        $stmt->execute([':ced' => $cedula]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true, 
            "exists"    => (bool)$persona, 
            "nombre"    => $persona['nombre']    ?? null,
            "telefono"  => $persona['telefono']  ?? null,
            "correo"    => $persona['correo']    ?? null,
            "direccion" => $persona['direccion'] ?? null
        ]);
        exit;
    }

    // 3. CONFIRMAR PEDIDO (TRANSACCIÓN)
    elseif ($action === 'confirmar') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['carrito'])) throw new Exception("El carrito está vacío");
        if (empty($data['cedula'])) throw new Exception("Faltan datos del cliente");

        $conexion->beginTransaction();

        // Limpieza de datos (Seguridad básica)
        $cedula    = htmlspecialchars(trim($data['cedula']), ENT_QUOTES, 'UTF-8');
        $nombre    = htmlspecialchars(trim($data['nombre'] ?? 'Cliente'), ENT_QUOTES, 'UTF-8');
        $direccion = isset($data['direccion']) ? htmlspecialchars(trim($data['direccion']), ENT_QUOTES, 'UTF-8') : null;
        $telefono  = isset($data['telefono']) ? htmlspecialchars(trim($data['telefono']), ENT_QUOTES, 'UTF-8') : null;

        // --- Gestión de Persona/Cliente ---
        $stmtP = $conexion->prepare("SELECT id_persona FROM persona WHERE cedula = :ced");
        $stmtP->execute([':ced' => $cedula]);
        $persona = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            // Insertar nueva persona (incluye correo si viene del carrito del cliente)
            $correo_nuevo = isset($data['correo']) ? htmlspecialchars(trim($data['correo']), ENT_QUOTES, 'UTF-8') : null;
            $stmtInsertP = $conexion->prepare("INSERT INTO persona (cedula, nombre, direccion, telefono, correo) VALUES (:ced, :nom, :dir, :tel, :correo)");
            $stmtInsertP->execute([':ced' => $cedula, ':nom' => $nombre, ':dir' => $direccion, ':tel' => $telefono, ':correo' => $correo_nuevo]);
            $id_persona = $conexion->lastInsertId();

            // Insertar nuevo cliente
            $stmtInsertC = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (:id_p)");
            $stmtInsertC->execute([':id_p' => $id_persona]);
            $id_cliente = $conexion->lastInsertId();
        } else {
            $id_persona = $persona['id_persona'];
            // Verificar si la persona ya está registrada como cliente
            $stmtC = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_persona = :id_p");
            $stmtC->execute([':id_p' => $id_persona]);
            $cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                $stmtInsertC = $conexion->prepare("INSERT INTO cliente (id_persona) VALUES (:id_p)");
                $stmtInsertC->execute([':id_p' => $id_persona]);
                $id_cliente = $conexion->lastInsertId();
            } else {
                $id_cliente = $cliente['id_cliente'];
            }
        }

        $totalGeneral = 0;
$detallesAGuardar = [];

// AGRUPAR PRODUCTOS REPETIDOS
$carritoAgrupado = [];

foreach ($data['carrito'] as $item) {
    $id = $item['id_producto'];
    if (!isset($carritoAgrupado[$id])) {
        $carritoAgrupado[$id] = 0;
    }
    $carritoAgrupado[$id] += $item['cant'];
}

// VALIDAR Y CALCULAR
foreach ($carritoAgrupado as $id_producto => $cantidad) {

    $stmtProd = $conexion->prepare("SELECT precio, stock_actual FROM producto WHERE id_producto = ?");
    $stmtProd->execute([$id_producto]);
    $prodDb = $stmtProd->fetch(PDO::FETCH_ASSOC);

    if (!$prodDb) throw new Exception("Producto con ID {$id_producto} no existe");
    if ($prodDb['stock_actual'] < $cantidad) throw new Exception("Stock insuficiente");

    $subtotal = $prodDb['precio'] * $cantidad;
    $totalGeneral += $subtotal;

    $detallesAGuardar[] = [
        'id' => $id_producto,
        'cant' => $cantidad,
        'precio' => $prodDb['precio'],
        'sub' => $subtotal
    ];
}

        // --- Determinar empleado vendedor (Opcional si es cliente o autogestión) ---
        $usuarioActual = $_SESSION['usuario_id'] ?? null;
        $id_vendedor = null;

        if ($usuarioActual) {
            $stmtEmpleado = $conexion->prepare("SELECT id_empleado FROM empleado WHERE id_usuario = :id_usuario LIMIT 1");
            $stmtEmpleado->execute([':id_usuario' => $usuarioActual]);
            $empleado = $stmtEmpleado->fetch(PDO::FETCH_ASSOC);
            
            if ($empleado && !empty($empleado['id_empleado'])) {
                $id_vendedor = $empleado['id_empleado'];
            }
        }

        // --- Insertar Solicitud ---
        $stmtSol = $conexion->prepare("INSERT INTO solicitud (id_cliente, total, estado, fecha_solicitud, id_vendedor) VALUES (?, ?, 'Pendiente', NOW(), ?)");
        $stmtSol->execute([$id_cliente, $totalGeneral, $id_vendedor]);
        $id_solicitud = $conexion->lastInsertId();

        // --- Insertar Detalles y Actualizar Stock ---
        $stmtDet = $conexion->prepare("INSERT INTO detalle_solicitud (id_solicitud, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtStock = $conexion->prepare("UPDATE producto SET stock_actual = stock_actual - ? WHERE id_producto = ? AND stock_actual >= ?");

        foreach ($detallesAGuardar as $det) {
            $stmtDet->execute([$id_solicitud, $det['id'], $det['cant'], $det['precio'], $det['sub']]);
            $stmtStock->execute([$det['cant'], $det['id'], $det['cant']]);

            if ($stmtStock->rowCount() === 0) {
                throw new Exception("Error al actualizar stock para el producto ID: " . $det['id']);
            }
        }

        $conexion->commit();
        if (ob_get_length()) ob_clean();
        // Devolver el codigo_solicitud que el catálogo del cliente usa para el mensaje de WhatsApp
        $codigoFormateado = 'SOL-' . str_pad($id_solicitud, 6, '0', STR_PAD_LEFT);
        echo json_encode([
            "success" => true,
            "message" => "Pedido realizado con éxito",
            "codigo_solicitud" => $codigoFormateado,
            "id_solicitud" => $id_solicitud
        ]);
        exit;
    }

    throw new Exception("Acción no válida");

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if (ob_get_length()) ob_clean();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

?>