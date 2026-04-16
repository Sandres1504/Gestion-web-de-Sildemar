<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

/* =========================
    LISTAR PRODUCTOS
========================= */
if ($action === 'list') {

    try {

        $stmt = $conexion->query("
            SELECT id_producto, nombre_producto, descripcion, precio, stock_actual
            FROM producto
            ORDER BY id_producto DESC
        ");

        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos as &$p) {

            $p['marca_repuesto'] = "N/A";
            $p['marca_carro'] = "N/A";
            $p['transmision'] = "N/A";
            $p['compra'] = 0;
            $p['codigo'] = "PROD-" . $p['id_producto'];

            if (!empty($p['descripcion'])) {

                $desc = json_decode($p['descripcion'], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($desc)) {

                    $p['marca_repuesto'] = $desc['marca_repuesto'] ?? "N/A";
                    $p['marca_carro'] = $desc['marca_vehiculo'] ?? "N/A";
                    $p['transmision'] = $desc['transmision'] ?? "N/A";
                    $p['compra'] = $desc['precio_compra'] ?? 0;
                    if (!empty($desc['codigo'])) {
                        $p['codigo'] = $desc['codigo'];
                    }

                }

            }
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

    // Soporte tanto para $_POST vía FormData como JSON normal
    if (!empty($_POST)) {
        $data = $_POST;
    } else {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
    }

    if (!$data) {
        echo json_encode(["success"=>false,"message"=>"Datos inválidos"]);
        exit;
    }

    $jsonDesc = json_encode([
        "marca_repuesto" => $data['marca_repuesto'] ?? '',
        "marca_vehiculo" => $data['marca_carro'] ?? '',
        "transmision" => $data['transmision'] ?? '',
        "precio_compra" => $data['compra'] ?? 0,
        "codigo" => $data['codigo'] ?? ''
    ]);

    // Manejar subida de imagen
    $imagenPath = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../Ima/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['imagen']['name']);
        $targetFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetFilePath)) {
            $imagenPath = 'Ima/' . $fileName;
        }
    }

    try {

        $sql = "INSERT INTO producto
                (nombre_producto, descripcion, precio, stock_actual, imagen)
                VALUES
                (:nombre, :descripcion, :precio, :stock, :imagen)";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':descripcion' => $jsonDesc,
            ':precio' => $data['precio'],
            ':stock' => $data['stock'],
            ':imagen' => $imagenPath
        ]);

        echo json_encode(["success"=>true]);

    } catch (PDOException $e) {

        echo json_encode([
            "success"=>false,
            "message"=>$e->getMessage()
        ]);

    }
}



/* =========================
    ACTUALIZAR PRODUCTO
 ========================= */
elseif ($action === 'update') {

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id_producto'])) {

        echo json_encode([
            "success"=>false,
            "message"=>"Datos inválidos"
        ]);

        exit;
    }

    $jsonDesc = json_encode([
        "marca_repuesto" => $data['marca_repuesto'] ?? '',
        "marca_vehiculo" => $data['marca_carro'] ?? '',
        "transmision" => $data['transmision'] ?? '',
        "precio_compra" => $data['compra'] ?? 0,
        "codigo" => $data['codigo'] ?? ''
    ]);

    try {

        $sql = "UPDATE producto
                SET nombre_producto = :nombre,
                    descripcion = :descripcion,
                    precio = :precio
                WHERE id_producto = :id";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':id' => $data['id_producto'],
            ':nombre' => $data['nombre'],
            ':descripcion' => $jsonDesc,
            ':precio' => $data['precio']
        ]);

        echo json_encode(["success"=>true]);

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

        echo json_encode([
            "success"=>false,
            "message"=>"ID no proporcionado"
        ]);

        exit;

    }

    try {

        $stmt = $conexion->prepare("DELETE FROM producto WHERE id_producto = ?");
        $stmt->execute([$_GET['id']]);

        echo json_encode(["success"=>true]);

    } catch (PDOException $e) {

        echo json_encode([
            "success"=>false,
            "message"=>"No se puede eliminar porque está asociado a solicitudes"
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

    } catch (PDOException $e) {
        echo json_encode(["success"=>false, "message"=>$e->getMessage()]);
    }
}
?>