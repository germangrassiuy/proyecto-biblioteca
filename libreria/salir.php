<?php
// ============================================================
//  SALIR - cerrar la sesion
// ============================================================
require 'db.php';

// No alcanza con vaciar $_SESSION: hay que destruir la sesion entera,
// para que el numero que tiene el navegador deje de servir.
$_SESSION = [];
session_destroy();

header('Location: entrar.php');
exit;
