<?php
// ============================================================
//  LIBROS - el catalogo de titulos
// ============================================================
//  Aca se carga el libro como OBRA: nombre, autor, ISBN y tapa.
//  El precio NO va aca: va en cada ejemplar, porque el mismo titulo
//  puede estar nuevo para vender, usado para alquilar y en digital, a
//  tres precios distintos. Eso se carga en ejemplares.php
// ============================================================
require 'db.php';
soloAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn   = trim($_POST['isbn'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $autor  = trim($_POST['autor'] ?? '');
    $imagen = trim($_POST['imagen'] ?? '');

    if ($nombre === '' || $autor === '') {
        $error = 'El nombre y el autor no pueden quedar vacios.';
    } else {
        $consulta = $pdo->prepare('INSERT INTO libros (isbn, nombre, autor, imagen) VALUES (?, ?, ?, ?)');
        // Los campos vacios se guardan como NULL y no como cadena vacia.
        // NULL significa "no tiene"; la cadena vacia significaria "tiene
        // uno, y es vacio". No es lo mismo, y en las consultas se nota.
        $consulta->execute([
            $isbn   === '' ? null : $isbn,
            $nombre,
            $autor,
            $imagen === '' ? null : $imagen,
        ]);
        header('Location: libros.php');
        exit;
    }
}

// Se cuentan los ejemplares de cada titulo con un LEFT JOIN:
// "left" porque queremos que aparezcan tambien los titulos que todavia
// no tienen ningun ejemplar cargado. Con un JOIN comun desaparecerian.
$libros = $pdo->query(
    'SELECT   l.id, l.isbn, l.nombre, l.autor, l.imagen, COUNT(e.id) AS ejemplares
       FROM   libros l
  LEFT JOIN   ejemplares e ON e.libro_id = l.id
   GROUP BY   l.id, l.isbn, l.nombre, l.autor, l.imagen
   ORDER BY   l.nombre'
)->fetchAll();

cabecera('Libros', 'libros.php');
?>

<main>
    <h2>Agregar un titulo</h2>

<?php if ($error !== ''): ?>
    <p class="error"><?= esc($error) ?></p>
<?php endif; ?>

    <form method="post" action="libros.php" class="alta">
        <label>Nombre del libro
            <input type="text" name="nombre" autocomplete="off" autofocus>
        </label>
        <label>Autor
            <input type="text" name="autor" autocomplete="off">
        </label>
        <label>ISBN de la tapa <span class="opcional">(opcional: un libro que solo existe en digital no tiene tapa)</span>
            <input type="text" name="isbn" autocomplete="off">
        </label>
        <label>Archivo de la tapa <span class="opcional">(opcional: nombre del archivo dentro de la carpeta portadas/, por ejemplo <span class="mono">rayuela.jpg</span>)</span>
            <input type="text" name="imagen" autocomplete="off">
        </label>
        <button type="submit">Guardar titulo</button>
    </form>

    <p class="nota">
        La tapa se guarda como <strong>nombre de archivo</strong>, no como imagen. La foto
        va en la carpeta <span class="mono">portadas/</span> y en la base queda solo el
        nombre. Si se guardaran las imagenes adentro de la base, cada copia de seguridad
        pasaria a pesar gigabytes. Los libros sin tapa cargada se muestran en el catalogo
        con una tapa dibujada.
    </p>

    <h2>Titulos cargados (<?= count($libros) ?>)</h2>

<?php if (!$libros): ?>
    <p class="nota">Todavia no hay titulos cargados.</p>
<?php else: ?>
    <table>
        <tr><th>Nombre</th><th>Autor</th><th>ISBN</th><th>Tapa</th><th class="derecha">Ejemplares</th></tr>
<?php foreach ($libros as $libro): ?>
        <tr>
            <td><?= esc($libro['nombre']) ?></td>
            <td><?= esc($libro['autor']) ?></td>
            <td class="mono"><?= $libro['isbn'] ? esc($libro['isbn']) : '—' ?></td>
            <td class="mono"><?= $libro['imagen'] ? esc($libro['imagen']) : '—' ?></td>
            <td class="derecha">
                <?= (int) $libro['ejemplares'] ?>
<?php if ((int) $libro['ejemplares'] === 0): ?>
                <span class="aviso-chico">sin ejemplares, no se puede escanear</span>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
</main>
</body>
</html>
