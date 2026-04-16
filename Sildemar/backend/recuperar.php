<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'generate_code') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $correo = trim($data['correo'] ?? '');

        if (!$correo) throw new Exception("Correo requerido.");

        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Devuelve éxito igual por seguridad
            echo json_encode(["success" => true, "mensaje" => "Código enviado"]);
            exit;
        }

        $code = sprintf("%06d", mt_rand(0, 999999));
        $expiration = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $upd = $conexion->prepare("UPDATE usuario SET codigo_recuperacion = :cod, expiracion_codigo = :exp WHERE id_usuario = :id");
        $upd->execute([':cod' => $code, ':exp' => $expiration, ':id' => $user['id_usuario']]);

        // Intentar enviar email con mail() de PHP
        $subject = "Codigo de Recuperacion Sildemar";
        $message = "Tu codigo de recuperacion de contraseña temporal es: $code\n\nEste codigo expirara en 15 minutos.";
        $headers = "From: no-reply@sildemar.com\r\n";
        
        // Mantiene @ para ignorar los warnings de sendmail de xampp si no está configurado
        @mail($correo, $subject, $message, $headers);

        // test_code removido para evitar vulnerabilidades. El correo se guardará en xampp/mailoutput.
        echo json_encode(["success" => true, "mensaje" => "Código enviado"]); 
    }
    elseif ($action === 'verify_code') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $correo = trim($data['correo'] ?? '');
        $codigo = trim($data['codigo'] ?? '');

        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo AND codigo_recuperacion = :cod AND expiracion_codigo > NOW()");
        $stmt->execute([':correo' => $correo, ':cod' => $codigo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "El código es incorrecto o ha caducado."]);
        }
    }
    elseif ($action === 'reset_password') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $correo = trim($data['correo'] ?? '');
        $codigo = trim($data['codigo'] ?? '');
        $nueva_clave = $data['clave'] ?? '';

        if (strlen($nueva_clave) < 6) throw new Exception("La nueva clave debe tener al menos 6 caracteres.");

        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo AND codigo_recuperacion = :cod AND expiracion_codigo > NOW()");
        $stmt->execute([':correo' => $correo, ':cod' => $codigo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("El código no es válido. Empieza de nuevo.");
        }

        // Se encripta la nueva clave
        $hash = password_hash($nueva_clave, PASSWORD_BCRYPT);
        $upd = $conexion->prepare("UPDATE usuario SET contraseña = :hash, codigo_recuperacion = NULL, expiracion_codigo = NULL WHERE id_usuario = :id");
        $upd->execute([':hash' => $hash, ':id' => $user['id_usuario']]);

        echo json_encode(["success" => true]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
