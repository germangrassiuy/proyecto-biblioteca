-- ============================================================
--  LIBRERIA - BASE DE DATOS (SQLite)
-- ============================================================
--  No hay que importar nada a mano: la primera vez que se abre el
--  sistema, db.php crea la base leyendo este archivo. La base queda en
--  libreria/datos/biblioteca.sqlite, que es UN archivo comun.
--
--  Para volver a empezar limpio (borrando lo que se cargo a mano):
--  cerrar el sistema, borrar ese archivo y volver a abrirlo.
--
--  POR QUE SQLite Y NO MySQL
--  MySQL es un SERVIDOR: un programa aparte que hay que instalar y dejar
--  prendido (por ejemplo con XAMPP). SQLite es una libreria que viene
--  adentro de PHP y guarda toda la base en un solo archivo. Por eso el
--  sistema anda en cualquier computadora sin instalar nada: se copia la
--  carpeta y listo. El SQL es casi el mismo; las diferencias estan
--  explicadas donde aparecen.
--
--  En SQLite no hay CREATE DATABASE ni USE: la base ES el archivo.
-- ============================================================

-- Se borran en este orden y no en otro: "ejemplares" apunta a "libros",
-- asi que hay que sacar primero al que apunta. Al reves la base lo
-- impide, justamente para que no queden ejemplares huerfanos.
DROP TABLE IF EXISTS ventas_lineas;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS alquileres;
DROP TABLE IF EXISTS ejemplares;
DROP TABLE IF EXISTS libros;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;


-- ------------------------------------------------------------
--  USUARIOS - quien puede entrar y que puede ver
-- ------------------------------------------------------------
--  Hay dos roles y NO ven lo mismo:
--    admin    -> el mostrador, la carga de libros, el stock, los clientes
--    cliente  -> solamente el catalogo, para mirar
--
--  LA CONTRASENIA NO SE GUARDA. Lo que se guarda es un "hash": un
--  resultado que sale de la contrasenia y del que NO se puede volver
--  atras. Cuando alguien entra, se calcula el hash de lo que escribio y
--  se compara con el guardado.
--
--  Por que importa: si alguien roba la base, no se lleva las
--  contrasenias, se lleva hashes que no sirven para entrar. Y ni
--  siquiera nosotros, que hicimos el sistema, podemos saber cual es la
--  contrasenia de un usuario. Guardarlas en texto plano es de los
--  errores mas caros que se pueden cometer.
--
--  El hash empieza con $2y$ porque lo genero bcrypt, que es el algoritmo
--  que usa PHP con password_hash(). Es lento A PROPOSITO: asi alguien que
--  quiera probar millones de contrasenias tarda anios en vez de minutos.
-- ------------------------------------------------------------
-- DOS DIFERENCIAS CON MySQL que aparecen en todas las tablas:
--  - INTEGER PRIMARY KEY se numera solo (1, 2, 3...). Es lo que en MySQL
--    se escribe INT AUTO_INCREMENT PRIMARY KEY.
--  - SQLite no tiene ENUM. Se usa TEXT con un CHECK que dice que valores
--    se aceptan: hace lo mismo, y la regla queda a la vista.
CREATE TABLE usuarios (
    id         INTEGER PRIMARY KEY,
    usuario    VARCHAR(40) NOT NULL UNIQUE,
    clave_hash VARCHAR(255) NOT NULL,
    rol        TEXT NOT NULL CHECK (rol IN ('admin','cliente')),
    nombre     VARCHAR(80) NOT NULL
);

-- Cuentas de prueba. En un sistema de verdad estas se borran y cada
-- persona se crea la suya con una contrasenia que solo ella sepa.
--   admin   / admin123
--   cliente / cliente123
INSERT INTO usuarios (usuario, clave_hash, rol, nombre) VALUES
    ('admin',   '$2y$10$ubZtuEDnCpCRxY6r0.Xv/ux6MfyB3wuCDWs4SKBbURlc374IYKk.i', 'admin',   'Encargada del local'),
    ('cliente', '$2y$10$seRiP3yP/TG3qgvSFFAad.bILZzG9b3WIh0508bhM2vbOGXVbnGoa', 'cliente', 'Cliente de ejemplo');


-- ------------------------------------------------------------
--  LIBROS - el titulo, la obra
-- ------------------------------------------------------------
--  Aca va el libro como obra: "Cien anos de soledad, de Garcia Marquez".
--  NO va el precio ni el estado, porque eso cambia segun el formato: el
--  mismo titulo puede estar nuevo para vender a $690, usado para alquilar
--  a $95 y en digital a $410. Son tres cosas distintas del mismo libro.
-- ------------------------------------------------------------
CREATE TABLE libros (
    id     INTEGER PRIMARY KEY,

    -- El ISBN se guarda como TEXTO y no como numero por dos razones:
    --  1. Puede empezar con cero, y un campo numerico se lo comeria.
    --  2. Nunca vamos a sumar ni restar ISBNs. No es una cantidad, es un
    --     nombre que resulta estar hecho de digitos.
    -- Admite NULL: un libro que solo existe en digital no tiene tapa,
    -- y por lo tanto no tiene ISBN impreso.
    isbn   VARCHAR(20)  NULL,

    nombre VARCHAR(150) NOT NULL,
    autor  VARCHAR(100) NOT NULL,

    -- La tapa del libro. Se guarda el NOMBRE DEL ARCHIVO, no la imagen.
    --
    -- Es una decision importante y conviene entender por que: una base de
    -- datos puede guardar imagenes adentro, pero se vuelve enorme y lenta,
    -- y cada copia de seguridad pasa a pesar gigabytes. Lo que se hace es
    -- guardar la foto como archivo en una carpeta (portadas/) y en la base
    -- solo el nombre: "cien-anos.jpg". La base queda liviana y el servidor
    -- web entrega las imagenes, que es lo que sabe hacer rapido.
    --
    -- Admite NULL: si el libro todavia no tiene tapa cargada, la pagina
    -- dibuja una tapa generada con el titulo.
    imagen VARCHAR(255) NULL
);


-- ------------------------------------------------------------
--  EJEMPLARES - lo que realmente se escanea
-- ------------------------------------------------------------
--  Cada fila junta los ejemplares que son INTERCAMBIABLES entre si:
--  "los ejemplares nuevos de este libro que estan a la venta". Por eso
--  tiene una cantidad y no es una fila por cada libro fisico.
--
--  POR QUE NO ESCANEAMOS EL ISBN DE LA TAPA:
--  El ISBN identifica la EDICION. Todas las copias de la misma edicion
--  traen el mismo codigo impreso. Entonces el ejemplar nuevo que se vende
--  y el usado que se alquila tienen el MISMO codigo en la tapa, y al
--  escanear el sistema no podria saber cual de los dos tenes en la mano.
--  Y si no puede saberlo, la regla "no se vende un libro usado" es
--  imposible de aplicar.
--
--  Por eso la libreria pega SU PROPIA etiqueta, con un codigo interno.
--  Es lo que hacen las librerias y bibliotecas de verdad. Y resuelve de
--  paso un tercer problema: los libros digitales no tienen tapa, asi que
--  no tienen codigo de barras. Con codigo interno, si.
-- ------------------------------------------------------------
CREATE TABLE ejemplares (
    id             INTEGER PRIMARY KEY,

    -- Lo que dice la etiqueta pegada. V=venta, A=alquiler, D=digital.
    -- UNIQUE: la base NO deja repetir una etiqueta, porque entonces no
    -- se sabria a cual de las dos filas corresponde un escaneo.
    -- COLLATE NOCASE: "V0001" y "v0001" son la misma etiqueta, tanto para
    -- buscar como para el UNIQUE. (SQLite distingue mayusculas si no se
    -- le dice; MySQL no las distinguia.)
    codigo_interno VARCHAR(20) NOT NULL UNIQUE COLLATE NOCASE,

    libro_id       INT NOT NULL,

    tipo           TEXT NOT NULL CHECK (tipo IN ('venta','alquiler','digital')),
    estado         TEXT NOT NULL DEFAULT 'nuevo' CHECK (estado IN ('nuevo','usado')),

    -- DECIMAL y no FLOAT. Un FLOAT guarda 0.1 de forma aproximada y los
    -- centavos se van desfasando al sumar muchas veces. Para plata,
    -- siempre DECIMAL.
    --
    -- OJO CON SQLite: acepta la palabra DECIMAL pero por dentro guarda el
    -- numero como uno con coma comun (REAL), o sea con el mismo problema
    -- del FLOAT. Para los precios de este proyecto no se nota. En un
    -- sistema que mueve plata de verdad sobre SQLite, lo que se hace es
    -- guardar CENTAVOS en un INTEGER: 450.00 se guarda como 45000.
    precio         DECIMAL(8,2) NOT NULL,

    -- LAS DOS CIFRAS DEL STOCK
    --
    --   stock_total       cuantos tiene la libreria en total
    --   stock_disponible  cuantos hay AHORA en el local
    --
    -- Cuando se alquila un libro, baja stock_disponible pero stock_total
    -- queda igual: el libro sigue siendo de la libreria, solo que hoy no
    -- esta. Cuando lo devuelven, stock_disponible vuelve a subir.
    -- Por eso hacen falta las dos y no alcanza con un solo numero.
    --
    -- En venta las dos bajan juntas: lo vendido se fue y no vuelve.
    --
    -- En digital las dos van en NULL. NULL no es cero: cero significa
    -- "se acabo", NULL significa "no corresponde". Un archivo no se
    -- agota nunca, asi que la pregunta "cuantos quedan" no aplica.
    stock_total      INT NULL,
    stock_disponible INT NULL,

    -- FOREIGN KEY: obliga a que libro_id exista de verdad en "libros".
    CONSTRAINT fk_ejemplar_libro
        FOREIGN KEY (libro_id) REFERENCES libros(id),

    -- LA REGLA DEL NEGOCIO, ESCRITA DENTRO DE LA BASE.
    -- Se lee: "o no es de venta, o esta nuevo". O sea: si es de venta,
    -- tiene que estar nuevo. Ponerla aca y no solo en el PHP significa
    -- que se cumple aunque los datos entren desde otro programa que abra
    -- la base (por ejemplo DB Browser for SQLite). La base se defiende sola.
    CONSTRAINT venta_solo_nuevo
        CHECK (tipo <> 'venta' OR estado = 'nuevo'),

    -- Un archivo no se gasta: los digitales son siempre nuevos.
    CONSTRAINT digital_siempre_nuevo
        CHECK (tipo <> 'digital' OR estado = 'nuevo'),

    -- El stock va en NULL si es digital, y con numeros si es fisico.
    CONSTRAINT stock_segun_el_tipo CHECK (
        (tipo =  'digital' AND stock_total IS NULL AND stock_disponible IS NULL)
     OR (tipo <> 'digital' AND stock_total IS NOT NULL AND stock_disponible IS NOT NULL)
    ),

    -- No puede haber mas libros en el local que los que la libreria tiene,
    -- ni cantidades negativas. Errores que de otro modo pasan en silencio.
    CONSTRAINT stock_coherente CHECK (
        stock_total IS NULL
     OR (stock_total >= 0 AND stock_disponible >= 0 AND stock_disponible <= stock_total)
    )
);


-- ------------------------------------------------------------
--  CLIENTES
-- ------------------------------------------------------------
CREATE TABLE clientes (
    id       INTEGER PRIMARY KEY,
    nombre   VARCHAR(60) NOT NULL,
    apellido VARCHAR(60) NOT NULL,

    -- Telefono como TEXTO, igual que el ISBN: lleva espacios, puede
    -- empezar con cero y nunca se suma. No es un numero, es un dato.
    telefono VARCHAR(30) NOT NULL
);


-- ------------------------------------------------------------
--  VENTAS - cada pedido que se cierra en el mostrador
-- ------------------------------------------------------------
--  Una venta es la CABECERA: cuando fue, a quien y cuanto.
--  Lo que se llevo va en ventas_lineas, una fila por articulo.
--
--  Por que en dos tablas y no en una: una venta puede tener 1 articulo
--  o 15. En una sola tabla habria que inventar columnas articulo1,
--  articulo2... y nunca alcanzarian. Con dos tablas, una venta tiene
--  tantas lineas como haga falta. Esto se llama relacion uno a muchos
--  y es de las cosas mas usadas al modelar una base.
-- ------------------------------------------------------------
CREATE TABLE ventas (
    id         INTEGER PRIMARY KEY,

    -- Puede ser NULL: en una venta de mostrador el cliente no siempre da
    -- su nombre, y no hay razon para obligarlo. En un ALQUILER si es
    -- obligatorio, porque hay que saber quien se llevo el libro. Esa
    -- diferencia la controla el PHP al cerrar el pedido.
    cliente_id INT NULL,

    fecha      DATETIME NOT NULL,
    total      DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_venta_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);


CREATE TABLE ventas_lineas (
    id          INTEGER PRIMARY KEY,
    venta_id    INT NOT NULL,
    ejemplar_id INT NOT NULL,

    -- El precio se copia aca y no se lee del ejemplar. Parece repetido
    -- y es a proposito: si manana sube el precio del libro, esta venta
    -- tiene que seguir diciendo lo que se cobro ESE dia. Un comprobante
    -- que cambia solo no sirve como comprobante.
    precio      DECIMAL(8,2) NOT NULL,
    tipo        TEXT NOT NULL CHECK (tipo IN ('venta','alquiler','digital')),

    CONSTRAINT fk_linea_venta    FOREIGN KEY (venta_id)    REFERENCES ventas(id),
    CONSTRAINT fk_linea_ejemplar FOREIGN KEY (ejemplar_id) REFERENCES ejemplares(id)
);


-- ------------------------------------------------------------
--  ALQUILERES - que libro tiene quien, y desde cuando
-- ------------------------------------------------------------
--  El truco esta en fecha_devolucion:
--    NULL  -> el libro TODAVIA esta afuera
--    fecha -> ya lo devolvieron ese dia
--
--  Asi la misma tabla sirve para dos cosas: saber que falta devolver hoy
--  (WHERE fecha_devolucion IS NULL) y tener el historial completo de
--  todo lo que se alquilo alguna vez. No hace falta borrar nada nunca.
-- ------------------------------------------------------------
CREATE TABLE alquileres (
    id               INTEGER PRIMARY KEY,
    ejemplar_id      INT NOT NULL,

    -- Aca NO puede ser NULL: si no se sabe quien se llevo el libro,
    -- no hay a quien reclamarselo.
    cliente_id       INT NOT NULL,

    fecha_salida     DATE NOT NULL,
    fecha_devolucion DATE NULL,

    CONSTRAINT fk_alq_ejemplar FOREIGN KEY (ejemplar_id) REFERENCES ejemplares(id),
    CONSTRAINT fk_alq_cliente  FOREIGN KEY (cliente_id)  REFERENCES clientes(id),

    -- No se puede devolver un libro antes de habérselo llevado.
    CONSTRAINT devolucion_posterior
        CHECK (fecha_devolucion IS NULL OR fecha_devolucion >= fecha_salida)
);


-- ============================================================
--  CATALOGO
-- ============================================================
--  100 titulos reales. Cada uno aparece en los formatos que le tocan:
--  algunos en los tres (venta, alquiler y digital), otros solo en venta
--  y alquiler, otros solo en digital, y asi.
--
--  AVISO SOBRE LOS ISBN: los titulos y los autores son reales, pero los
--  ISBN estan generados para practicar. Tienen el digito de control
--  EAN-13 bien calculado, asi que sirven para probar la validacion, pero
--  NO corresponden a las ediciones de verdad. Si el catalogo se usa en
--  serio, hay que poner el ISBN que trae cada libro en la tapa.
-- ============================================================

INSERT INTO clientes (nombre, apellido, telefono) VALUES
    ('Ana',      'Perez',     '099 111 222'),
    ('Bruno',    'Gimenez',   '098 333 444'),
    ('Carla',    'Rodriguez', '091 555 666'),
    ('Diego',    'Fernandez', '094 777 888'),
    ('Elena',    'Martinez',  '092 999 000'),
    ('Federico', 'Suarez',    '095 222 333'),
    ('Gabriela', 'Lopez',     '096 444 555'),
    ('Hugo',     'Silva',     '097 666 777');

INSERT INTO libros (isbn, nombre, autor) VALUES
    ('9788400000004', 'Cien anos de soledad', 'Gabriel Garcia Marquez'),
    ('9788401379116', 'El amor en los tiempos del colera', 'Gabriel Garcia Marquez'),
    ('9788402758224', 'Cronica de una muerte anunciada', 'Gabriel Garcia Marquez'),
    ('9788404137331', 'El coronel no tiene quien le escriba', 'Gabriel Garcia Marquez'),
    ('9788405516449', 'Don Quijote de la Mancha', 'Miguel de Cervantes'),
    ('9788406895550', 'Rayuela', 'Julio Cortazar'),
    (NULL, 'Bestiario', 'Julio Cortazar'),
    ('9788409653775', 'Final del juego', 'Julio Cortazar'),
    ('9788411032889', 'Ficciones', 'Jorge Luis Borges'),
    ('9788412411997', 'El Aleph', 'Jorge Luis Borges'),
    ('9788413791104', 'La casa de los espiritus', 'Isabel Allende'),
    ('9788415170211', 'Paula', 'Isabel Allende'),
    ('9788416549320', 'De amor y de sombra', 'Isabel Allende'),
    ('9788417928438', 'Pedro Paramo', 'Juan Rulfo'),
    ('9788419307545', 'El llano en llamas', 'Juan Rulfo'),
    ('9788420686653', 'La ciudad y los perros', 'Mario Vargas Llosa'),
    (NULL, 'La fiesta del Chivo', 'Mario Vargas Llosa'),
    ('9788423444878', 'Conversacion en La Catedral', 'Mario Vargas Llosa'),
    ('9788424823986', 'El tunel', 'Ernesto Sabato'),
    ('9788426203090', 'Sobre heroes y tumbas', 'Ernesto Sabato'),
    ('9788427582200', 'Martin Fierro', 'Jose Hernandez'),
    ('9788428961318', 'Facundo', 'Domingo Faustino Sarmiento'),
    ('9788430340422', 'La tregua', 'Mario Benedetti'),
    ('9788431719531', 'Gracias por el fuego', 'Mario Benedetti'),
    ('9788433098641', 'Montevideanos', 'Mario Benedetti'),
    ('9788434477759', 'La borra del cafe', 'Mario Benedetti'),
    (NULL, 'Las venas abiertas de America Latina', 'Eduardo Galeano'),
    ('9788437235974', 'El libro de los abrazos', 'Eduardo Galeano'),
    ('9788438615089', 'Memoria del fuego', 'Eduardo Galeano'),
    ('9788439994190', 'Cuentos de amor, de locura y de muerte', 'Horacio Quiroga'),
    ('9788441373303', 'Cuentos de la selva', 'Horacio Quiroga'),
    ('9788442752411', 'El pozo', 'Juan Carlos Onetti'),
    ('9788444131528', 'La vida breve', 'Juan Carlos Onetti'),
    ('9788445510636', 'El astillero', 'Juan Carlos Onetti'),
    ('9788446889748', 'Ariel', 'Jose Enrique Rodo'),
    ('9788448268855', 'Tala', 'Gabriela Mistral'),
    (NULL, 'Veinte poemas de amor y una cancion desesperada', 'Pablo Neruda'),
    ('9788451027074', 'Canto general', 'Pablo Neruda'),
    ('9788452406182', 'Confieso que he vivido', 'Pablo Neruda'),
    ('9788453785293', 'Romancero gitano', 'Federico Garcia Lorca'),
    ('9788455164409', 'La casa de Bernarda Alba', 'Federico Garcia Lorca'),
    ('9788456543517', 'Bodas de sangre', 'Federico Garcia Lorca'),
    ('9788457922625', 'Yerma', 'Federico Garcia Lorca'),
    ('9788459301732', 'La colmena', 'Camilo Jose Cela'),
    ('9788460680840', 'La familia de Pascual Duarte', 'Camilo Jose Cela'),
    ('9788462059958', 'Nada', 'Carmen Laforet'),
    (NULL, 'Los santos inocentes', 'Miguel Delibes'),
    ('9788464818171', 'El camino', 'Miguel Delibes'),
    ('9788466197281', 'La sombra del viento', 'Carlos Ruiz Zafon'),
    ('9788467576399', 'El juego del angel', 'Carlos Ruiz Zafon'),
    ('9788468955506', 'Marina', 'Carlos Ruiz Zafon'),
    ('9788470334610', 'El Lazarillo de Tormes', 'Anonimo'),
    ('9788471713728', 'La Celestina', 'Fernando de Rojas'),
    ('9788473092838', 'Fuenteovejuna', 'Lope de Vega'),
    ('9788474471946', 'La vida es sueno', 'Pedro Calderon de la Barca'),
    ('9788475851051', 'Platero y yo', 'Juan Ramon Jimenez'),
    (NULL, 'Niebla', 'Miguel de Unamuno'),
    ('9788478609277', 'San Manuel Bueno, martir', 'Miguel de Unamuno'),
    ('9788479988388', 'El arbol de la ciencia', 'Pio Baroja'),
    ('9788481367492', 'Campos de Castilla', 'Antonio Machado'),
    ('9788482746609', 'Orgullo y prejuicio', 'Jane Austen'),
    ('9788484125716', 'Sentido y sensibilidad', 'Jane Austen'),
    ('9788485504824', 'Emma', 'Jane Austen'),
    ('9788486883935', 'Jane Eyre', 'Charlotte Bronte'),
    ('9788488263049', 'Cumbres borrascosas', 'Emily Bronte'),
    ('9788489642157', 'Grandes esperanzas', 'Charles Dickens'),
    (NULL, 'Oliver Twist', 'Charles Dickens'),
    ('9788492400379', 'Cuento de Navidad', 'Charles Dickens'),
    ('9788493779481', 'Los miserables', 'Victor Hugo'),
    ('9788495158598', 'Nuestra Senora de Paris', 'Victor Hugo'),
    ('9788496537705', 'El conde de Montecristo', 'Alexandre Dumas'),
    ('9788497916813', 'Los tres mosqueteros', 'Alexandre Dumas'),
    ('9788499295923', 'Madame Bovary', 'Gustave Flaubert'),
    ('9788500675034', 'El principito', 'Antoine de Saint-Exupery'),
    ('9788502054141', 'Veinte mil leguas de viaje submarino', 'Julio Verne'),
    ('9788503433259', 'La vuelta al mundo en ochenta dias', 'Julio Verne'),
    (NULL, 'Viaje al centro de la Tierra', 'Julio Verne'),
    ('9788506191477', 'Crimen y castigo', 'Fiodor Dostoievski'),
    ('9788507570585', 'Los hermanos Karamazov', 'Fiodor Dostoievski'),
    ('9788508949694', 'Ana Karenina', 'Leon Tolstoi'),
    ('9788510328807', 'Guerra y paz', 'Leon Tolstoi'),
    ('9788511707915', 'La metamorfosis', 'Franz Kafka'),
    ('9788513087022', 'El proceso', 'Franz Kafka'),
    ('9788514466130', 'El viejo y el mar', 'Ernest Hemingway'),
    ('9788515845248', 'Por quien doblan las campanas', 'Ernest Hemingway'),
    ('9788517224355', '1984', 'George Orwell'),
    (NULL, 'Rebelion en la granja', 'George Orwell'),
    ('9788519982574', 'Un mundo feliz', 'Aldous Huxley'),
    ('9788521361688', 'Fahrenheit 451', 'Ray Bradbury'),
    ('9788522740796', 'Cronicas marcianas', 'Ray Bradbury'),
    ('9788524119903', 'El guardian entre el centeno', 'J. D. Salinger'),
    ('9788525499011', 'Matar a un ruisenor', 'Harper Lee'),
    ('9788526878129', 'El senor de las moscas', 'William Golding'),
    ('9788528257236', 'El Hobbit', 'J. R. R. Tolkien'),
    ('9788529636344', 'El senor de los anillos', 'J. R. R. Tolkien'),
    ('9788531015458', 'Dracula', 'Bram Stoker'),
    (NULL, 'Frankenstein', 'Mary Shelley'),
    ('9788533773677', 'La isla del tesoro', 'Robert Louis Stevenson'),
    ('9788535152784', 'El retrato de Dorian Gray', 'Oscar Wilde'),
    ('9788536531892', 'Diario de Ana Frank', 'Ana Frank');

-- LAS ETIQUETAS IMPRESAS
-- La mayoria de los codigos son V0001, A0001, D0001... Pero cuatro ejemplares
-- de venta llevan el numero de las etiquetas que ya estan impresas y pegadas
-- en los libros de verdad (codigos de barras EAN-13, que el lector lee):
--    9789974000018  El principito         (era V0060)
--    9789974000025  Rayuela               (era V0006)
--    9789974000032  La tregua             (era V0019)
--    9789974000049  Cuentos de la selva   (era V0025)
-- Para el sistema no hay ninguna diferencia: el codigo interno es TEXTO y
-- puede ser cualquier cosa, mientras no se repita. Lo que importa es que lo
-- que esta impreso en la etiqueta sea exactamente lo que esta en la base.
INSERT INTO ejemplares (codigo_interno, libro_id, tipo, estado, precio, stock_total, stock_disponible) VALUES
    ('V0001', 1, 'venta', 'nuevo', 450.00, 2, 2),
    ('A0001', 1, 'alquiler', 'nuevo', 70.00, 1, 0),
    ('D0001', 1, 'digital', 'nuevo', 270.00, NULL, NULL),
    ('V0002', 2, 'venta', 'nuevo', 620.00, 9, 9),
    ('A0002', 2, 'alquiler', 'usado', 90.00, 4, 2),
    ('D0002', 2, 'digital', 'nuevo', 370.00, NULL, NULL),
    ('V0003', 3, 'venta', 'nuevo', 800.00, 5, 5),
    ('A0003', 3, 'alquiler', 'usado', 120.00, 1, 1),
    ('D0003', 3, 'digital', 'nuevo', 480.00, NULL, NULL),
    ('V0004', 4, 'venta', 'nuevo', 970.00, 12, 12),
    ('A0004', 4, 'alquiler', 'usado', 150.00, 4, 4),
    ('D0004', 4, 'digital', 'nuevo', 580.00, NULL, NULL),
    ('V0005', 5, 'venta', 'nuevo', 1140.00, 8, 8),
    ('A0005', 5, 'alquiler', 'usado', 170.00, 1, 1),
    ('9789974000025', 6, 'venta', 'nuevo', 1320.00, 4, 4),
    ('A0006', 6, 'alquiler', 'usado', 200.00, 4, 2),
    ('D0005', 7, 'digital', 'nuevo', 890.00, NULL, NULL),
    ('V0007', 8, 'venta', 'nuevo', 1660.00, 7, 7),
    ('V0008', 9, 'venta', 'nuevo', 530.00, 3, 3),
    ('A0007', 10, 'alquiler', 'usado', 110.00, 4, 2),
    ('V0009', 11, 'venta', 'nuevo', 880.00, 6, 6),
    ('A0008', 11, 'alquiler', 'usado', 130.00, 1, 1),
    ('D0006', 11, 'digital', 'nuevo', 530.00, NULL, NULL),
    ('V0010', 12, 'venta', 'nuevo', 1050.00, 2, 2),
    ('A0009', 12, 'alquiler', 'usado', 160.00, 4, 0),
    ('D0007', 12, 'digital', 'nuevo', 630.00, NULL, NULL),
    ('V0011', 13, 'venta', 'nuevo', 1230.00, 9, 9),
    ('A0010', 13, 'alquiler', 'usado', 180.00, 1, 1),
    ('D0008', 13, 'digital', 'nuevo', 740.00, NULL, NULL),
    ('V0012', 14, 'venta', 'nuevo', 1400.00, 5, 5),
    ('A0011', 14, 'alquiler', 'usado', 210.00, 4, 2),
    ('D0009', 14, 'digital', 'nuevo', 840.00, NULL, NULL),
    ('V0013', 15, 'venta', 'nuevo', 1570.00, 12, 12),
    ('A0012', 15, 'alquiler', 'nuevo', 240.00, 1, 1),
    ('V0014', 16, 'venta', 'nuevo', 1740.00, 8, 8),
    ('A0013', 16, 'alquiler', 'usado', 260.00, 4, 4),
    ('D0010', 17, 'digital', 'nuevo', 370.00, NULL, NULL),
    ('V0015', 18, 'venta', 'nuevo', 790.00, 11, 11),
    ('V0016', 19, 'venta', 'nuevo', 960.00, 7, 7),
    ('A0014', 20, 'alquiler', 'usado', 170.00, 4, 4),
    ('V0017', 21, 'venta', 'nuevo', 1310.00, 10, 10),
    ('A0015', 21, 'alquiler', 'usado', 200.00, 1, 1),
    ('D0011', 21, 'digital', 'nuevo', 790.00, NULL, NULL),
    ('V0018', 22, 'venta', 'nuevo', 1480.00, 6, 6),
    ('A0016', 22, 'alquiler', 'nuevo', 220.00, 4, 2),
    ('D0012', 22, 'digital', 'nuevo', 890.00, NULL, NULL),
    ('9789974000032', 23, 'venta', 'nuevo', 1660.00, 2, 2),
    ('A0017', 23, 'alquiler', 'usado', 250.00, 1, 0),
    ('D0013', 23, 'digital', 'nuevo', 1000.00, NULL, NULL),
    ('V0020', 24, 'venta', 'nuevo', 530.00, 9, 9),
    ('A0018', 24, 'alquiler', 'usado', 80.00, 4, 4),
    ('D0014', 24, 'digital', 'nuevo', 320.00, NULL, NULL),
    ('V0021', 25, 'venta', 'nuevo', 700.00, 5, 5),
    ('A0019', 25, 'alquiler', 'usado', 100.00, 1, 1),
    ('V0022', 26, 'venta', 'nuevo', 880.00, 12, 12),
    ('A0020', 26, 'alquiler', 'usado', 130.00, 4, 2),
    ('D0015', 27, 'digital', 'nuevo', 630.00, NULL, NULL),
    ('V0023', 28, 'venta', 'nuevo', 1220.00, 4, 4),
    ('V0024', 29, 'venta', 'nuevo', 1390.00, 11, 11),
    ('A0021', 30, 'alquiler', 'usado', 240.00, 4, 2),
    ('9789974000049', 31, 'venta', 'nuevo', 1740.00, 3, 3),
    ('A0022', 31, 'alquiler', 'usado', 260.00, 1, 1),
    ('D0016', 31, 'digital', 'nuevo', 1040.00, NULL, NULL),
    ('V0026', 32, 'venta', 'nuevo', 610.00, 10, 10),
    ('A0023', 32, 'alquiler', 'usado', 90.00, 4, 4),
    ('D0017', 32, 'digital', 'nuevo', 370.00, NULL, NULL),
    ('V0027', 33, 'venta', 'nuevo', 790.00, 6, 6),
    ('A0024', 33, 'alquiler', 'usado', 120.00, 1, 1),
    ('D0018', 33, 'digital', 'nuevo', 470.00, NULL, NULL),
    ('V0028', 34, 'venta', 'nuevo', 960.00, 2, 2),
    ('A0025', 34, 'alquiler', 'usado', 140.00, 4, 0),
    ('D0019', 34, 'digital', 'nuevo', 580.00, NULL, NULL),
    ('V0029', 35, 'venta', 'nuevo', 1130.00, 9, 9),
    ('A0026', 35, 'alquiler', 'usado', 170.00, 1, 1),
    ('V0030', 36, 'venta', 'nuevo', 1300.00, 5, 5),
    ('A0027', 36, 'alquiler', 'nuevo', 200.00, 4, 4),
    ('D0020', 37, 'digital', 'nuevo', 890.00, NULL, NULL),
    ('V0031', 38, 'venta', 'nuevo', 1650.00, 8, 8),
    ('V0032', 39, 'venta', 'nuevo', 520.00, 4, 4),
    ('A0028', 40, 'alquiler', 'usado', 100.00, 4, 4),
    ('V0033', 41, 'venta', 'nuevo', 870.00, 7, 7),
    ('A0029', 41, 'alquiler', 'usado', 130.00, 1, 1),
    ('D0021', 41, 'digital', 'nuevo', 520.00, NULL, NULL),
    ('V0034', 42, 'venta', 'nuevo', 1040.00, 3, 3),
    ('A0030', 42, 'alquiler', 'usado', 160.00, 4, 2),
    ('D0022', 42, 'digital', 'nuevo', 620.00, NULL, NULL),
    ('V0035', 43, 'venta', 'nuevo', 1220.00, 10, 10),
    ('A0031', 43, 'alquiler', 'nuevo', 180.00, 1, 1),
    ('D0023', 43, 'digital', 'nuevo', 730.00, NULL, NULL),
    ('V0036', 44, 'venta', 'nuevo', 1390.00, 6, 6),
    ('A0032', 44, 'alquiler', 'usado', 210.00, 4, 4),
    ('D0024', 44, 'digital', 'nuevo', 830.00, NULL, NULL),
    ('V0037', 45, 'venta', 'nuevo', 1560.00, 2, 2),
    ('A0033', 45, 'alquiler', 'usado', 230.00, 1, 0),
    ('V0038', 46, 'venta', 'nuevo', 1740.00, 9, 9),
    ('A0034', 46, 'alquiler', 'usado', 260.00, 4, 2),
    ('D0025', 47, 'digital', 'nuevo', 370.00, NULL, NULL),
    ('V0039', 48, 'venta', 'nuevo', 780.00, 12, 12),
    ('V0040', 49, 'venta', 'nuevo', 950.00, 8, 8),
    ('A0035', 50, 'alquiler', 'nuevo', 170.00, 4, 2),
    ('V0041', 51, 'venta', 'nuevo', 1300.00, 11, 11),
    ('A0036', 51, 'alquiler', 'usado', 200.00, 1, 1),
    ('D0026', 51, 'digital', 'nuevo', 780.00, NULL, NULL),
    ('V0042', 52, 'venta', 'nuevo', 1470.00, 7, 7),
    ('A0037', 52, 'alquiler', 'usado', 220.00, 4, 4),
    ('D0027', 52, 'digital', 'nuevo', 880.00, NULL, NULL),
    ('V0043', 53, 'venta', 'nuevo', 1650.00, 3, 3),
    ('A0038', 53, 'alquiler', 'usado', 250.00, 1, 1),
    ('D0028', 53, 'digital', 'nuevo', 990.00, NULL, NULL),
    ('V0044', 54, 'venta', 'nuevo', 520.00, 10, 10),
    ('A0039', 54, 'alquiler', 'usado', 80.00, 4, 2),
    ('D0029', 54, 'digital', 'nuevo', 310.00, NULL, NULL),
    ('V0045', 55, 'venta', 'nuevo', 690.00, 6, 6),
    ('A0040', 55, 'alquiler', 'usado', 100.00, 1, 1),
    ('V0046', 56, 'venta', 'nuevo', 860.00, 2, 2),
    ('A0041', 56, 'alquiler', 'usado', 130.00, 4, 0),
    ('D0030', 57, 'digital', 'nuevo', 620.00, NULL, NULL),
    ('V0047', 58, 'venta', 'nuevo', 1210.00, 5, 5),
    ('V0048', 59, 'venta', 'nuevo', 1380.00, 12, 12),
    ('A0042', 60, 'alquiler', 'usado', 230.00, 4, 4),
    ('V0049', 61, 'venta', 'nuevo', 1730.00, 4, 4),
    ('A0043', 61, 'alquiler', 'usado', 260.00, 1, 1),
    ('D0031', 61, 'digital', 'nuevo', 1040.00, NULL, NULL),
    ('V0050', 62, 'venta', 'nuevo', 600.00, 11, 11),
    ('A0044', 62, 'alquiler', 'usado', 90.00, 4, 2),
    ('D0032', 62, 'digital', 'nuevo', 360.00, NULL, NULL),
    ('V0051', 63, 'venta', 'nuevo', 780.00, 7, 7),
    ('A0045', 63, 'alquiler', 'usado', 120.00, 1, 1),
    ('D0033', 63, 'digital', 'nuevo', 470.00, NULL, NULL),
    ('V0052', 64, 'venta', 'nuevo', 950.00, 3, 3),
    ('A0046', 64, 'alquiler', 'nuevo', 140.00, 4, 4),
    ('D0034', 64, 'digital', 'nuevo', 570.00, NULL, NULL),
    ('V0053', 65, 'venta', 'nuevo', 1120.00, 10, 10),
    ('A0047', 65, 'alquiler', 'usado', 170.00, 1, 1),
    ('V0054', 66, 'venta', 'nuevo', 1300.00, 6, 6),
    ('A0048', 66, 'alquiler', 'usado', 200.00, 4, 2),
    ('D0035', 67, 'digital', 'nuevo', 880.00, NULL, NULL),
    ('V0055', 68, 'venta', 'nuevo', 1640.00, 9, 9),
    ('V0056', 69, 'venta', 'nuevo', 510.00, 5, 5),
    ('A0049', 70, 'alquiler', 'usado', 100.00, 4, 2),
    ('V0057', 71, 'venta', 'nuevo', 860.00, 8, 8),
    ('A0050', 71, 'alquiler', 'nuevo', 130.00, 1, 1),
    ('D0036', 71, 'digital', 'nuevo', 520.00, NULL, NULL),
    ('V0058', 72, 'venta', 'nuevo', 1030.00, 4, 4),
    ('A0051', 72, 'alquiler', 'usado', 150.00, 4, 4),
    ('D0037', 72, 'digital', 'nuevo', 620.00, NULL, NULL),
    ('V0059', 73, 'venta', 'nuevo', 1210.00, 11, 11),
    ('A0052', 73, 'alquiler', 'usado', 180.00, 1, 1),
    ('D0038', 73, 'digital', 'nuevo', 730.00, NULL, NULL),
    ('9789974000018', 74, 'venta', 'nuevo', 1380.00, 7, 7),
    ('A0053', 74, 'alquiler', 'usado', 210.00, 4, 2),
    ('D0039', 74, 'digital', 'nuevo', 830.00, NULL, NULL),
    ('V0061', 75, 'venta', 'nuevo', 1550.00, 3, 3),
    ('A0054', 75, 'alquiler', 'usado', 230.00, 1, 1),
    ('V0062', 76, 'venta', 'nuevo', 1720.00, 10, 10),
    ('A0055', 76, 'alquiler', 'usado', 260.00, 4, 4),
    ('D0040', 77, 'digital', 'nuevo', 360.00, NULL, NULL),
    ('V0063', 78, 'venta', 'nuevo', 770.00, 2, 2),
    ('V0064', 79, 'venta', 'nuevo', 940.00, 9, 9),
    ('A0056', 80, 'alquiler', 'usado', 170.00, 4, 4),
    ('V0065', 81, 'venta', 'nuevo', 1290.00, 12, 12),
    ('A0057', 81, 'alquiler', 'usado', 190.00, 1, 1),
    ('D0041', 81, 'digital', 'nuevo', 770.00, NULL, NULL),
    ('V0066', 82, 'venta', 'nuevo', 1460.00, 8, 8),
    ('A0058', 82, 'alquiler', 'usado', 220.00, 4, 2),
    ('D0042', 82, 'digital', 'nuevo', 880.00, NULL, NULL),
    ('V0067', 83, 'venta', 'nuevo', 1640.00, 4, 4),
    ('A0059', 83, 'alquiler', 'usado', 250.00, 1, 1),
    ('D0043', 83, 'digital', 'nuevo', 980.00, NULL, NULL),
    ('V0068', 84, 'venta', 'nuevo', 510.00, 11, 11),
    ('A0060', 84, 'alquiler', 'usado', 80.00, 4, 4),
    ('D0044', 84, 'digital', 'nuevo', 310.00, NULL, NULL),
    ('V0069', 85, 'venta', 'nuevo', 680.00, 7, 7),
    ('A0061', 85, 'alquiler', 'nuevo', 100.00, 1, 1),
    ('V0070', 86, 'venta', 'nuevo', 860.00, 3, 3),
    ('A0062', 86, 'alquiler', 'usado', 130.00, 4, 2),
    ('D0045', 87, 'digital', 'nuevo', 620.00, NULL, NULL),
    ('V0071', 88, 'venta', 'nuevo', 1200.00, 6, 6),
    ('V0072', 89, 'venta', 'nuevo', 1370.00, 2, 2),
    ('A0063', 90, 'alquiler', 'usado', 230.00, 4, 2),
    ('V0073', 91, 'venta', 'nuevo', 1720.00, 5, 5),
    ('A0064', 91, 'alquiler', 'usado', 260.00, 1, 1),
    ('D0046', 91, 'digital', 'nuevo', 1030.00, NULL, NULL),
    ('V0074', 92, 'venta', 'nuevo', 590.00, 12, 12),
    ('A0065', 92, 'alquiler', 'nuevo', 90.00, 4, 4),
    ('D0047', 92, 'digital', 'nuevo', 350.00, NULL, NULL),
    ('V0075', 93, 'venta', 'nuevo', 770.00, 8, 8),
    ('A0066', 93, 'alquiler', 'usado', 120.00, 1, 1),
    ('D0048', 93, 'digital', 'nuevo', 460.00, NULL, NULL),
    ('V0076', 94, 'venta', 'nuevo', 940.00, 4, 4),
    ('A0067', 94, 'alquiler', 'usado', 140.00, 4, 2),
    ('D0049', 94, 'digital', 'nuevo', 560.00, NULL, NULL),
    ('V0077', 95, 'venta', 'nuevo', 1110.00, 11, 11),
    ('A0068', 95, 'alquiler', 'usado', 170.00, 1, 1),
    ('V0078', 96, 'venta', 'nuevo', 1280.00, 7, 7),
    ('A0069', 96, 'alquiler', 'usado', 190.00, 4, 4),
    ('D0050', 97, 'digital', 'nuevo', 880.00, NULL, NULL),
    ('V0079', 98, 'venta', 'nuevo', 1630.00, 10, 10),
    ('V0080', 99, 'venta', 'nuevo', 500.00, 6, 6),
    ('A0070', 100, 'alquiler', 'usado', 100.00, 4, 0);

-- Quien tiene cada libro que hoy no esta en el local.
-- Hay exactamente una fila por cada ejemplar que falta del stock.
INSERT INTO alquileres (ejemplar_id, cliente_id, fecha_salida, fecha_devolucion) VALUES
    (2, 1, '2026-09-12', NULL),
    (5, 2, '2026-09-07', NULL),
    (5, 5, '2026-08-31', NULL),
    (16, 6, '2026-08-18', NULL),
    (16, 1, '2026-08-11', NULL),
    (20, 2, '2026-09-05', NULL),
    (20, 5, '2026-08-29', NULL),
    (25, 4, '2026-08-26', NULL),
    (25, 7, '2026-08-19', NULL),
    (25, 2, '2026-08-12', NULL),
    (25, 5, '2026-09-12', NULL),
    (31, 6, '2026-08-16', NULL),
    (31, 1, '2026-08-09', NULL),
    (45, 6, '2026-08-14', NULL),
    (45, 1, '2026-08-07', NULL),
    (48, 7, '2026-08-09', NULL),
    (56, 2, '2026-09-01', NULL),
    (56, 5, '2026-08-25', NULL),
    (60, 6, '2026-08-12', NULL),
    (60, 1, '2026-09-12', NULL),
    (71, 2, '2026-08-30', NULL),
    (71, 5, '2026-08-23', NULL),
    (71, 8, '2026-08-16', NULL),
    (71, 3, '2026-08-09', NULL),
    (85, 2, '2026-08-28', NULL),
    (85, 5, '2026-08-21', NULL),
    (94, 5, '2026-08-13', NULL),
    (96, 6, '2026-08-08', NULL),
    (96, 1, '2026-09-08', NULL),
    (100, 2, '2026-08-26', NULL),
    (100, 5, '2026-08-19', NULL),
    (111, 6, '2026-08-06', NULL),
    (111, 1, '2026-09-06', NULL),
    (116, 8, '2026-09-03', NULL),
    (116, 3, '2026-08-27', NULL),
    (116, 6, '2026-08-20', NULL),
    (116, 1, '2026-08-13', NULL),
    (125, 6, '2026-09-11', NULL),
    (125, 1, '2026-09-04', NULL),
    (136, 2, '2026-08-22', NULL),
    (136, 5, '2026-08-15', NULL),
    (140, 6, '2026-09-09', NULL),
    (140, 1, '2026-09-02', NULL),
    (151, 2, '2026-08-20', NULL),
    (151, 5, '2026-08-13', NULL),
    (165, 2, '2026-08-18', NULL),
    (165, 5, '2026-08-11', NULL),
    (176, 6, '2026-09-05', NULL),
    (176, 1, '2026-08-29', NULL),
    (180, 2, '2026-08-16', NULL),
    (180, 5, '2026-08-09', NULL),
    (191, 6, '2026-09-03', NULL),
    (191, 1, '2026-08-27', NULL),
    (200, 4, '2026-09-11', NULL),
    (200, 7, '2026-09-04', NULL),
    (200, 2, '2026-08-28', NULL),
    (200, 5, '2026-08-21', NULL);
