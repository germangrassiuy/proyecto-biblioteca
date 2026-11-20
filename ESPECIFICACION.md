# Proyecto libreria - especificacion

Documento vivo. German va anotando pedidos, aca se ordenan.
Ultima actualizacion: 14/09/2026.

---

## 1. Restricciones del proyecto

- **Stack permitido: HTML, CSS, PHP y SQL.** Es una consigna pedagogica: los
  alumnos no pueden usar otra cosa.
- **JavaScript solo para hablar con el Arduino** (y, desde el 20/11, una linea
  que deja el cursor en el campo de escaneo: el `autofocus` de HTML el
  navegador lo aplica una sola vez por pagina y el iframe se recarga en cada
  escaneo; explicado en `escaneo.php`), el minimo indispensable, y
  solo cuando sea extremadamente necesario. El porque esta explicado en
  `explicaciones/01-php-javascript-y-el-arduino.md`.
- **Los presupuestos salen del proyecto.** Las guias en PDF los traian; ya no
  forman parte del alcance.
- **Destinatario final: los alumnos.** Todo lleva explicacion del porque, no
  solo del que.

## 2. Hardware disponible

| Pieza | Estado |
|---|---|
| Lector ZGB-J43-NE, 1D, USB, tipo teclado (HID) | Probado y funcionando |
| Arduino Uno clon CH340, en COM4 | Conectado, programa cargado |
| LCD 16x2 I2C (SDA a A4, SCL a A5) | Conectada y probada |
| LED verde | Pin 12 (recableado 19/11/2026) — **probado, enciende** |
| LED rojo | Pin 10 (recableado 19/11/2026) — **probado, enciende** |
| Zumbador piezo | Pin 7 (recableado 19/11/2026) |
| HC-08, TXD | Pin 2 (antes 10: ahi quedo el LED rojo) |
| Modulo Bluetooth HC-08 (BLE) | Probado: la PC le manda y el Arduino muestra |
| Transformador de 5 V 2 A | Alimenta la placa **por el conector USB** |

### La limitacion del unico puerto USB: resuelta (16/09/2026)

Antes el Arduino ocupaba el unico USB libre, el lector quedaba desenchufado y
se lo simulaba tipeando el codigo a mano. Eso sigue siendo valido como
simulacion — el lector es HID teclado, y para el software un codigo tipeado y
uno escaneado son **identicos** —, pero ya no es necesario:

- El Arduino recibe los datos por el **modulo HC-08** y se alimenta de un
  transformador de 5 V. **El USB de la PC queda libre para el lector.**
- El transformador entra por el **conector USB de la placa**, no por el jack
  redondo: ese pasa por el regulador, que con 5 V de entrada deja el riel en
  unos 3,7 V. El ATmega a 16 MHz necesita 4 V como minimo, asi que funciona a
  medias y falla de a ratos, que es peor que no funcionar.
- El HC-08 es **BLE**, no Bluetooth clasico: no tiene perfil SPP y por lo tanto
  **no crea un puerto COM virtual**. Del lado del navegador hay que hablarle con
  **Web Bluetooth** en vez de Web Serial. Es la misma excepcion de stack que ya
  tiene `arduino.js`: JavaScript minimo y solo para el hardware.
- Los datos del modulo (direccion, canal FFE1) estan en la memoria del proyecto.

## 3. Modelo de datos

| Tabla | Campos |
|---|---|
| `libros` | id, isbn, nombre, autor |
| `ejemplares` | id, codigo_interno, libro_id, tipo, estado, precio |
| `clientes` | id, nombre, apellido, telefono |

- `tipo` es `venta`, `alquiler` o `digital`.
- `estado` es `nuevo` o `usado`.
- **Regla: no se puede vender un libro usado.** Esta escrita como restriccion
  dentro de la base (`CHECK`), no solo en el PHP.
- **Lo que se escanea es un codigo interno propio de la libreria**, uno por
  ejemplar, no el ISBN de la tapa. El razonamiento completo esta en
  `explicaciones/` y comentado dentro de `biblioteca.sql`.
- Los ejemplares de venta y los de alquiler **no son los mismos**: son filas
  distintas, con etiquetas distintas, aunque compartan el titulo.

## 4. Los dos modos del lector

### Modo scanner
Al escanear, muestra **el precio y los datos del producto**, en dos lugares:
el panel LCD del Arduino y la pantalla de la PC. Nada mas: no acumula.

### Modo venta
Al escanear, muestra **lo mismo** que en modo scanner, pero ademas **va sumando
a escondidas**.

- Si pasan **3 segundos sin escanear nada**, muestra en pantalla el **total de
  la compra**.
- Al **cerrar un pedido**, la suma vuelve a **0** y queda lista para una venta
  nueva.
- Si un libro **no se encuentra**, el total **no se resetea**: sigue apareciendo
  normalmente a los 3 segundos.

## 5. Señales del Arduino

| Situacion | Pin | LED | Pantalla |
|---|---|---|---|
| El codigo existe en la base | 12 | Verde | Datos del producto y precio |
| El codigo no existe | 10 | Rojo | Cartel "no encontrado" |

## 6. Como se resuelven los 3 segundos sin JavaScript

El tiempo se mide en dos lugares distintos, y ninguno necesita JavaScript:

- **En la pantalla de la PC**: con `<meta http-equiv="refresh" content="3;...">`,
  que es HTML puro. Si se escanea otra cosa antes, la pagina se recarga y el
  reloj arranca de nuevo solo.
- **En el LCD del Arduino**: con `millis()` dentro del propio programa del
  Arduino, que ya esta corriendo todo el tiempo.

El total en si se guarda del lado de PHP, en la **sesion** (`$_SESSION`). Es lo
que permite que la suma sobreviva de una pagina a la otra sin que el usuario la
vea: eso es el "a escondidas" del pedido.

---

## 7. Pendientes de definir

Anotados para resolver mas adelante, no frenan el trabajo:

1. **Alquiler en modo venta.** Si se escanea un ejemplar de alquiler estando en
   modo venta, ¿se bloquea con un aviso, o se cobra como alquiler?
2. **Cerrar el pedido.** ¿Con un boton en pantalla? ¿La venta queda guardada en
   una tabla, o solo se limpia el total?
3. **El cliente en la venta.** ¿Hay que elegir un cliente antes de cobrar, o los
   clientes son solo una agenda aparte?
4. **Stock.** ¿Un ejemplar vendido desaparece del catalogo o queda marcado?
   ¿Los digitales se pueden vender infinitas veces?
5. **Devolucion de alquileres.** ¿Entra en el alcance o queda para despues?

## 8. El pendiente tecnico: resuelto y medido

Cada escaneo recarga la pagina, y eso cerraba la conexion con el Arduino.
**Se midio** cuanto costaba reabrirla:

```
DTR activado    -> READY a los 1,97 s  => la placa SE REINICIA
DTR desactivado -> READY a los 1,97 s  => se reinicia IGUAL
```

Casi 2 segundos por libro escaneado, y el truco de desactivar DTR no sirvio.

**Solucion aplicada:** partir la pantalla en dos. `mostrador.php` se carga una
sola vez y no se recarga nunca: ahi vive la conexion al Arduino. Adentro tiene
un `<iframe>` con `escaneo.php`, y ese es el unico que se recarga. El puerto se
abre una vez por jornada y la placa se reinicia una sola vez.

Precio a pagar: los enlaces de la pagina de afuera llevan `target="_blank"`,
porque si navegaran en la misma ventana cerrarian el puerto.

Explicado en `explicaciones/02-recarga-y-puerto-serie.md`.

---

## 9. Cuentas, catalogo y stock

### Dos roles, dos paneles
| Rol | Que ve |
|---|---|
| `admin` | Mostrador, catalogo, libros, ejemplares, clientes |
| `cliente` | Solamente el catalogo |

No se resuelve escondiendo botones: **cada pagina de admin vuelve a
preguntar quien entro** (`soloAdmin()` en `db.php`). Verificado: un cliente
que escribe la direccion a mano de las 5 pantallas de admin rebota al
catalogo en las 5.

Las contrasenias se guardan como hash de bcrypt, nunca en texto plano.
Cuentas de prueba: `admin`/`admin123` y `cliente`/`cliente123`.

### El catalogo del cliente
Un libro aparece **una sola vez**, con un tag por cada formato en que se
consigue. Se resuelve con `GROUP BY` mas `MAX(CASE WHEN tipo=... )`, que
convierte "una fila por formato" en "una columna por formato".

Reparto de los 100 titulos: 40 en los tres formatos, 20 en venta y
alquiler, 20 solo venta, 10 solo alquiler, 10 solo digital.

Los tags de un formato sin stock se muestran apagados, no se esconden: al
cliente le sirve saber que el libro existe en ese formato.

### El stock, dos cifras
`stock_total` es cuantos tiene la libreria. `stock_disponible` es cuantos
hay ahora en el local. Al alquilar baja el disponible y el total queda
igual; al devolver, vuelve a subir. En venta bajan las dos. En digital las
dos van en NULL, porque un archivo no se agota — y NULL no es cero: cero
es "se acabo", NULL es "no corresponde".

### Las tapas
En la base va el **nombre del archivo**, no la imagen: una base con fotos
adentro se vuelve enorme y cada copia de seguridad pesa gigabytes. Las
fotos van en `portadas/`. Los libros sin tapa cargada se dibujan con el
titulo. No se bajaron tapas reales: derechos de autor, y en el liceo sin
conexion se romperian.

---

## 10. Operaciones: cobrar y devolver

### Cobrar
Cinco pasos que van en **una transaccion**: revisar stock, guardar la
cabecera, guardar las lineas, descontar el stock y anotar los alquileres.
Todo o nada.

| Tipo | Que le pasa al stock |
|---|---|
| venta | bajan `stock_total` y `stock_disponible` |
| alquiler | baja solo `stock_disponible`; el total queda igual |
| digital | nada: no se agota |

El cliente es **opcional** en una venta y **obligatorio** si hay un
alquiler en el pedido.

Verificado: cobrar 3 articulos movio el stock de los tres como
corresponde; e intentar llevar 3 de un articulo con 1 disponible fallo y
**no escribio nada** — ni las ventas ni el stock de los otros dos
articulos que si tenian, que es justamente lo que prueba la transaccion.

### Devolver
`alquileres.php` lista lo que esta afuera con los dias, marca los que
pasaron los 15 dias de plazo, y el boton "Devolver" sube el stock y
completa `fecha_devolucion`. La fila no se borra: queda el historial.

### Un solo reloj
Las fechas las pone siempre la base (`CURDATE()`, `NOW()`), nunca PHP.
Se detecto en la prueba: una venta de las 23:15 quedaba con la venta
fechada hoy y el alquiler maniana, porque PHP y MySQL estaban en zonas
horarias distintas. Con dos relojes, tarde o temprano discrepan.

---

## 9. Estado al 14/09/2026

Funcionando y verificado:

| Que | Como se verifico |
|---|---|
| Base con las tres tablas | Importada en MariaDB 10.4.32 |
| Regla "no se vende un libro usado" | MariaDB **rechaza** el INSERT prohibido, y acepta el mismo libro como alquiler usado |
| Modo scanner | Muestra precio y datos, no suma |
| Modo venta, suma escondida | 690 -> 1540 -> 1635, visto en las lineas que salen al Arduino |
| Un codigo inexistente no resetea el total | Quedo en 1540 despues del codigo fallido |
| Total a los 3 s en la PANTALLA | El navegador se fue solo a `?ver=total`, sin JavaScript |
| Total a los 3 s en la LCD | German lo vio en la pantalla fisica: "TOTAL COMPRA 2175" |
| Cerrar pedido vuelve a cero | Verificado |
| Programa del Arduino | Compila (28% de programa) y esta cargado en COM4 |
| Protocolo serie | Contesta OK a los mensajes validos y ERR:FORMATO a uno roto |
| LCD conectada | La placa dice READY, no ERR:LCD |

**Los dos LED y el zumbador: probados el 16/09/2026.** Estaban pendientes desde
el arranque porque no habia LED enchufados. Ahora estan en los pines **7 y 8**
(y el zumbador en el **9**), y se vieron encender: verde con un codigo que
existe, rojo con uno que no, y los dos bips suenan distinto.

### El protocolo se simplifico a 3 campos (16/09/2026)

El mensaje que recibe el Arduino paso de seis campos a tres:

```
antes:   accion|encontrado|codigo|titulo|precio|total
ahora:   texto|precio|existe
         Rayuela|1320.00|1
```

**Por que alcanza con tres:** si la computadora puede mandar la palabra `Total`
en el lugar del texto y la suma en el lugar del precio, entonces es ella la que
decide *cuando* se muestra el total, y el Arduino ya no necesita medir los 3
segundos con `millis()`. Desaparecen el campo `accion`, las variables de estado
y el temporizador. Y queda **un solo reloj** en el sistema, que es el mismo
principio que ya estaba escrito para las fechas.

Efecto de rebote: la pantalla del Arduino deja de tener `if`. Los dos renglones
se escriben siempre igual; lo unico que se bifurca es la luz y el sonido.

**Pendiente:** `escaneo.php`, `arduino.js` y `arduino/libreria_lector/` siguen
hablando los **6 campos** y con el `millis()` adentro. Los programas nuevos
(`arduino/libreria_simple/` para Tinkercad y `arduino/libreria_simple_fisico/`
para la placa) usan los 3. Hay que unificar.

**Entorno instalado en esta PC:** XAMPP 8.2 (PHP 8.2.12, Apache, MariaDB
10.4.32). La carpeta del proyecto esta enlazada dentro de htdocs con un
junction, asi que se edita en el escritorio y se ve en
`http://localhost/libreria/`: una sola copia, sin riesgo de desincronizarse.

---

## 11. Puesta en marcha del 19/11/2026

- **Recableado:** verde en 12, rojo en 10, zumbador en 7. El HC-08 paso al pin 2
  porque el 10 lo ocupa el rojo. Los pines quedaron como constantes al principio
  de `libreria_simple_fisico.ino`.
- **El mostrador volvio al cable USB:** carga `arduino.js` (Web Serial) en vez
  de `bluetooth.js`. Es una sola linea en `mostrador.php`.
- **La placa tiene cargado `libreria_simple_fisico`** (3 campos). Verificado:
  contesta `recibi: [...]` a cada mensaje, los dos LED prenden, el zumbador
  suena y la LCD se detecta (READY sin ERR:LCD, 20/11).
- El Arduino IDE de esta PC esta vacio; el programa se compilo con el avr-gcc y
  el avrdude que quedaron en `AppData\Local\Arduino15`
  (`herramientas/compilar_y_cargar.sh`).

## 12. Portable y en GitHub (20/11/2026)

La PC de desarrollo no es la que se usa con el Arduino. Por eso el sistema
pasa a correr en cualquier Windows **sin instalar nada**:

- **SQLite en vez de MySQL.** La base es el archivo
  `libreria/datos/biblioteca.sqlite`, y `db.php` la crea sola la primera vez
  leyendo `biblioteca.sql`. Lo que cambio del SQL, explicado donde aparece:
  `INTEGER PRIMARY KEY` por `AUTO_INCREMENT`, `TEXT` + `CHECK` por `ENUM`,
  `date('now','localtime')` por `CURDATE()`, `julianday()` por `DATEDIFF()`,
  sin `FOR UPDATE` (SQLite bloquea la base entera al escribir) y
  `PRAGMA foreign_keys = ON` en cada conexion. Las reglas de la base (las
  `CHECK`, las claves foraneas, el `UNIQUE` de la etiqueta) siguen todas.
- **PHP adentro.** `php/` trae el PHP 8.2 oficial reducido a lo que se usa
  (10,6 MB). `INICIAR.bat` levanta el servidor de PHP en `127.0.0.1:8642` y
  abre Edge. Se usa 127.0.0.1 y no localhost porque en esta PC otro programa
  ocupaba 127.0.0.1 en el puerto que se probo primero, y con localhost el
  navegador podia terminar hablandole a ese programa.
- **Verificado sobre un clon limpio**, como lo haria la otra PC, y tambien en
  una carpeta con espacios en el nombre: 37 pruebas de punta a punta (roles,
  modos, cobro, transaccion sin stock que no escribe nada, alquiler y
  devolucion con fechas locales, las reglas de la base, paginas sin errores).
- Los PDF y ZIP (guias, respuestas del docente, fotos) **no se suben**: quedan
  solo en la PC de desarrollo (`.gitignore`).
- En la PC de desarrollo sigue existiendo la base MySQL `biblioteca` del 19/11
  y la `libreria` del 27/09; el sistema ya no usa ninguna de las dos.
