<?php
// ============================================================
//  ENTRAR - la pantalla de acceso
// ============================================================
require 'db.php';

// Si ya entro, no tiene sentido mostrarle el formulario otra vez.
if (usuarioActual() !== null) {
    header('Location: ' . paginaDeInicio());
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreUsuario = trim($_POST['usuario'] ?? '');
    $claveEscrita  = $_POST['clave'] ?? '';

    $consulta = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ?');
    $consulta->execute([$nombreUsuario]);
    $fila = $consulta->fetch();

    // password_verify() vuelve a calcular el hash de lo que escribio la
    // persona y lo compara con el guardado. En ningun momento tenemos la
    // contrasenia real: ni la guardamos ni la podriamos recuperar.
    if ($fila && password_verify($claveEscrita, $fila['clave_hash'])) {

        // Cambiar el numero de sesion justo al entrar. Si alguien te hizo
        // abrir la pagina con un numero de sesion que el ya conocia, al
        // cambiarlo aca ese numero deja de servirle. Son dos palabras y
        // tapan un agujero real.
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'     => $fila['id'],
            'nombre' => $fila['nombre'],
            'rol'    => $fila['rol'],
        ];
        header('Location: ' . paginaDeInicio());
        exit;
    }

    // El mensaje es el mismo si el usuario no existe y si la clave esta
    // mal. A proposito: decir "ese usuario no existe" le confirma a un
    // desconocido cuales son los usuarios validos, y eso es medio camino
    // andado para entrar.
    $error = 'Usuario o contrasenia incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Libreria &middot; Entrar</title>
<link rel="stylesheet" href="estilos.css">

<body class="pantalla-entrar">
<main class="caja-entrar">
    <h1>Libreria</h1>
    <p class="nota">Entra con tu usuario para continuar.</p>

<?php if ($error !== ''): ?>
    <p class="error"><?= esc($error) ?></p>
<?php endif; ?>

    <form method="post" action="entrar.php" class="alta">
        <label>Usuario
            <input type="text" name="usuario" autocomplete="off" autofocus>
        </label>
        <label>Contrasenia
            <input type="password" name="clave">
        </label>
        <button type="submit">Entrar</button>
    </form>

    <div class="cuentas-prueba">
        <p><strong>Cuentas de prueba</strong></p>
        <p><span class="rol rol-admin">admin</span> usuario <span class="mono">admin</span>, clave <span class="mono">admin123</span><br>
           Ve el mostrador, la carga de libros y el stock.</p>
        <p><span class="rol rol-cliente">cliente</span> usuario <span class="mono">cliente</span>, clave <span class="mono">cliente123</span><br>
           Ve solamente el catalogo.</p>
    </div>
</main>
</body>
</html>
