# Libreria - sistema de mostrador con Arduino

Sistema de una libreria hecho en **HTML, CSS, PHP y SQL**, con un **Arduino**
que muestra en una pantalla LCD el libro escaneado, prende un LED verde o rojo
y toca un bip. Es una implementacion de referencia para un proyecto escolar:
todo el codigo explica el **por que** de cada decision.

- Catalogo de 100 titulos, en venta, alquiler y digital.
- Mostrador con lector de codigo de barras (o tipeando el codigo).
- Modo scanner (consulta precio) y modo venta (suma y cobra).
- Alquileres con devolucion, clientes y control de stock.
- Dos roles: `admin` ve todo, `cliente` solo el catalogo.

## Como usarlo en cualquier computadora (Windows)

No hay que instalar nada: el PHP viene adentro de la carpeta `php/` y la base
de datos es SQLite, un archivo que se crea solo.

1. Bajar el proyecto:
   - con git: `git clone https://github.com/germangrassiuy/proyecto-biblioteca.git`
   - o sin git: boton verde **Code** -> **Download ZIP**, y descomprimirlo.
2. Doble clic en **`INICIAR.bat`**.
3. Se abre el sistema en Edge (`http://localhost:8765/`). Entrar con:

   | Usuario | Clave | Que ve |
   |---|---|---|
   | `admin` | `admin123` | mostrador, catalogo, libros, ejemplares, alquileres, clientes |
   | `cliente` | `cliente123` | solo el catalogo |

4. **La ventana negra tiene que quedar abierta**: es el servidor. Cerrarla
   apaga el sistema.

Para volver a empezar con la base limpia: cerrar el sistema y borrar la
carpeta `libreria/datos/`. La proxima vez se crea de nuevo.

### Probar sin el lector

Escribir el codigo en el campo del mostrador y pulsar Enter. Es exactamente
lo que hace el lector, que se comporta como un teclado.

| Codigos | Que son |
|---|---|
| `V0001` a `V0080` | libros a la venta (nuevos) |
| `A0001` a `A0070` | libros para alquilar (casi todos usados) |
| `D0001` a `D0050` | libros digitales (sin limite de stock) |
| `NADA` | no existe: sirve para probar el LED rojo |

## El Arduino

1. Enchufar el Arduino por USB **antes** de abrir el mostrador.
2. En el mostrador, boton **Conectar Arduino** y elegir el puerto
   (`USB-SERIAL CH340` o `Arduino Uno`). Hay que hacerlo una vez por jornada.

Necesita **Edge o Chrome de escritorio**: Firefox no puede hablar con el
puerto USB (no tiene Web Serial).

### Cableado

| Pieza | Conexion |
|---|---|
| LED verde | pata larga al **pin 12**, pata corta a una resistencia de 220 ohm y a GND |
| LED rojo | pata larga al **pin 10**, pata corta a una resistencia de 220 ohm y a GND |
| Zumbador | pata + al **pin 7**, la otra a GND |
| Pantalla LCD 16x2 I2C | GND a GND, VCC a 5V, SDA a SDA (A4), SCL a SCL (A5) |
| Bluetooth HC-08 (opcional) | VCC a 5V, GND a GND, TXD al **pin 2**, RXD sin conectar |

### Cargarle el programa a la placa

El programa es `libreria/arduino/libreria_simple_fisico/libreria_simple_fisico.ino`.
Se abre con el **Arduino IDE**, se instala la libreria **hd44780** de Bill Perry
(Herramientas -> Administrar bibliotecas) y se sube a la placa. Si la placa ya
lo tiene cargado, no hace falta repetirlo.

## Las carpetas

| Carpeta / archivo | Que tiene |
|---|---|
| `INICIAR.bat` | arranca todo |
| `libreria/` | el sistema: las paginas PHP, el CSS, `biblioteca.sql` y el JavaScript minimo para el Arduino |
| `libreria/LEEME.txt` | como funciona cada parte, en detalle |
| `libreria/arduino/` | los programas de la placa (fisica y Tinkercad) |
| `explicaciones/` | material para los alumnos: el por que de las decisiones dificiles |
| `ESPECIFICACION.md` | que tiene que hacer el sistema y como se fue decidiendo |
| `php/` | PHP 8.2 oficial para Windows, reducido a lo que el sistema usa (licencia en `php/license.txt`) |
| `herramientas/` | script para compilar y cargar el Arduino sin el IDE (opcional) |
