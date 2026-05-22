<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

/* 1. LISTAR TODOS LOS CLIENTES */
if ($action === "listar") {
    // El correo ahora está directamente en la tabla persona
    $busqueda = isset($_GET['buscar']) ? "%" . $_GET['buscar'] . "%" : "%%";
    $buscarTel = isset($_GET['telefono']) ? "%" . $_GET['telefono'] . "%" : null;

    if ($buscarTel) {
        $sql = "SELECT c.id_cliente, p.nombre, p.telefono, p.correo, p.direccion
                FROM cliente c
                JOIN persona p ON c.id_persona = p.id_persona
                WHERE p.telefono LIKE ?";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$buscarTel]);
    } else {
        $sql = "SELECT c.id_cliente, p.nombre, p.telefono, p.correo, p.direccion
                FROM cliente c
                JOIN persona p ON c.id_persona = p.id_persona
                WHERE p.nombre LIKE ? OR p.cedula LIKE ? OR p.telefono LIKE ?";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$busqueda, $busqueda, $busqueda]);
    }

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

/* 2. CLIENTES FRECUENTES */
if ($action === "frecuentes") {
    $sql = "SELECT p.nombre, COUNT(s.id_solicitud) AS pedidos, SUM(s.total) AS total_gastado
            FROM cliente c
            JOIN persona p ON c.id_persona = p.id_persona
            JOIN solicitud s ON s.id_cliente = c.id_cliente
            GROUP BY c.id_cliente
            ORDER BY total_gastado DESC LIMIT 10";

    $res = $conexion->query($sql);
    $data = $res->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

/* 3. ESTADÍSTICAS POR CARRO */
if ($action === "carros") {
    $sql = "SELECT pr.marca_carro, SUM(d.subtotal) AS ingresos
            FROM detalle_solicitud d
            JOIN producto pr ON d.id_producto = pr.id_producto
            GROUP BY pr.marca_carro
            ORDER BY ingresos DESC";

    $res = $conexion->query($sql);
    $data = $res->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

/* 4. DETALLES (PARA EL MODAL) */
if ($action === "detalles") {
    $id = $_GET['id'] ?? 0;
    $sql = "SELECT s.fecha_solicitud, pr.id_producto AS codigo, pr.nombre_producto AS producto,
                    pr.marca_repuesto, pr.marca_carro AS carro, pr.ano AS anio_carro,
                    pr.precio, d.cantidad, d.subtotal AS total, s.estado
            FROM detalle_solicitud d
            JOIN solicitud s ON d.id_solicitud = s.id_solicitud
            JOIN producto pr ON d.id_producto = pr.id_producto
            WHERE s.id_cliente = ? ORDER BY s.fecha_solicitud DESC";
            
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

echo json_encode(["success" => false]);
?>