<?php
header('Content-Type: application/json');
require_once 'db.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$action = $_GET['action'] ?? '';

try {
    // Crear tabla de intentos si no existe
    $conexion->exec("
        CREATE TABLE IF NOT EXISTS recuperacion_intentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            correo VARCHAR(255) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            action VARCHAR(20) DEFAULT 'generate',
            success TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Asegurar columnas de recuperación en usuario para compatibilidad con DB anteriores
    $stmtColumn = $conexion->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' AND COLUMN_NAME = :column");
    $columnsToAdd = [
        'codigo_recuperacion' => 'VARCHAR(6) DEFAULT NULL',
        'expiracion_codigo' => 'DATETIME DEFAULT NULL'
    ];
    foreach ($columnsToAdd as $column => $definition) {
        $stmtColumn->execute([':column' => $column]);
        if ((int)$stmtColumn->fetchColumn() === 0) {
            $conexion->exec("ALTER TABLE usuario ADD COLUMN $column $definition");
        }
    }

    if ($action === 'generate_code') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $correo = filter_var(trim($data['correo'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!$correo) throw new Exception("Correo requerido.");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limits
        $stmtLimitIp = $conexion->prepare("SELECT COUNT(*) as cnt FROM recuperacion_intentos WHERE ip = :ip AND action = 'generate' AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $stmtLimitIp->execute([':ip' => $ip]);
        $cntIp = (int)$stmtLimitIp->fetchColumn();

        $stmtLimitEmail = $conexion->prepare("SELECT COUNT(*) as cnt FROM recuperacion_intentos WHERE correo = :correo AND action = 'generate' AND created_at > (NOW() - INTERVAL 30 MINUTE)");
        $stmtLimitEmail->execute([':correo' => $correo]);
        $cntEmail = (int)$stmtLimitEmail->fetchColumn();

        $MAX_IP = 20;
        $MAX_EMAIL = 5;

        if ($cntIp >= $MAX_IP || $cntEmail >= $MAX_EMAIL) {
            error_log("[RECUPERAR] Rate limit alcanzado para $correo (IP: $ip) - ip:$cntIp email:$cntEmail");
            $insBlocked = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'generate', 0)");
            $insBlocked->execute([':correo' => $correo, ':ip' => $ip]);
            echo json_encode(["success" => false, "message" => "Demasiados intentos. Intenta más tarde."]);
            exit;
        }

        // Verificar existencia del usuario
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        $user = $stmt->fetch();

        if (!$user) {
            error_log("[RECUPERAR] Intento de recuperación para correo no registrado: $correo | IP: " . $ip);
            $ins = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'generate', 0)");
            $ins->execute([':correo' => $correo, ':ip' => $ip]);
            echo json_encode(["success" => false, "message" => "Correo no registrado."]);
            exit;
        }

        // Generar y guardar código
        $code = sprintf("%06d", mt_rand(0, 999999));
        $expiration = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $upd = $conexion->prepare("UPDATE usuario SET codigo_recuperacion = :cod, expiracion_codigo = :exp WHERE id_usuario = :id");
        $upd->execute([':cod' => $code, ':exp' => $expiration, ':id' => $user['id_usuario']]);

        $subject = "Codigo de Recuperacion Sildemar";
        $message = "Tu codigo de recuperacion de contraseña temporal es: $code\n\nEste codigo expirara en 15 minutos.";
        $headers = "From: " . (getenv('MAIL_FROM') ?: 'no-reply@sildemar.com') . "\r\n";

        // Enviar por PHPMailer si disponible
        $mailSent = false;
        $mailDebug = getenv('MAIL_DEBUG') === '1';

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $smtpHost = getenv('SMTP_HOST');
                if ($smtpHost) {
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $smtpUser = getenv('SMTP_USER');
                    $smtpPass = getenv('SMTP_PASS');
                    $mail->SMTPAuth = $smtpUser !== '' && $smtpPass !== '';
                    if ($mail->SMTPAuth) {
                        $mail->Username = $smtpUser;
                        $mail->Password = $smtpPass;
                    }
                    $mail->SMTPSecure = getenv('SMTP_SECURE') ?: '';
                    $mail->Port = intval(getenv('SMTP_PORT') ?: 587);
                }
                $mail->setFrom(getenv('MAIL_FROM') ?: 'no-reply@sildemar.com', getenv('MAIL_FROM_NAME') ?: 'Sildemar');
                $mail->addAddress($correo);
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $message;
                $mailSent = $mail->send();
            } catch (Exception $e) {
                error_log("[RECUPERAR] PHPMailer error: " . $e->getMessage());
                $mailSent = false;
            }
        } else {
            $mailResult = @mail($correo, $subject, $message, $headers);
            $mailSent = (bool)$mailResult;
        }

        // Registrar intento
        $ins = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'generate', :success)");
        $ins->execute([':correo' => $correo, ':ip' => $ip, ':success' => $mailSent ? 1 : 0]);

        if ($mailDebug) {
            echo json_encode(["success" => true, "message" => "Código enviado", "debug_code" => $code]);
            exit;
        }

        if ($mailSent) {
            echo json_encode(["success" => true, "message" => "Código enviado"]);
        } else {
            error_log("[RECUPERAR] No se pudo enviar el código a $correo");
            echo json_encode(["success" => false, "message" => "No se pudo enviar el código. Contacte al administrador."]);
        }
    }
    elseif ($action === 'verify_code') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $correo = trim($data['correo'] ?? '');
        $codigo = trim($data['codigo'] ?? '');

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo AND codigo_recuperacion = :cod AND expiracion_codigo > NOW()");
        $stmt->execute([':correo' => $correo, ':cod' => $codigo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $ins = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'verify', 1)");
            $ins->execute([':correo' => $correo, ':ip' => $ip]);
            echo json_encode(["success" => true]);
        } else {
            $ins = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'verify', 0)");
            $ins->execute([':correo' => $correo, ':ip' => $ip]);
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

        $hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
        $upd = $conexion->prepare("UPDATE usuario SET password = :hash, codigo_recuperacion = NULL, expiracion_codigo = NULL WHERE id_usuario = :id");
        $upd->execute([':hash' => $hash, ':id' => $user['id_usuario']]);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ins = $conexion->prepare("INSERT INTO recuperacion_intentos (correo, ip, action, success) VALUES (:correo, :ip, 'reset', 1)");
        $ins->execute([':correo' => $correo, ':ip' => $ip]);

        echo json_encode(["success" => true]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
