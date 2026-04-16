<?php
// backed/logout.php
session_start();
session_unset();
session_destroy();
header("Location: ../index.html"); // Te redirige al login
exit();
?>