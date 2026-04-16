<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "sildemar";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error de BD"]);
    exit;
}
?>