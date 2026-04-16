<?php
header('Content-Type: application/json');

// Conexión a la base de datos
$conn = new mysqli("localhost", "root", "", "sildemar");

if ($conn->connect_error) {
    echo json_encode(["success" => false]);
    exit;
}

$action = $_GET['action'] ?? '';

/* 1. LISTAR TODOS LOS CLIENTES */
if ($action === "listar") {
    $busqueda = isset($_GET['buscar']) ? "%" . $_GET['buscar'] . "%" : "%%";

    // Se une con 'usuario' para obtener el correo, ya que no está en 'persona'
    $sql = "SELECT c.id_cliente, p.nombre, p.telefono, u.correo, p.direccion 
            FROM cliente c 
            JOIN persona p ON c.id_persona = p.id_persona 
            LEFT JOIN usuario u ON p.id_persona = u.id_persona
            WHERE p.nombre LIKE ? OR p.cedula LIKE ? OR p.telefono LIKE ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $data = [];
    while($r = $res->fetch_assoc()) $data[] = $r;
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

    $res = $conn->query($sql);
    $data = [];
    while($r = $res->fetch_assoc()) $data[] = $r;
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

    $res = $conn->query($sql);
    $data = [];
    while($r = $res->fetch_assoc()) $data[] = $r;
    echo json_encode($data);
    exit;
}

/* 4. DETALLES (PARA EL MODAL) */
if ($action === "detalles") {
    $id = $_GET['id'] ?? 0;
    $sql = "SELECT s.fecha_solicitud, pr.nombre_producto as producto, pr.marca_carro as carro, 
                    d.cantidad, d.subtotal as total, s.estado
            FROM detalle_solicitud d
            JOIN solicitud s ON d.id_solicitud = s.id_solicitud
            JOIN producto pr ON d.id_producto = pr.id_producto
            WHERE s.id_cliente = ? ORDER BY s.fecha_solicitud DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while($r = $res->fetch_assoc()) $data[] = $r;
    echo json_encode($data);
    exit;
}

echo json_encode(["success" => false]);
?>