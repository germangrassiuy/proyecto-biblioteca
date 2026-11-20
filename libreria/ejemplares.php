<?php
// ============================================================
//  EJEMPLARES - lo que realmente se escanea
// ============================================================
//  Cada fila junta los ejemplares intercambiables entre si: "los
//  nuevos de este libro que estan a la venta". Por eso lleva una
//  cantidad, y no es una fila por cada libro fisico.
//
//  Aca se ve funcionando la regla "no se puede vender un libro usado",
//  y se ve DOS VECES a proposito. Ver el comentario de la doble
//  validacion, mas abajo.
// ============================================================
require 'db.php';
soloAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo   = trim($_POST['codigo_interno'] ?? '');
    $libroId  = (int) ($_POST['libro_id'] ?? 0);
    $tipo     = $_POST['tipo']   ?? '';
    $estado   = $_POST['estado'] ?? '';
    $precio   = trim($_POST['precio'] ?? '');
    $cantidad = trim($_POST['cantidad'] ?? '');

    $esDigital = ($tipo === 'digital');

    if ($codigo === '' || $libroId === 0 || $precio === '') {
        $error = 'Falta completar el codigo, el titulo o el precio.';

    } elseif (!in_array($tipo, ['venta', 'alquiler', 'digital'], true)) {
        $error = 'El tipo tiene que ser venta, alquiler o digital.';

    } elseif (!is_numeric($precio) || (float) $precio < 0) {
        $error = 'El precio tiene que ser un numero que no sea negativo.';

    } elseif ($tipo === 'venta' && $estado === 'usado') {
        // LA REGLA DEL NEGOCIO, primera barrera.
        $error = 'No se puede poner a la venta un ejemplar usado. Un libro usado solo se alquila.';

    } elseif ($tipo === 'digital' && $estado === 'usado') {
        $error = 'Un libro digital no se gasta: siempre es nuevo.';

    } elseif (!$esDigital && ($cantidad === '' || !ctype_digit($cantidad))) {
        $error = 'Los libros de papel necesitan una cantidad: cuantos tiene la libreria.';

    } else {
        // Un articulo recien cargado tiene TODOS sus ejemplares en el
        // local: nadie alquilo nada todavia. Por eso las dos cifras
        // arrancan iguales. Se separan despues, cuando se alquila.
        $total      = $esDigital ? null : (int) $cantidad;
        $disponible = $total;

        try {
            $consulta = $pdo->prepare(
                'INSERT INTO ejemplares
                        (codigo_interno, libro_id, tipo, estado, precio, stock_total, stock_disponible)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $consulta->execute([$codigo, $libroId, $tipo, $estado, $precio, $total, $disponible]);
            header('Location: ejemplares.php');
            exit;

        } catch (PDOException $e) {
            // LA DOBLE VALIDACION.
            // Si llegamos aca es porque la base rechazo algo que el PHP
            // dejo pasar. Puede parecer repetido y no lo es: el PHP solo
            // protege ESTE formulario. La base protege TODO, incluso si
            // alguien carga datos desde otro programa que abra la base
            // o desde una pagina que escribamos el anio que viene.
            // La base es la ultima linea de defensa y no se puede saltear.
            $m = $e->getMessage();

            if (str_contains($m, 'venta_solo_nuevo')) {
                $error = 'La base rechazo el ejemplar: no se vende un libro usado.';
            } elseif (str_contains($m, 'digital_siempre_nuevo')) {
                $error = 'La base rechazo el ejemplar: un libro digital no puede estar usado.';
            } elseif (str_contains($m, 'stock_segun_el_tipo')) {
                $error = 'La base rechazo el stock: los digitales no llevan cantidad y los de papel si.';
            } elseif (str_contains($m, 'stock_coherente')) {
                $error = 'La base rechazo el stock: no puede ser negativo.';
            } elseif (str_contains($m, 'codigo_interno') || str_contains($m, 'Duplicate')) {
                $error = 'Ya existe un ejemplar con la etiqueta ' . $codigo . '. Cada uno necesita la suya.';
            } else {
                $error = 'No se pudo guardar: ' . $m;
            }
        }
    }
}

$libros = $pdo->query('SELECT id, nombre, autor FROM libros ORDER BY nombre')->fetchAll();

$ejemplares = $pdo->query(
    'SELECT  e.codigo_interno, e.tipo, e.estado, e.precio,
             e.stock_total, e.stock_disponible, l.nombre, l.autor
       FROM  ejemplares e
       JOIN  libros l ON l.id = e.libro_id
   ORDER BY  e.codigo_interno'
)->fetchAll();

cabecera('Ejemplares', 'ejemplares');
?>

<main>
    <h2>Agregar un ejemplar</h2>

    <p class="nota">
        El codigo es el de la <strong>etiqueta que pega la libreria</strong>, no el
        ISBN de la tapa. Dos formatos del mismo libro llevan etiquetas distintas:
        es lo unico que permite saber cual tenes en la mano al escanear.
        Se usa <span class="mono">V0001</span> para venta,
        <span class="mono">A0001</span> para alquiler y
        <span class="mono">D0001</span> para digital.
    </p>

<?php if ($error !== ''): ?>
    <p class="error"><?= esc($error) ?></p>
<?php endif; ?>

<?php if (!$libros): ?>
    <p class="error">Primero hay que cargar algun titulo en <a href="libros.php">Libros</a>.</p>
<?php else: ?>
    <form method="post" action="ejemplares.php" class="alta">
        <label>Codigo de la etiqueta
            <input type="text" name="codigo_interno" autocomplete="off" autofocus>
        </label>
        <label>Titulo
            <select name="libro_id">
<?php foreach ($libros as $libro): ?>
                <option value="<?= (int) $libro['id'] ?>"><?= esc($libro['nombre']) ?> — <?= esc($libro['autor']) ?></option>
<?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="tipo">
                <option value="venta">Venta</option>
                <option value="alquiler">Alquiler</option>
                <option value="digital">Digital</option>
            </select>
        </label>
        <label>Estado
            <select name="estado">
                <option value="nuevo">Nuevo</option>
                <option value="usado">Usado (solo para alquiler)</option>
            </select>
        </label>
        <label>Precio
            <input type="text" name="precio" autocomplete="off" placeholder="690.00">
        </label>
        <label>Cantidad <span class="opcional">(cuantos tiene la libreria. Dejar vacio si es digital: un archivo no se agota)</span>
            <input type="text" name="cantidad" autocomplete="off" placeholder="5">
        </label>
        <button type="submit">Guardar ejemplar</button>
    </form>
<?php endif; ?>

    <h2>Ejemplares cargados (<?= count($ejemplares) ?>)</h2>

    <p class="nota">
        <strong>Total</strong> es cuantos tiene la libreria. <strong>En tienda</strong> es
        cuantos hay ahora en el local. Cuando se alquila uno, baja "en tienda" pero
        "total" queda igual: el libro sigue siendo de la libreria, solo que hoy no esta.
    </p>

<?php if (!$ejemplares): ?>
    <p class="nota">Todavia no hay ejemplares cargados.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Etiqueta</th><th>Titulo</th><th>Tipo</th><th>Estado</th>
            <th class="derecha">Precio</th><th class="derecha">Total</th><th class="derecha">En tienda</th>
        </tr>
<?php foreach ($ejemplares as $ej): ?>
        <tr>
            <td class="mono"><?= esc($ej['codigo_interno']) ?></td>
            <td><?= esc($ej['nombre']) ?><br><span class="autor-chico"><?= esc($ej['autor']) ?></span></td>
            <td><span class="tipo tipo-<?= esc($ej['tipo']) ?>"><?= esc($ej['tipo']) ?></span></td>
            <td><?= esc($ej['estado']) ?></td>
            <td class="derecha mono"><?= pesos($ej['precio']) ?></td>
            <td class="derecha mono"><?= $ej['stock_total'] === null ? '—' : (int) $ej['stock_total'] ?></td>
            <td class="derecha mono">
<?php if ($ej['stock_total'] === null): ?>
                <span class="autor-chico">ilimitado</span>
<?php elseif ((int) $ej['stock_disponible'] === 0): ?>
                <span class="sin-stock">0</span>
<?php else: ?>
                <?= (int) $ej['stock_disponible'] ?>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</main>
</body>
</html>
