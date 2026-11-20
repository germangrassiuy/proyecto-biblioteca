<?php
// ============================================================
//  PANTALLA DE ESCANEO
// ============================================================
//  Esta pagina vive DENTRO de un iframe de mostrador.php.
//  Es la unica que se recarga con cada escaneo. La de afuera no se
//  recarga nunca, y por eso el puerto del Arduino queda abierto.
//  El porque esta en explicaciones/02-recarga-y-puerto-serie.md
// ============================================================
require 'db.php';
soloAdmin();

$vista        = 'inicio';   // inicio | resultado | total | cierre
$lineaArduino = null;       // que mensaje mandarle al Arduino, si hay que mandar alguno
$ejemplar     = null;
$totalCerrado = 0;
$ventaCerrada = null;
$errorCobro   = '';

// ------------------------------------------------------------
//  Acciones que CAMBIAN el estado: llegan por POST, no por GET.
//  Un GET tiene que poder repetirse sin consecuencias (es "mirar").
//  Cambiar de modo o cobrar cambian cosas, y eso va por POST. Es una
//  regla general de la web, no un capricho.
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['modo'])) {
        $_SESSION['modo'] = ($_POST['modo'] === 'venta') ? 'venta' : 'scanner';
        cerrarLaVenta();                  // cambiar de modo abandona la venta en curso
        header('Location: escaneo.php');  // redirige para que F5 no repita la accion
        exit;
    }

    if (isset($_POST['cerrar'])) {
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);

        // Aca pasa todo: se guarda la venta, se descuenta el stock y se
        // anotan los alquileres. O no pasa nada de nada, si algo falla.
        $resultado = cobrarLaVenta($pdo, $clienteId ?: null);

        if ($resultado['ok']) {
            $totalCerrado = $resultado['total'];
            $ventaCerrada = $resultado['venta_id'];
            $vista        = 'cierre';
            // El Arduino muestra el total final del pedido cobrado.
            $lineaArduino = sprintf('Cerrado|%.2f|1', $totalCerrado);
        } else {
            // No se cobro nada: la venta sigue en curso, intacta.
            $errorCobro = $resultado['mensaje'];
            $vista      = 'total';
        }
    }
}

$modo = modoActual();

// ------------------------------------------------------------
//  Un escaneo: llega el codigo por GET y se busca en la base.
// ------------------------------------------------------------
$codigo = trim($_GET['codigo'] ?? '');

if ($vista === 'inicio' && $codigo !== '') {

    // Consulta preparada: el codigo NO se pega dentro del texto del SQL.
    // Va como parametro (?), asi el motor lo trata siempre como un dato
    // y nunca como instrucciones. Es lo que evita que alguien escriba
    // SQL adentro del campo del formulario y se lleve puesta la base.
    $consulta = $pdo->prepare(
        'SELECT  e.id, e.codigo_interno, e.tipo, e.estado, e.precio,
                 e.stock_total, e.stock_disponible,
                 l.nombre, l.autor, l.isbn
           FROM  ejemplares e
           JOIN  libros l ON l.id = e.libro_id
          WHERE  e.codigo_interno = ?'
    );
    $consulta->execute([$codigo]);
    $ejemplar = $consulta->fetch();

    $vista = 'resultado';

    if ($ejemplar) {
        // En modo venta cada escaneo se suma. En modo scanner, no.
        if ($modo === 'venta') {
            agregarALaVenta($ejemplar);
        }
        // El mensaje al Arduino son tres datos: lo que va arriba en la
        // pantalla, lo que va abajo, y si hay que prender el verde o el
        // rojo. Nada mas, porque nada mas necesita para mostrarlo.
        $lineaArduino = sprintf(
            '%s|%.2f|1',
            paraLcd($ejemplar['nombre']),
            $ejemplar['precio']
        );
    } else {
        // No encontrado. El total NO se toca: un codigo que no existe
        // no tiene por que arruinar una venta que ya venia sumando.
        // El codigo que fallo no se manda a la LCD: ya lo esta mostrando
        // esta pantalla, que es la que mira el operador.
        $lineaArduino = 'NO ENCONTRADO|0|0';
    }

} elseif ($vista === 'inicio' && ($_GET['ver'] ?? '') === 'total') {
    // Aca llega el navegador solo, a los 3 segundos, mandado por el
    // <meta refresh> que puso la pantalla de resultado.
    $vista = 'total';

    // Y aca se le manda el total al Arduino.
    //
    // ESTO ES NUEVO, Y ES EL CAMBIO IMPORTANTE. Antes el Arduino media
    // los 3 segundos por su cuenta con millis() y sacaba el total solo.
    // Eran DOS relojes midiendo lo mismo: este <meta refresh> y el de la
    // placa. Dos relojes separados terminan discrepando siempre.
    //
    // Ahora el reloj es uno: el navegador llega aca a los 3 segundos y
    // PHP le manda "Total" en el lugar del titulo y la suma en el lugar
    // del precio. Para el Arduino es un mensaje mas, indistinguible de
    // un libro, y por eso su programa no necesita medir nada.
    if (modoActual() === 'venta' && totalDeLaVenta() > 0) {
        $lineaArduino = sprintf('Total|%.2f|1', totalDeLaVenta());
    }
}

$venta = ventaEnCurso();
$total = totalDeLaVenta();

// La lista de clientes solo hace falta si hay algo para cobrar.
$clientes = $venta
    ? $pdo->query('SELECT id, nombre, apellido FROM clientes ORDER BY apellido, nombre')->fetchAll()
    : [];

// Si hay un alquiler en el pedido, el cliente pasa a ser obligatorio.
$hayAlquiler = false;
foreach ($venta as $r) {
    if ($r['tipo'] === 'alquiler') { $hayAlquiler = true; break; }
}
?>
<!doctype html>
<html lang="es">
<meta charset="utf-8">
<title>Escaneo</title>
<link rel="stylesheet" href="estilos.css">
<?php if ($vista === 'resultado' && $modo === 'venta'): ?>
<!-- EL RELOJ DE LOS 3 SEGUNDOS, SIN JAVASCRIPT.
     Esta linea le dice al navegador: "si en 3 segundos seguis aca, anda
     a la pantalla del total". Si mientras tanto se escanea otro libro,
     la pagina se recarga, la linea se escribe de nuevo y el reloj
     arranca de cero solo. Es HTML puro, de los anios 90. -->
<meta http-equiv="refresh" content="3;url=escaneo.php?ver=total">
<?php endif; ?>

<body class="panel">

<div class="modos">
    <form method="post" action="escaneo.php">
        <button name="modo" value="scanner" class="<?= $modo === 'scanner' ? 'modo-activo' : '' ?>">Modo scanner</button>
        <button name="modo" value="venta"   class="<?= $modo === 'venta'   ? 'modo-activo' : '' ?>">Modo venta</button>
    </form>
</div>

<!-- El campo de escaneo. El lector escribe el codigo y manda Enter,
     y ese Enter envia este formulario. Sin JavaScript. -->
<form method="get" action="escaneo.php" class="buscador">
    <input type="text" name="codigo" value="" autofocus autocomplete="off"
           placeholder="Escanea o escribi el codigo + Enter">
    <button type="submit">Buscar</button>
</form>

<?php if ($errorCobro !== ''): ?>
    <p class="error">No se cobro nada: <?= esc($errorCobro) ?></p>
<?php endif; ?>

<?php if ($vista === 'resultado' && $ejemplar): ?>
    <div class="ficha encontrado">
        <p class="etiqueta">
            <?php
            $rotulo = ['alquiler' => 'Alquiler', 'digital' => 'Libro digital', 'venta' => 'Venta'];
            echo $rotulo[$ejemplar['tipo']];
            ?>
            &middot; <?= esc($ejemplar['estado']) ?>
        </p>
        <p class="titulo"><?= esc($ejemplar['nombre']) ?></p>
        <p class="autor"><?= esc($ejemplar['autor']) ?></p>
        <p class="precio"><?= pesos($ejemplar['precio']) ?></p>
        <p class="codigo"><?= esc($ejemplar['codigo_interno']) ?><?= $ejemplar['isbn'] ? ' &middot; ISBN ' . esc($ejemplar['isbn']) : '' ?></p>
<?php if ($ejemplar['stock_total'] === null): ?>
        <p class="stock">Libro digital &middot; sin limite de stock</p>
<?php elseif ((int) $ejemplar['stock_disponible'] === 0): ?>
        <p class="stock sin-stock">SIN STOCK EN TIENDA &middot;
           <?= (int) $ejemplar['stock_total'] ?> en total, todos afuera</p>
<?php else: ?>
        <p class="stock"><?= (int) $ejemplar['stock_disponible'] ?> en tienda
           de <?= (int) $ejemplar['stock_total'] ?> en total</p>
<?php endif; ?>
    </div>
<?php if ($modo === 'venta'): ?>
    <p class="nota">Sumando. El total aparece a los 3 segundos sin escanear.</p>
<?php endif; ?>

<?php elseif ($vista === 'resultado'): ?>
    <div class="ficha sin-registro">
        <p class="etiqueta">No encontrado</p>
        <p class="titulo">Ese codigo no esta en el catalogo</p>
        <p class="codigo"><?= esc($codigo) ?></p>
    </div>
<?php if ($modo === 'venta'): ?>
    <p class="nota">La venta sigue como estaba: <?= pesos($total) ?>.</p>
<?php endif; ?>

<?php elseif ($vista === 'total'): ?>
    <div class="ficha total">
        <p class="etiqueta">Total de la compra</p>
        <p class="monto"><?= pesos($total) ?></p>
        <p class="autor"><?= count($venta) ?> articulo<?= count($venta) === 1 ? '' : 's' ?></p>
    </div>
<?php if ($venta): ?>
    <table class="renglones">
<?php foreach ($venta as $renglon): ?>
        <tr>
            <td class="mono"><?= esc($renglon['codigo']) ?></td>
            <td><?= esc($renglon['titulo']) ?></td>
            <td><span class="tipo tipo-<?= esc($renglon['tipo']) ?>"><?= esc($renglon['tipo']) ?></span></td>
            <td class="derecha"><?= pesos($renglon['precio']) ?></td>
        </tr>
<?php endforeach; ?>
    </table>

    <form method="post" action="escaneo.php" class="cobro">
        <label>Cliente
<?php if ($hayAlquiler): ?>
            <span class="obligatorio">obligatorio: hay un alquiler en el pedido</span>
<?php else: ?>
            <span class="opcional">opcional en una venta de mostrador</span>
<?php endif; ?>
            <select name="cliente_id">
<?php if (!$hayAlquiler): ?>
                <option value="0">Sin cliente</option>
<?php endif; ?>
<?php foreach ($clientes as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= esc($c['apellido']) ?>, <?= esc($c['nombre']) ?></option>
<?php endforeach; ?>
            </select>
        </label>
        <button name="cerrar" value="1" class="cerrar">Cobrar y cerrar &middot; <?= pesos($total) ?></button>
    </form>
<?php endif; ?>

<?php elseif ($vista === 'cierre'): ?>
    <div class="ficha cerrada">
        <p class="etiqueta">Pedido cobrado &middot; venta n&ordm; <?= (int) $ventaCerrada ?></p>
        <p class="monto"><?= pesos($totalCerrado) ?></p>
        <p class="autor">Stock descontado y alquileres anotados. La suma volvio a cero.</p>
    </div>

<?php else: ?>
    <p class="nota">
        <?= $modo === 'venta'
            ? 'Modo venta: cada escaneo se suma. A los 3 segundos sin escanear aparece el total.'
            : 'Modo scanner: cada escaneo muestra el precio y los datos. No suma nada.' ?>
    </p>
<?php endif; ?>

<?php if ($lineaArduino !== null): ?>
<script>
/* LA UNICA LINEA DE JAVASCRIPT DEL SISTEMA.
   Le pasa el mensaje a la pagina de afuera, que es la que tiene el
   puerto del Arduino abierto. Solo eso: no calcula ni decide nada.
   El "if" es porque si alguien abre esta pagina suelta, fuera del
   iframe, no hay pagina de afuera a quien pasarle el mensaje. */
if (window.parent && window.parent.alArduino) {
    window.parent.alArduino(<?= json_encode($lineaArduino) ?>);
}
</script>
<?php endif; ?>
</body>
</html>
