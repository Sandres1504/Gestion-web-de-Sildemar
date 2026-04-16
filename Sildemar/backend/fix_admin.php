<?php
require_once 'db.php';

try {
    $stmt = $conexion->prepare("UPDATE usuario SET rol = 'Administrador' WHERE correo = 'admin@sildemar.com'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<h1>¡Éxito!</h1><p>El rol del usuario <b>admin@sildemar.com</b> se ha actualizado a <b>Administrador</b> en la base de datos.</p>";
        echo "<br><a href='../index.html'>Ir al Login para probar de nuevo</a>";
    } else {
        echo "<h1>Aviso</h1><p>No se realizaron cambios. Es posible que el usuario ya sea Administrador o que el correo no exista.</p>";
        echo "<br><a href='../index.html'>Ir al Login</a>";
    }
} catch (PDOException $e) {
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
?>
