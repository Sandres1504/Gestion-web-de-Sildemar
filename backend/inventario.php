<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'logger.php';

$action = $_GET['action'] ?? '';

/* =========================
    LISTAR PRODUCTOS
========================= */
if ($action === 'list') {

    try {

        $stmt = $conexion->query("
            SELECT id_producto, codigo, nombre_producto, descripcion, precio, compra, stock_actual, marca_repuesto, marca_carro, modelo_vehiculo, transmision, categoria, imagen, ano
            FROM producto
            ORDER BY id_producto DESC
        ");

        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos as &$p) {
            $p['marca_repuesto'] = $p['marca_repuesto'] ?? "N/A";
            $p['marca_carro'] = $p['marca_carro'] ?? "N/A";
            $p['modelo_vehiculo'] = $p['modelo_vehiculo'] ?? "N/A";
            $p['transmision'] = $p['transmision'] ?? "No aplica";
            $p['compra'] = floatval($p['compra'] ?? 0);
            $p['precio'] = floatval($p['precio']);
            $p['stock_actual'] = intval($p['stock_actual']);
                $p['ano'] = $p['ano'] ?? null;
        }

        echo json_encode($productos);

    } catch (PDOException $e) {
        echo json_encode(["error"=>$e->getMessage()]);
    }
}


/* =========================
    CREAR PRODUCTO
========================= */
elseif ($action === 'create') {

    // Obtener datos del formulario
    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $marca_repuesto = trim($_POST['marca_repuesto'] ?? '');
    $marca_carro = trim($_POST['marca_carro'] ?? '');
    $modelo_vehiculo = trim($_POST['modelo_vehiculo'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'General');
    $transmision = trim($_POST['transmision'] ?? 'No aplica');
    $stock = intval($_POST['stock'] ?? 0);
    $compra = floatval($_POST['compra'] ?? 0);
    $precio = floatval($_POST['precio'] ?? 0);

    // Validar que el código sea obligatorio
    if (empty($codigo)) {
        echo json_encode(["success"=>false, "message"=>"El código del producto es obligatorio"]);
        exit;
    }

    // Validar campos obligatorios
    if (empty($nombre)) {
        echo json_encode(["success"=>false, "message"=>"El nombre del producto es obligatorio"]);
        exit;
    }

    // Verificar si el código ya existe en la base de datos
    $checkStmt = $conexion->prepare("SELECT COUNT(*) FROM producto WHERE codigo = :codigo");
    $checkStmt->execute([':codigo' => $codigo]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(["success"=>false, "message"=>"El código '$codigo' ya existe. Por favor usa otro código."]);
        exit;
    }

    // Manejar subida de imagen
    $imagenPath = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../ima/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $targetFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetFilePath)) {
            $imagenPath = $fileName;
        }
    }

    try {
        $sql = "INSERT INTO producto (codigo, nombre_producto, precio, compra, stock_actual, marca_repuesto, marca_carro, modelo_vehiculo, transmision, categoria, imagen, ano) 
                VALUES (:codigo, :nombre, :precio, :compra, :stock, :marca_repuesto, :marca_carro, :modelo_vehiculo, :transmision, :categoria, :imagen, :ano)";

        $stmt = $conexion->prepare($sql);
            $anoParam = trim($_POST['ano'] ?? '');
            if ($anoParam === '') $anoParam = null;

        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':precio' => $precio,
            ':compra' => $compra,
            ':stock' => $stock,
            ':marca_repuesto' => $marca_repuesto,
            ':marca_carro' => $marca_carro,
            ':modelo_vehiculo' => $modelo_vehiculo,
            ':transmision' => $transmision,
            ':categoria' => $categoria,
            ':imagen' => $imagenPath
                , ':ano' => $anoParam
            ]);

        echo json_encode(["success"=>true, "message"=>"Producto creado correctamente"]);

        $activo_id = $_POST['activo_id'] ?? null;
        if ($activo_id) {
            registrarAuditoria($conexion, $activo_id, "Gerente Operacional creó el producto: " . $nombre . " (Cod: " . $codigo . ")");
        }

    } catch (PDOException $e) {
        // Capturar error de duplicado por si acaso
        if ($e->errorInfo[1] == 1062) {
            echo json_encode(["success"=>false, "message"=>"El código '$codigo' ya existe. Por favor usa otro código."]);
        } else {
            echo json_encode(["success"=>false, "message"=>$e->getMessage()]);
        }
    }
}


/* =========================
    ACTUALIZAR PRODUCTO
 ========================= */
elseif ($action === 'update') {

    // Cambiamos a $_POST para soportar FormData (imágenes)
    $id_producto = $_POST['id_producto'] ?? null;
    if (!$id_producto) {
        echo json_encode(["success"=>false,"message"=>"ID de producto no proporcionado"]);
        exit;
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $marca_repuesto = trim($_POST['marca_repuesto'] ?? '');
    $marca_carro = trim($_POST['marca_carro'] ?? '');
    $modelo_vehiculo = trim($_POST['modelo_vehiculo'] ?? '');
    $transmision = trim($_POST['transmision'] ?? 'No aplica');
    $categoria = trim($_POST['categoria'] ?? 'General');
    $compra = floatval($_POST['compra'] ?? 0);
    $precio = floatval($_POST['precio'] ?? 0);

    // Manejar subida de nueva imagen si existe
    $imagenPath = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../ima/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $targetFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetFilePath)) {
            $imagenPath = $fileName;
        }
    }

    try {
        $sql = "UPDATE producto SET 
                    nombre_producto = :nombre,
                    marca_repuesto = :marca_repuesto,
                    marca_carro = :marca_carro,
                    modelo_vehiculo = :modelo_vehiculo,
                    transmision = :transmision,
                    categoria = :categoria,
                    compra = :compra,
                    precio = :precio,
                    ano = :ano";
        
            $anoParam = trim($_POST['ano'] ?? '');
            if ($anoParam === '') $anoParam = null;

        $params = [
            ':id' => $id_producto,
            ':nombre' => $nombre,
            ':marca_repuesto' => $marca_repuesto,
            ':marca_carro' => $marca_carro,
            ':modelo_vehiculo' => $modelo_vehiculo,
            ':transmision' => $transmision,
            ':categoria' => $categoria,
            ':compra' => $compra,
            ':precio' => $precio
        ];

        if ($imagenPath) {
            $sql .= ", imagen = :imagen";
            $params[':imagen'] = $imagenPath;
        }

            $params[':ano'] = $anoParam;

        $sql .= " WHERE id_producto = :id";

        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);

        echo json_encode(["success"=>true]);

        $activo_id = $_POST['activo_id'] ?? null;
        if ($activo_id) {
            registrarAuditoria($conexion, $activo_id, "Gerente Operacional actualizó el producto ID: " . $id_producto);
        }

    } catch (PDOException $e) {
        echo json_encode([
            "success"=>false,
            "message"=>$e->getMessage()
        ]);
    }
}


/* =========================
    ELIMINAR PRODUCTO
========================= */
elseif ($action === 'delete') {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        echo json_encode(["success"=>false,"message"=>"ID no proporcionado"]);
        exit;
    }

    try {
        // Primero verificar si tiene dependencias
        $checkStmt = $conexion->prepare("SELECT COUNT(*) FROM detalle_solicitud WHERE id_producto = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(["success"=>false,"message"=>"No se puede eliminar porque el producto está asociado a solicitudes"]);
            exit;
        }

        $stmt = $conexion->prepare("DELETE FROM producto WHERE id_producto = ?");
        $stmt->execute([$id]);

        echo json_encode(["success"=>true]);

        $activo_id = $_GET['activo_id'] ?? null;
        if ($activo_id) {
            registrarAuditoria($conexion, $activo_id, "Gerente Operacional eliminó el producto ID: " . $id);
        }

    } catch (PDOException $e) {
        echo json_encode([
            "success"=>false,
            "message"=>$e->getMessage()
        ]);
    }
}

/* =========================
    AGREGAR STOCK
========================= */
elseif ($action === 'add_stock') {

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id_producto']) || !isset($data['cantidad'])) {
        echo json_encode(["success"=>false, "message"=>"Datos inválidos"]);
        exit;
    }

    try {
        $cantidad = (int) $data['cantidad'];
        if ($cantidad <= 0) {
            echo json_encode(["success"=>false, "message"=>"La cantidad debe ser mayor a 0"]);
            exit;
        }

        $sql = "UPDATE producto
                SET stock_actual = stock_actual + :cantidad
                WHERE id_producto = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':id' => $data['id_producto'],
            ':cantidad' => $cantidad
        ]);

        echo json_encode(["success"=>true]);

        $activo_id = $data['activo_id'] ?? null;
        if ($activo_id) {
            registrarAuditoria($conexion, $activo_id, "Gerente Operacional agregó +" . $cantidad . " stock al producto ID: " . $data['id_producto']);
        }

    } catch (PDOException $e) {
        echo json_encode(["success"=>false, "message"=>$e->getMessage()]);
    }
}

// Si ninguna acción coincide
else {
    echo json_encode(["success"=>false, "message"=>"Acción no válida"]);
}
?>