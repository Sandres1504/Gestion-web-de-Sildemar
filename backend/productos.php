<?php
ob_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$action = $_GET['action'] ?? '';

try {
    require_once 'db.php';

    if (!isset($conexion)) {
        throw new Exception("Error de configuración: Conexión no disponible.");
    }

    // Esta acción es la que debe llamar tu archivo Cliente/cliente.html
    if ($action === 'listar_catalogo') {
        if (ob_get_length()) ob_clean(); 

        // Obtener la tasa de cambio oficial (ID 1)
        $stmtTasa = $conexion->query("SELECT tasa_dolar FROM configuracion WHERE id = 1");
        $resTasa = $stmtTasa->fetch(PDO::FETCH_ASSOC);
        
        if (!$resTasa) throw new Exception("No se encontró configuración de tasa en la base de datos.");
        
        $tasa = ($resTasa && $resTasa['tasa_dolar'] > 0) ? floatval($resTasa['tasa_dolar']) : 1.0;

        // Seleccionar directamente las columnas de la tabla producto
        $sql = "SELECT id_producto, codigo, nombre_producto, marca_repuesto, 
                       marca_carro, ano AS anio_carro, transmision, categoria, 
                       precio AS precio_venta, stock_actual AS stock, imagen 
                FROM producto 
                WHERE stock_actual > 0";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular precio en Bs para cada producto
        foreach ($productos as &$p) {
            $p['precio_bs'] = round($p['precio_venta'] * $tasa, 2);
        }

        echo json_encode(["success" => true, "productos" => $productos, "tasa_dolar" => $tasa]);
        exit;
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(["success" => false, "message" => "Acción no válida"]);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(["success" => false, "message" => "Error de servidor: " . $e->getMessage()]);
}
?>