<?php
require 'db.php';
soloAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if ($nombre === '' || $apellido === '' || $telefono === '') {
        $error = 'Hay que completar los tres campos.';
    } else {
        $consulta = $pdo->prepare(
            'INSERT INTO clientes (nombre, apellido, telefono) VALUES (?, ?, ?)'
        );
        $consulta->execute([$nombre, $apellido, $telefono]);
        // Redirige despues de guardar para que F5 no cargue el cliente dos veces.
        header('Location: clientes.php');
        exit;
    }
}

$clientes = $pdo->query('SELECT * FROM clientes ORDER BY apellido, nombre')->fetchAll();

cabecera('Clientes', 'clientes.php');
?>

<main>
    <h2>Agregar un cliente</h2>

<?php if ($error !== ''): ?>
    <p class="error"><?= esc($error) ?></p>
<?php endif; ?>

    <form method="post" action="clientes.php" class="alta">
        <label>Nombre
            <input type="text" name="nombre" autocomplete="off" autofocus>
        </label>
        <label>Apellido
            <input type="text" name="apellido" autocomplete="off">
        </label>
        <label>Telefono
            <input type="text" name="telefono" autocomplete="off">
        </label>
        <button type="submit">Guardar cliente</button>
    </form>

    <h2>Clientes cargados (<?= count($clientes) ?>)</h2>

<?php if (!$clientes): ?>
    <p class="nota">Todavia no hay clientes cargados.</p>
<?php else: ?>
    <table>
        <tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Telefono</th></tr>
<?php foreach ($clientes as $cliente): ?>
        <tr>
            <td class="mono"><?= esc($cliente['id']) ?></td>
            <td><?= esc($cliente['nombre']) ?></td>
            <td><?= esc($cliente['apellido']) ?></td>
            <td class="mono"><?= esc($cliente['telefono']) ?></td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</main>
</body>
</html>
