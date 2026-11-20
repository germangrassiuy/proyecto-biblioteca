<?php
// ============================================================
//  ALQUILERES - que libros estan afuera y quien los tiene
// ============================================================
//  Toda la pantalla se apoya en un solo detalle del modelo:
//
//      fecha_devolucion IS NULL  ->  el libro TODAVIA esta afuera
//      fecha_devolucion = fecha  ->  volvio ese dia
//
//  Con esa unica columna alcanza para las dos cosas que hacen falta:
//  saber que falta devolver hoy, y tener el historial completo de todo
//  lo que se alquilo alguna vez. Nunca hay que borrar una fila.
// ============================================================
require 'db.php';
soloAdmin();

// Dias que tiene el cliente para devolver antes de quedar atrasado.
const DIAS_DE_PLAZO = 15;

$aviso = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['devolver'])) {
    // Devolver toca dos tablas (alquileres y ejemplares), asi que va
    // adentro de una transaccion. Esta en db.php
    $resultado = devolverUnAlquiler($pdo, (int) $_POST['devolver']);
    if ($resultado['ok']) {
        $aviso = 'Libro devuelto. Ya vuelve a figurar disponible en el catalogo.';
    } else {
        $error = $resultado['mensaje'];
    }
}

// Lo que esta afuera ahora mismo.
//
// Los dias se cuentan con julianday(), que convierte una fecha en un
// numero de dias corrido. Restando dos, sale cuantos dias hay entre
// ellas. Es lo que en MySQL hace DATEDIFF(), que SQLite no tiene.
// La fecha de hoy va con 'localtime' por lo que esta explicado en db.php.
$afuera = $pdo->query(
    "SELECT  a.id, a.fecha_salida,
             CAST(julianday(date('now', 'localtime')) - julianday(a.fecha_salida) AS INTEGER) AS dias,
             e.codigo_interno,
             l.nombre AS libro, l.autor,
             c.nombre AS cliente_nombre, c.apellido, c.telefono
       FROM  alquileres a
       JOIN  ejemplares e ON e.id = a.ejemplar_id
       JOIN  libros     l ON l.id = e.libro_id
       JOIN  clientes   c ON c.id = a.cliente_id
      WHERE  a.fecha_devolucion IS NULL
   ORDER BY  a.fecha_salida"
)->fetchAll();

// Las ultimas devoluciones, para ver que el historial queda guardado.
$devueltos = $pdo->query(
    "SELECT  a.fecha_salida, a.fecha_devolucion,
             CAST(julianday(a.fecha_devolucion) - julianday(a.fecha_salida) AS INTEGER) AS dias,
             l.nombre AS libro,
             c.nombre AS cliente_nombre, c.apellido
       FROM  alquileres a
       JOIN  ejemplares e ON e.id = a.ejemplar_id
       JOIN  libros     l ON l.id = e.libro_id
       JOIN  clientes   c ON c.id = a.cliente_id
      WHERE  a.fecha_devolucion IS NOT NULL
   ORDER BY  a.fecha_devolucion DESC, a.id DESC
      LIMIT  10"
)->fetchAll();

$atrasados = 0;
foreach ($afuera as $a) {
    if ((int) $a['dias'] > DIAS_DE_PLAZO) { $atrasados++; }
}

cabecera('Alquileres', 'alquileres.php');
?>

<main>
    <h2>Libros afuera (<?= count($afuera) ?>)</h2>

<?php if ($aviso !== ''): ?>
    <p class="aviso-ok"><?= esc($aviso) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error"><?= esc($error) ?></p>
<?php endif; ?>

    <p class="nota">
        Estos son los ejemplares que la libreria tiene pero que hoy no estan en el
        local. Por eso su <strong>stock en tienda</strong> es menor que el total.
<?php if ($atrasados > 0): ?>
        Hay <strong><?= $atrasados ?></strong> con mas de <?= DIAS_DE_PLAZO ?> dias afuera.
<?php endif; ?>
    </p>

<?php if (!$afuera): ?>
    <p class="nota">No hay ningun libro afuera: esta todo en el local.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Etiqueta</th><th>Libro</th><th>Se lo llevo</th>
            <th>Desde</th><th class="derecha">Dias</th><th></th>
        </tr>
<?php foreach ($afuera as $a): ?>
        <tr>
            <td class="mono"><?= esc($a['codigo_interno']) ?></td>
            <td><?= esc($a['libro']) ?><br><span class="autor-chico"><?= esc($a['autor']) ?></span></td>
            <td><?= esc($a['apellido']) ?>, <?= esc($a['cliente_nombre']) ?>
                <br><span class="autor-chico mono"><?= esc($a['telefono']) ?></span></td>
            <td class="mono"><?= esc($a['fecha_salida']) ?></td>
            <td class="derecha mono">
<?php if ((int) $a['dias'] > DIAS_DE_PLAZO): ?>
                <span class="sin-stock"><?= (int) $a['dias'] ?></span>
<?php else: ?>
                <?= (int) $a['dias'] ?>
<?php endif; ?>
            </td>
            <td>
                <form method="post" action="alquileres.php">
                    <button name="devolver" value="<?= (int) $a['id'] ?>">Devolver</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>

    <h2>Ultimas devoluciones</h2>

<?php if (!$devueltos): ?>
    <p class="nota">
        Todavia no se devolvio ningun libro. Cuando se devuelva uno va a aparecer aca:
        la fila del alquiler no se borra nunca, solo se le completa la fecha de
        devolucion. Asi queda el historial.
    </p>
<?php else: ?>
    <table>
        <tr><th>Libro</th><th>Cliente</th><th>Salio</th><th>Volvio</th><th class="derecha">Dias</th></tr>
<?php foreach ($devueltos as $d): ?>
        <tr>
            <td><?= esc($d['libro']) ?></td>
            <td><?= esc($d['apellido']) ?>, <?= esc($d['cliente_nombre']) ?></td>
            <td class="mono"><?= esc($d['fecha_salida']) ?></td>
            <td class="mono"><?= esc($d['fecha_devolucion']) ?></td>
            <td class="derecha mono"><?= (int) $d['dias'] ?></td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</main>
</body>
</html>
