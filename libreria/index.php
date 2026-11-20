<?php
// La puerta de entrada del sistema.
// Segun quien sea el que llega, lo manda a donde le corresponde:
// el encargado al mostrador, el cliente al catalogo, y el que no entro
// todavia a la pantalla de acceso.
require 'db.php';

header('Location: ' . (usuarioActual() === null ? 'entrar.php' : paginaDeInicio()));
exit;
