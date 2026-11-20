<?php
// ============================================================
//  CATALOGO - lo que ve el cliente
// ============================================================
//  Un libro puede estar en tres formatos: venta, alquiler y digital.
//  En la tabla "ejemplares" eso son TRES FILAS. Si las mostraramos tal
//  cual, el mismo titulo aparecería tres veces y el catalogo seria
//  ilegible.
//
//  Lo que queremos es UNA fila por titulo, con un tag por cada formato
//  que tenga. La consulta de abajo es la que hace esa vuelta.
// ============================================================
require 'db.php';
soloIdentificado();   // el catalogo lo ven los dos roles, pero hay que entrar

$busqueda = trim($_GET['buscar'] ?? '');

// ------------------------------------------------------------
//  LA CONSULTA: de tres filas por libro a una sola
// ------------------------------------------------------------
//  GROUP BY l.id junta las filas del mismo libro en una.
//
//  Y despues, el truco: MAX(CASE WHEN tipo='venta' THEN precio END).
//  Se lee asi: "de las filas de este libro, mira solo la de venta y
//  traeme su precio". Las demas filas devuelven NULL y MAX las ignora.
//  Con eso pasamos de "una fila por formato" a "una columna por formato",
//  que es justo lo que necesita la pantalla.
//
//  Si un libro no tiene formato digital, precio_digital queda en NULL, y
//  eso es lo que usamos despues para decidir si dibujar el tag o no.
// ------------------------------------------------------------
$sql =
  "SELECT  l.id, l.nombre, l.autor, l.isbn, l.imagen,
           MAX(CASE WHEN e.tipo = 'venta'    THEN e.precio END)           AS precio_venta,
           MAX(CASE WHEN e.tipo = 'alquiler' THEN e.precio END)           AS precio_alquiler,
           MAX(CASE WHEN e.tipo = 'digital'  THEN e.precio END)           AS precio_digital,
           MAX(CASE WHEN e.tipo = 'venta'    THEN e.stock_disponible END) AS hay_venta,
           MAX(CASE WHEN e.tipo = 'alquiler' THEN e.stock_disponible END) AS hay_alquiler
     FROM  libros l
     JOIN  ejemplares e ON e.libro_id = l.id";

$parametros = [];
if ($busqueda !== '') {
    // LIKE con % busca "que contenga". Va como parametro, no pegado al
    // SQL: si alguien escribe comillas en el buscador, quedan tratadas
    // como texto y no como instrucciones.
    $sql .= " WHERE l.nombre LIKE ? OR l.autor LIKE ?";
    $parametros = ['%' . $busqueda . '%', '%' . $busqueda . '%'];
}

$sql .= " GROUP BY l.id, l.nombre, l.autor, l.isbn, l.imagen ORDER BY l.nombre";

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$libros = $consulta->fetchAll();

// Cuantos titulos hay en total, para poder decir "mostrando X de Y".
$totalTitulos = $pdo->query('SELECT COUNT(DISTINCT libro_id) FROM ejemplares')->fetchColumn();

cabecera('Catalogo', 'catalogo.php');
?>

<main class="ancho">
    <h2>Catalogo</h2>

    <form method="get" action="catalogo.php" class="buscador">
        <input type="text" name="buscar" value="<?= esc($busqueda) ?>"
               autocomplete="off" placeholder="Buscar por titulo o autor">
        <button type="submit">Buscar</button>
<?php if ($busqueda !== ''): ?>
        <a class="boton-flojo" href="catalogo.php">Ver todos</a>
<?php endif; ?>
    </form>

    <p class="nota">
<?php if ($busqueda !== ''): ?>
        <?= count($libros) ?> de <?= (int) $totalTitulos ?> titulos coinciden con
        &laquo;<?= esc($busqueda) ?>&raquo;.
<?php else: ?>
        <?= (int) $totalTitulos ?> titulos. Cada libro aparece una sola vez, con un
        tag por cada formato en que se consigue.
<?php endif; ?>
    </p>

<?php if (!$libros): ?>
    <p class="error">No hay ningun libro que coincida con esa busqueda.</p>
<?php else: ?>
    <ul class="catalogo">
<?php foreach ($libros as $libro): ?>
        <li>
<?php if ($libro['imagen']): ?>
            <img class="tapa" src="portadas/<?= esc($libro['imagen']) ?>"
                 alt="Tapa de <?= esc($libro['nombre']) ?>">
<?php else: ?>
            <?php
            // Todavia no hay foto de la tapa, asi que la dibujamos.
            // El color sale del id del libro: el mismo libro tiene siempre
            // el mismo color, y dos libros distintos casi nunca coinciden.
            // No es al azar a proposito: si fuera al azar, la tapa
            // cambiaria de color en cada recarga y marearia.
            $color = 'tapa-' . ($libro['id'] % 6);
            ?>
            <div class="tapa tapa-dibujada <?= $color ?>">
                <span class="tapa-titulo"><?= esc($libro['nombre']) ?></span>
                <span class="tapa-filete"></span>
            </div>
<?php endif; ?>
            <div class="obra">
                <p class="titulo-obra"><?= esc($libro['nombre']) ?></p>
                <p class="autor-obra"><?= esc($libro['autor']) ?></p>
<?php if ($libro['isbn']): ?>
                <p class="isbn-obra">ISBN <?= esc($libro['isbn']) ?></p>
<?php endif; ?>
            </div>
            <div class="formatos">

<?php if ($libro['precio_venta'] !== null): ?>
                <span class="tag tag-venta <?= (int) $libro['hay_venta'] === 0 ? 'agotado' : '' ?>">
                    Venta
                    <b><?= pesos($libro['precio_venta']) ?></b>
<?php if ((int) $libro['hay_venta'] === 0): ?>
                    <i>sin stock</i>
<?php endif; ?>
                </span>
<?php endif; ?>

<?php if ($libro['precio_alquiler'] !== null): ?>
                <span class="tag tag-alquiler <?= (int) $libro['hay_alquiler'] === 0 ? 'agotado' : '' ?>">
                    Alquiler
                    <b><?= pesos($libro['precio_alquiler']) ?></b>
<?php if ((int) $libro['hay_alquiler'] === 0): ?>
                    <i>todos alquilados</i>
<?php endif; ?>
                </span>
<?php endif; ?>

<?php if ($libro['precio_digital'] !== null): ?>
                <span class="tag tag-digital">
                    Digital
                    <b><?= pesos($libro['precio_digital']) ?></b>
                </span>
<?php endif; ?>

            </div>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</main>
</body>
</html>
