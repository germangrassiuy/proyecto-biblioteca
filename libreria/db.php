<?php
// ============================================================
//  CONEXION, SESION Y FUNCIONES COMUNES
//  Todas las paginas empiezan con require 'db.php';
// ============================================================

// La sesion es la memoria del servidor para UN visitante concreto.
// Guarda dos cosas: quien entro (admin o cliente) y la venta en curso.
// El navegador solo recibe una cookie con un numero de sesion; los datos
// quedan del lado del servidor. Eso es lo que permite ir sumando la
// venta "a escondidas": el total existe y no se muestra hasta pedirlo.
session_start();

// ------------------------------------------------------------
//  LA BASE: un archivo SQLite
// ------------------------------------------------------------
//  Con MySQL, aca irian el servidor, el usuario y la clave. Con SQLite
//  no hay servidor: la base es un archivo y alcanza con decir donde esta.
//  __DIR__ es la carpeta de ESTE archivo, asi la ruta funciona desde
//  cualquier computadora, este donde este copiada la carpeta.
$archivoBase = __DIR__ . '/datos/biblioteca.sqlite';
$baseNueva   = !file_exists($archivoBase);

if (!is_dir(dirname($archivoBase))) {
    mkdir(dirname($archivoBase));
}

try {
    $pdo = new PDO('sqlite:' . $archivoBase, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // SQLite trae las FOREIGN KEY APAGADAS de fabrica (por compatibilidad
    // con programas viejos) y hay que prenderlas en cada conexion. Sin
    // esta linea se podria cargar un ejemplar de un libro que no existe,
    // y la base no diria nada.
    $pdo->exec('PRAGMA foreign_keys = ON');

    // La primera vez el archivo no existe: se crea la base leyendo
    // biblioteca.sql. Por eso no hay que importar nada a mano.
    if ($baseNueva) {
        $pdo->exec(file_get_contents(__DIR__ . '/biblioteca.sql'));
    }
} catch (PDOException $e) {
    // Si fallo justo al crearla, se borra el archivo a medio hacer: si no,
    // la proxima vez existiria y nadie la volveria a crear.
    if ($baseNueva && file_exists($archivoBase)) {
        $pdo = null;
        unlink($archivoBase);
    }
    exit('No se pudo abrir la base de datos. Detalle: ' . $e->getMessage());
}


// ------------------------------------------------------------
//  esc() - imprimir texto dentro del HTML de forma segura
// ------------------------------------------------------------
//  Si alguien carga un libro llamado <script>algo</script>, sin esta
//  funcion el navegador ejecutaria ese codigo en vez de mostrarlo.
//  Se usa en TODA salida que venga de la base o del usuario.
function esc($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function pesos($importe) {
    return '$ ' . number_format((float) $importe, 2, ',', '.');
}


// ------------------------------------------------------------
//  paraLcd() - dejar un texto que la pantalla del Arduino pueda mostrar
// ------------------------------------------------------------
//  La LCD usa el juego de caracteres HD44780, que no tiene vocales con
//  tilde ni enie: dibujaria un simbolo raro. Ademas sacamos la barra |,
//  que es el separador de campos del mensaje: si viniera dentro de un
//  titulo, el Arduino contaria mal los campos.
function paraLcd($texto) {
    $con   = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ','ü','Ü','¿','¡'];
    $sin   = ['a','e','i','o','u','A','E','I','O','U','n','N','u','U','?','!'];
    $texto = str_replace($con, $sin, $texto);
    $texto = str_replace(['|', "\r", "\n"], ' ', $texto);
    return trim($texto);
}


// ============================================================
//  QUIEN ENTRO: admin o cliente
// ============================================================
//  Hay dos clases de usuario y NO ven lo mismo:
//    admin    -> el mostrador, la carga de libros, el stock, los clientes
//    cliente  -> solamente el catalogo, para mirar
//
//  Lo importante: esto no se resuelve escondiendo botones. Se resuelve
//  preguntando en CADA pagina si el que entro tiene permiso. Un menu sin
//  el boton "Ejemplares" no protege nada: alcanza con escribir la
//  direccion a mano para entrar igual. Por eso cada pagina de admin
//  llama a soloAdmin() en la primera linea.
// ============================================================

function usuarioActual() {
    return $_SESSION['usuario'] ?? null;   // null = no entro nadie
}

function esAdmin() {
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'admin';
}

function paginaDeInicio() {
    return esAdmin() ? 'mostrador.php' : 'catalogo.php';
}

// Corta la pagina si el que mira no es admin.
function soloAdmin() {
    if (usuarioActual() === null) {
        header('Location: entrar.php');
        exit;
    }
    if (!esAdmin()) {
        header('Location: catalogo.php');
        exit;
    }
}

// Corta la pagina si no entro nadie. Sirve para el catalogo, que lo ven
// los dos roles.
function soloIdentificado() {
    if (usuarioActual() === null) {
        header('Location: entrar.php');
        exit;
    }
}


// ------------------------------------------------------------
//  cabecera() - el encabezado y el menu, una sola vez
// ------------------------------------------------------------
//  Esta escrito en un solo lugar y lo usan todas las paginas. Si manana
//  se agrega una seccion, se agrega aca y aparece en todas. Copiar y
//  pegar el menu en cada archivo es el camino seguro a que queden
//  distintos entre si.
//
//  El menu cambia segun el rol: cada uno ve el suyo.
// ------------------------------------------------------------
//  El tercer parametro existe por el mostrador: esa pagina no se puede
//  recargar nunca (tiene el puerto del Arduino abierto), asi que sus
//  enlaces tienen que abrirse en otra pestania. Ver explicaciones/02.
function cabecera($titulo, $actual = '', $enlacesEnOtraPestania = false) {
    $u = usuarioActual();

    $menu = esAdmin()
        ? ['mostrador.php' => 'Mostrador', 'catalogo.php' => 'Catalogo',
           'libros.php' => 'Libros', 'ejemplares.php' => 'Ejemplares',
           'alquileres.php' => 'Alquileres', 'clientes.php' => 'Clientes']
        : ['catalogo.php' => 'Catalogo'];

    echo '<!doctype html><html lang="es"><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Libreria &middot; ' . esc($titulo) . '</title>';
    echo '<link rel="stylesheet" href="estilos.css">';
    echo '<body>';
    echo '<header><div class="barra"><h1>Libreria</h1>';

    if ($u) {
        echo '<div class="quien">'
           . '<span class="rol rol-' . esc($u['rol']) . '">' . esc($u['rol']) . '</span> '
           . esc($u['nombre'])
           . ' &middot; <a href="salir.php">Salir</a></div>';
    }
    echo '</div><nav>';
    $destino = $enlacesEnOtraPestania ? ' target="_blank"' : '';
    foreach ($menu as $archivo => $rotulo) {
        if ($archivo === $actual) {
            // El enlace a la pagina en la que ya estamos no necesita
            // abrir nada: se marca y listo.
            echo '<a href="' . $archivo . '" class="activo">' . $rotulo . '</a> ';
        } else {
            echo '<a href="' . $archivo . '"' . $destino . '>' . $rotulo . '</a> ';
        }
    }
    echo '</nav></header>';
}


// ============================================================
//  LA VENTA EN CURSO
// ============================================================
//  Vive en la sesion. Es una lista: cada elemento es un renglon con lo
//  que se escaneo. El total se CALCULA sumando la lista, no se guarda
//  aparte: asi nunca puede quedar desincronizado con los renglones.
// ============================================================

function ventaEnCurso() {
    return $_SESSION['venta'] ?? [];
}

function agregarALaVenta($ejemplar) {
    $_SESSION['venta'][] = [
        'id'     => $ejemplar['id'],
        'codigo' => $ejemplar['codigo_interno'],
        'titulo' => $ejemplar['nombre'],
        'tipo'   => $ejemplar['tipo'],
        'precio' => $ejemplar['precio'],
    ];
}

function totalDeLaVenta() {
    $total = 0;
    foreach (ventaEnCurso() as $renglon) {
        $total += (float) $renglon['precio'];
    }
    return $total;
}

function cerrarLaVenta() {
    $_SESSION['venta'] = [];
}

function modoActual() {
    return $_SESSION['modo'] ?? 'scanner';
}


// ============================================================
//  COBRAR - guardar la venta y mover el stock
// ============================================================
//  Cobrar un pedido no es una sola cosa: son varias, y TODAS tienen que
//  pasar o NINGUNA.
//
//      1. Revisar que haya stock de cada articulo.
//      2. Guardar la cabecera de la venta.
//      3. Guardar una linea por articulo.
//      4. Descontar el stock.
//      5. Si hay alquileres, anotar quien se llevo cada libro.
//
//  Imagina que se corta la luz entre el paso 3 y el 4: la venta quedaria
//  guardada y el stock sin descontar. La libreria creeria tener libros
//  que ya no tiene, y nadie se enteraria hasta que alguien los busque.
//
//  Para eso existe la TRANSACCION. beginTransaction() abre el paquete,
//  commit() lo confirma entero, y rollBack() lo deshace entero. Entre el
//  begin y el commit, para el resto del mundo no paso NADA todavia. Si
//  algo falla en el medio, la base vuelve sola al estado anterior: no
//  hay que limpiar a mano lo que ya se habia escrito.
//
//  Regla practica: si una operacion toca mas de una tabla y tiene que
//  quedar consistente, va adentro de una transaccion.
// ============================================================
function cobrarLaVenta($pdo, $clienteId) {

    $renglones = ventaEnCurso();
    if (!$renglones) {
        return ['ok' => false, 'mensaje' => 'No hay nada para cobrar.'];
    }

    // Cuantas veces se escaneo cada articulo. Hace falta porque se puede
    // escanear el mismo libro tres veces, y hay que ver si hay tres.
    $cuantos     = [];
    $hayAlquiler = false;
    foreach ($renglones as $r) {
        $cuantos[$r['id']] = ($cuantos[$r['id']] ?? 0) + 1;
        if ($r['tipo'] === 'alquiler') {
            $hayAlquiler = true;
        }
    }

    // Una venta de mostrador puede ser anonima: no hay por que obligar a
    // nadie a dar su nombre para comprar un libro. Un ALQUILER no: si no
    // se sabe quien se lo llevo, no hay a quien reclamarselo.
    if ($hayAlquiler && !$clienteId) {
        return ['ok' => false,
                'mensaje' => 'Hay un alquiler en el pedido, asi que hay que elegir el cliente. Si no, nadie sabria quien se llevo el libro.'];
    }

    $total = totalDeLaVenta();

    // UN SOLO RELOJ.
    // La fecha la pone SIEMPRE la base, nunca PHP con date(). Parece un
    // detalle y no lo es: si PHP y la base estan configurados en zonas
    // horarias distintas, una venta hecha a las 23:15 quedaria con la
    // venta fechada hoy y el alquiler fechado maniana. El calculo de
    // "dias afuera" saldria mal y nadie entenderia por que. Si hay dos
    // relojes, tarde o temprano discrepan.
    //
    // En SQLite la fecha de hoy se pide con date('now', 'localtime').
    // El 'localtime' NO es opcional: 'now' solo da la hora de Greenwich
    // (UTC), que en Uruguay va 3 horas adelantada. Sin el, todo lo que se
    // vende despues de las 21:00 quedaria fechado al dia siguiente.

    $pdo->beginTransaction();

    try {
        // ---- 1. Revisar el stock ----
        foreach ($cuantos as $ejemplarId => $sellevan) {
            // El peligro aca: dos cajas cobrando a la vez podrian leer
            // "queda 1" las dos y vender el mismo libro dos veces.
            // En MySQL eso se evita con FOR UPDATE, que reserva la fila.
            // SQLite no lo tiene ni lo necesita: cuando una transaccion
            // empieza a escribir, bloquea la base ENTERA, y la otra caja
            // recibe un error en vez de escribir encima. Ese error cae en
            // el catch de abajo y no se cobra nada.
            $consulta = $pdo->prepare(
                'SELECT  e.tipo, e.stock_disponible, l.nombre
                   FROM  ejemplares e
                   JOIN  libros l ON l.id = e.libro_id
                  WHERE  e.id = ?'
            );
            $consulta->execute([$ejemplarId]);
            $ejemplar = $consulta->fetch();

            if (!$ejemplar) {
                throw new RuntimeException('Un articulo del pedido ya no existe en el catalogo.');
            }
            // Los digitales no tienen stock que revisar: no se agotan.
            if ($ejemplar['tipo'] !== 'digital'
                && (int) $ejemplar['stock_disponible'] < $sellevan) {
                throw new RuntimeException(sprintf(
                    'No alcanza el stock de "%s": el pedido lleva %d y en tienda hay %d.',
                    $ejemplar['nombre'], $sellevan, (int) $ejemplar['stock_disponible']
                ));
            }
        }

        // ---- 2. La cabecera de la venta ----
        $consulta = $pdo->prepare("INSERT INTO ventas (cliente_id, fecha, total) VALUES (?, datetime('now', 'localtime'), ?)");
        $consulta->execute([$clienteId ?: null, $total]);
        $ventaId = $pdo->lastInsertId();

        // ---- 3, 4 y 5: una linea por articulo, y mover el stock ----
        $guardarLinea = $pdo->prepare(
            'INSERT INTO ventas_lineas (venta_id, ejemplar_id, precio, tipo) VALUES (?, ?, ?, ?)');

        // En una VENTA el libro se va y no vuelve: bajan las dos cifras.
        $descontarVenta = $pdo->prepare(
            'UPDATE ejemplares
                SET stock_total = stock_total - 1,
                    stock_disponible = stock_disponible - 1
              WHERE id = ?');

        // En un ALQUILER el libro sigue siendo de la libreria: baja solo
        // el disponible. El total no se toca.
        $descontarAlquiler = $pdo->prepare(
            'UPDATE ejemplares SET stock_disponible = stock_disponible - 1 WHERE id = ?');

        $anotarAlquiler = $pdo->prepare(
            "INSERT INTO alquileres (ejemplar_id, cliente_id, fecha_salida, fecha_devolucion)
             VALUES (?, ?, date('now', 'localtime'), NULL)");

        foreach ($renglones as $r) {
            $guardarLinea->execute([$ventaId, $r['id'], $r['precio'], $r['tipo']]);

            if ($r['tipo'] === 'venta') {
                $descontarVenta->execute([$r['id']]);
            } elseif ($r['tipo'] === 'alquiler') {
                $descontarAlquiler->execute([$r['id']]);
                $anotarAlquiler->execute([$r['id'], $clienteId]);
            }
            // Digital: no hay nada que descontar.
        }

        $pdo->commit();       // recien ACA queda escrito, todo junto
        cerrarLaVenta();      // la sesion arranca limpia para el proximo

        return ['ok' => true, 'total' => $total, 'venta_id' => $ventaId];

    } catch (Exception $e) {
        // Algo fallo: la base vuelve sola al estado de antes del begin.
        $pdo->rollBack();
        return ['ok' => false, 'mensaje' => $e->getMessage()];
    }
}


// ------------------------------------------------------------
//  devolverUnAlquiler() - el libro vuelve al local
// ------------------------------------------------------------
//  Tambien toca dos tablas, asi que tambien va en una transaccion.
// ------------------------------------------------------------
function devolverUnAlquiler($pdo, $alquilerId) {
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            'SELECT ejemplar_id FROM alquileres
              WHERE id = ? AND fecha_devolucion IS NULL');
        $consulta->execute([$alquilerId]);
        $fila = $consulta->fetch();

        if (!$fila) {
            throw new RuntimeException('Ese alquiler no existe o ya fue devuelto.');
        }

        // Tambien aca la fecha la pone la base, por la misma razon.
        $pdo->prepare("UPDATE alquileres SET fecha_devolucion = date('now', 'localtime') WHERE id = ?")
            ->execute([$alquilerId]);

        $pdo->prepare('UPDATE ejemplares SET stock_disponible = stock_disponible + 1 WHERE id = ?')
            ->execute([$fila['ejemplar_id']]);

        $pdo->commit();
        return ['ok' => true];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['ok' => false, 'mensaje' => $e->getMessage()];
    }
}
