<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'logger.php';

if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function enviarCorreoBienvenida($correo, $usuario, $clave) {
    $from = getenv('MAIL_FROM') ?: 'no-reply@sildemar.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'Sildemar';
    $subject = 'Bienvenido a Sildemar';
    $message = "Hola,\n\n" .
        "Se ha creado tu cuenta en el sistema Sildemar.\n\n" .
        "Correo: $usuario\n" .
        "Contraseña temporal: $clave\n\n" .
        "Por seguridad, te recomendamos cambiar tu contraseña la primera vez que ingreses.\n\n" .
        "Si no reconoces este correo, por favor contacta al administrador.\n";

    $mailSent = false;

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
            $mail->setFrom($from, $fromName);
            $mail->addAddress($correo);
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mailSent = (bool) $mail->send();
        } catch (Exception $e) {
            error_log('[GESTION_USUARIOS] PHPMailer error: ' . $e->getMessage());
            $mailSent = false;
        }
    } else {
        $headers = "From: $from\r\n";
        $mailSent = (bool) @mail($correo, $subject, $message, $headers);
    }

    return $mailSent;
}

$action = $_GET['action'] ?? '';

// Verificar si hay una sesión activa para acciones sensibles
$role_sesion = $_SESSION['usuario_rol'] ?? '';
$id_sesion = $_SESSION['usuario_id'] ?? null;

try {
    if ($action === 'create') {
        $datos = json_decode(file_get_contents('php://input'), true);

        // Validar permisos desde la sesión
        if ($role_sesion !== 'Gerente del Sistema') {
            throw new Exception("No tienes permisos para crear usuarios. Solo el Gerente del Sistema puede hacerlo.");
        }

        $nombre = trim($datos['nombre'] ?? '');
        $email = trim($datos['email'] ?? '');
        $telefono = trim($datos['telefono'] ?? '');
        $direccion = trim($datos['direccion'] ?? '');
        $clave = $datos['clave'] ?? '';
        $id_rol = intval($datos['id_rol'] ?? 3); // Valor dinámico del rol
        $cedula = trim($datos['cedula'] ?? '');

        // Validaciones básicas
        if (empty($nombre) || empty($email) || empty($clave) || empty($cedula)) {
            throw new Exception("Todos los campos son obligatorios");
        }

        // Validar longitud de cédula (entre 7 y 8 números)
        $soloNumeros = preg_replace('/[^0-9]/', '', $cedula);
        if (strlen($soloNumeros) < 7 || strlen($soloNumeros) > 8) {
            throw new Exception("La cédula debe tener entre 7 y 8 números");
        }

        // Validar formato de correo
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido");
        }

        $conexion->beginTransaction();

        // Verificar si la cédula ya existe
        $stmtP = $conexion->prepare("SELECT id_persona FROM persona WHERE cedula = :ced");
        $stmtP->execute([':ced' => $cedula]);
        $persona = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            $stmtInsertP = $conexion->prepare("INSERT INTO persona (cedula, nombre, telefono, direccion) VALUES (:cedula, :nombre, :telefono, :direccion)");
            $stmtInsertP->execute([
                ':cedula' => $cedula, 
                ':nombre' => $nombre, 
                ':telefono' => $telefono, 
                ':direccion' => $direccion
            ]);
            $id_persona = $conexion->lastInsertId();
        } else {
            $id_persona = $persona['id_persona'];
            // Actualizar datos por si cambiaron
            $stmtUpdateP = $conexion->prepare("UPDATE persona SET nombre = :nombre, telefono = :telefono, direccion = :direccion WHERE id_persona = :id");
            $stmtUpdateP->execute([
                ':nombre' => $nombre,
                ':telefono' => $telefono,
                ':direccion' => $direccion,
                ':id' => $id_persona
            ]);
        }

        // Verificar si el correo ya está registrado
        $stmtCheck = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo");
        $stmtCheck->execute([':correo' => $email]);
        if ($stmtCheck->fetch()) {
            throw new Exception("El correo electrónico ya está registrado en el sistema");
        }

        $passHash = password_hash($clave, PASSWORD_DEFAULT);
        
        // Usar el nombre correcto de la columna (contraseña sin caracteres raros)
        $sqlU = "INSERT INTO usuario (correo, `password`, id_rol, id_persona) 
                 VALUES (:correo, :password, :id_rol, :id_persona)";
        $stmtU = $conexion->prepare($sqlU);
        $stmtU->execute([
            ':correo' => $email,
            ':password' => $passHash,
            ':id_rol' => $id_rol,
            ':id_persona' => $id_persona
        ]);

        $id_usuario = $conexion->lastInsertId();

        $cargo = 'Operador';
        if ($id_rol == 1) $cargo = 'Administrador';
        if ($id_rol == 2) $cargo = 'Gerente';

        $sqlE = "INSERT INTO empleado (cargo, id_usuario) VALUES (:cargo, :id_usuario)";
        $stmtE = $conexion->prepare($sqlE);
        $stmtE->execute([
            ':cargo' => $cargo,
            ':id_usuario' => $id_usuario
        ]);

        $conexion->commit();

        if ($id_sesion) {
            registrarAuditoria($conexion, $id_sesion, "Gerente del Sistema creó usuario " . $nombre);
        }

        $emailEnviado = enviarCorreoBienvenida($email, $email, $clave);

        echo json_encode([
            'success' => true,
            'email_sent' => $emailEnviado,
            'message' => $emailEnviado ? 'Usuario creado y correo enviado.' : 'Usuario creado. No se pudo enviar el correo de bienvenida.'
        ]);

    }
    else if ($action === 'list') {
    try {
        // Consulta corregida - usar INNER JOIN y los nombres correctos de columnas
        $stmt = $conexion->prepare("
            SELECT u.id_usuario, u.correo, r.nombre_rol as rol, p.nombre 
            FROM usuario u 
            INNER JOIN persona p ON u.id_persona = p.id_persona 
            INNER JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.id_usuario
        ");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
}
    else if ($action === 'update') {
        $datos = json_decode(file_get_contents('php://input'), true);
        $id_usuario = $datos['id_usuario'] ?? null;
        $nuevo_id_rol = 3;

        if (!$id_usuario) {
            throw new Exception("ID de usuario no proporcionado");
        }

        $sql = "UPDATE usuario SET id_rol = :id_rol WHERE id_usuario = :id_usuario";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id_rol' => $nuevo_id_rol, ':id_usuario' => $id_usuario]);

        echo json_encode(['success' => true]);
    }
    else if ($action === 'delete') {
        $id_usuario = $_GET['id'] ?? null;
        
        if (!$id_usuario) {
            throw new Exception("ID de usuario no proporcionado");
        }

        // Seguridad: Evitar que un usuario se borre a sí mismo
        if ($id_sesion && $id_sesion == $id_usuario) {
            throw new Exception("No puedes eliminar tu propia cuenta activa.");
        }

        $stmtSelect = $conexion->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
        $stmtSelect->execute([':id' => $id_usuario]);
        $user_data = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            throw new Exception("El usuario no existe");
        }

        $conexion->beginTransaction();

        // 1. Borrar de la tabla 'empleado'
        $stmtEmp = $conexion->prepare("DELETE FROM empleado WHERE id_usuario = :id");
        $stmtEmp->execute([':id' => $id_usuario]);

        // 2. Borrar de la tabla 'usuario'
        $stmt = $conexion->prepare("DELETE FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);

        $conexion->commit();

        if ($id_sesion) {
            registrarAuditoria($conexion, $id_sesion, "Gerente del Sistema eliminó usuario ID " . $id_usuario);
        }

        echo json_encode(['success' => true]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
}
catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>