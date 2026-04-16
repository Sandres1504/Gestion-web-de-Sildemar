<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // 1. Conteo de productos
    $resProd = $conexion->query("SELECT COUNT(*) as total FROM producto");
    $totalProductos = $resProd->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Conteo de solicitudes pendientes
    $resSol = $conexion->query("SELECT COUNT(*) as total FROM solicitud WHERE estado = 'Pendiente'");
    $pedidosPendientes = $resSol->fetch(PDO::FETCH_ASSOC)['total'];

    // 3. Suma total de ventas (ingresos)
    $resVentas = $conexion->query("SELECT SUM(total) as total FROM solicitud WHERE estado = 'Entregada'");
    $totalVentas = $resVentas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 4. Datos para grafico: Top 5 categorias con más stock.
    $resGrafico = $conexion->query("SELECT c.nombre_categoria as marca, SUM(p.stock_actual) as cantidad FROM producto p LEFT JOIN categoria c ON p.id_categoria = c.id_categoria GROUP BY p.id_categoria ORDER BY cantidad DESC LIMIT 5");
    $graficoMarcas = $resGrafico->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ultimos movimientos 
    $resUltimos = $conexion->query("SELECT p.nombre as cliente, s.total, s.fecha_solicitud as fecha FROM solicitud s JOIN cliente c ON s.id_cliente = c.id_cliente JOIN persona p ON c.id_persona = p.id_persona ORDER BY s.id_solicitud DESC LIMIT 5");
    $ultimosMovimientos = $resUltimos->fetchAll(PDO::FETCH_ASSOC);

    // 6. Histórico de ventas (Entregadas) por mes para gráfico de barras
    $resBarras = $conexion->query("
        SELECT MONTH(fecha_solicitud) as mes, SUM(total) as ventas 
        FROM solicitud 
        WHERE estado = 'Entregada' AND YEAR(fecha_solicitud) = YEAR(CURDATE())
        GROUP BY MONTH(fecha_solicitud) 
        ORDER BY mes ASC
    ");
    $graficoBarras = $resBarras->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "metrics" => [
            "productos" => $totalProductos,
            "pendientes" => $pedidosPendientes,
            "ventas" => number_format($totalVentas, 2)
        ],
        "grafico" => $graficoMarcas,
        "graficoBarras" => $graficoBarras,
        "ultimos" => $ultimosMovimientos
    ]);

}
catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>