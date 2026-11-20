# Por que la pagina del mostrador no se recarga nunca

*Explicacion para el cuadernillo del alumno.*

## El problema, contado desde el principio

Nuestro sistema funciona asi: escaneas un libro, el navegador manda el codigo
al servidor, PHP lo busca en la base y devuelve una pagina nueva con el
resultado. Eso es un **formulario de HTML** y es lo mas simple que existe.

Pero tiene una consecuencia que no se ve: cuando llega la pagina nueva,
**la anterior se destruye entera**. Todo lo que habia en memoria desaparece.

Y una de las cosas que hay en memoria es **la conexion con el Arduino**.

## Por que eso es un problema grave (y no una molestia)

Si el puerto se cierra con cada escaneo, hay que volver a abrirlo con el
siguiente. Y aca viene el detalle que cambia todo:

> **Cuando se abre el puerto serie, el Arduino Uno se reinicia.**

No es un error ni un descuido: la placa esta hecha a proposito asi. Tiene una
patita del cable USB (se llama DTR) conectada al pin de RESET. Sirve para que
la computadora pueda reiniciar la placa sola cuando le carga un programa
nuevo, sin que tengas que apretar el boton. Es comodisimo para programar, y es
un estorbo para nosotros.

Al reiniciarse, el Arduino arranca de cero: pasa por el gestor de arranque,
carga el programa y recien ahi empieza a funcionar.

**¿Cuanto tarda?** No lo supusimos, lo medimos. Abrimos el puerto y cronometramos
cuanto pasaba hasta que la placa mandaba su mensaje de arranque:

```
DTR activado    -> la placa dijo READY a los 1,97 s  => SE REINICIO
DTR desactivado -> la placa dijo READY a los 1,97 s  => SE REINICIO
```

Casi **2 segundos por escaneo**. Y fijate en la segunda linea: probamos
desactivando la senial DTR antes de abrir, que es el truco que suele
recomendarse, y **se reinicio igual**. Ese camino estaba cerrado.

Dos segundos no suenan a mucho leyendolo. Pensalo en el mostrador: una persona
con diez libros espera **20 segundos** mirando una pantalla apagada. Inusable.

## La idea que resuelve todo

Si el problema es que la pagina se recarga, la solucion no es pelearse con el
puerto serie: es **hacer que la pagina no se recargue**.

Pero necesitamos que algo se recargue, porque asi es como PHP nos contesta.

La salida es partir la pantalla en dos:

```
+-- mostrador.php --------------------------------+
|  Esta pagina se carga UNA vez y no se mueve mas. |
|  Aca vive la conexion con el Arduino.            |
|                                                  |
|  +-- escaneo.php (adentro de un iframe) -------+ |
|  |  ESTA si se recarga con cada escaneo.       | |
|  |  Campo de codigo, resultado, total...       | |
|  +---------------------------------------------+ |
+--------------------------------------------------+
```

Un **iframe** es una pagina metida adentro de otra. Cada una se carga por su
cuenta. Cuando la de adentro se recarga, la de afuera **ni se entera**: sigue
viva, con su memoria intacta y su puerto abierto.

Resultado:

- El puerto se abre **una sola vez**, al empezar la jornada.
- El Arduino se reinicia **una sola vez**, esos 2 segundos, y nunca mas.
- Cada escaneo es instantaneo.
- Toda la logica sigue en PHP, como queriamos.

## Como se pasan el dato

La pagina de adentro no puede hablarle al Arduino: el puerto no es de ella. Le
pasa el mensaje a la de afuera con **una sola linea**:

```javascript
window.parent.alArduino("V|1|V0001|Cien anios|690.00|1540.00");
```

`window.parent` es "la pagina que me contiene". Y `alArduino` es la funcion que
vive alla afuera, al lado del puerto abierto. Eso es todo el JavaScript que hay
en esta parte del sistema: una linea que pasa un mensaje de una pagina a la
otra.

El navegador permite esto **solo si las dos paginas vienen del mismo sitio**. Si
un iframe pudiera llamar funciones de cualquier pagina que lo contenga, una
publicidad metida en un sitio podria manejar el sitio entero.

## El detalle que se paga por esto

Ningun enlace de la pagina de afuera puede cambiar la ventana entera: eso
destruiria la pagina de afuera y cerraria el puerto. Volveriamos al problema del
principio por haber hecho clic en el lugar equivocado.

Por eso los enlaces del menu abren la seccion **adentro del recuadro**, nombrando
al iframe:

```html
<iframe src="escaneo.php" name="panel"></iframe>
...
<a href="libros.php" target="panel">Libros</a>
```

`target="panel"` le dice al navegador "esto se abre en el recuadro que se llama
panel". La pagina de afuera ni se entera, y el puerto sigue abierto.

**La primera version lo resolvia distinto, y salio mal.** Los enlaces se abrian
en otra pestania (`target="_blank"`). Parecia lo mismo, pero tenia una trampa: si
desde esa pestania nueva se volvia a "Mostrador", quedaban **dos mostradores**
abiertos. El primero tenia el puerto; el segundo decia "Arduino no conectado", y
al tocar "Conectar" el navegador contestaba que el puerto estaba **ocupado**...
ocupado por el primero. Para el operador, el sistema "se desconectaba solo".

Es un buen ejemplo de algo que pasa seguido al programar: **una decision de
diseño te obliga a otras**, y a veces la primera que se te ocurre tiene un precio
escondido que aparece recien cuando alguien la usa de verdad.

### Y las paginas, como saben que estan adentro del recuadro

Una seccion abierta adentro del recuadro no tiene que dibujar su propio
encabezado ni su menu: ya estan los del mostrador, arriba. Pero la misma
seccion, abierta en una ventana sola, si los necesita.

No hace falta avisarle: **el navegador lo dice solo**. En cada pedido manda un
renglon, `Sec-Fetch-Dest`, que dice para que es lo que pide: `document` si es una
pagina entera, `iframe` si va adentro de un recuadro, `image` si es una imagen.
PHP lo lee como cualquier otro dato que manda el navegador
(`$_SERVER['HTTP_SEC_FETCH_DEST']`). Esta en la funcion `enElPanel()` de `db.php`.

## Lo que descartamos, y por que

**Modificar la placa.** Se le puede soldar un capacitor entre RESET y GND, o
cortar una pista, y el reinicio automatico desaparece. Funciona. Pero despues
**no se le pueden cargar programas nuevos** sin deshacer el cambio, y en clase
se cargan programas todo el tiempo. Arreglaria el sintoma y romperia el trabajo.

**Aguantar los 2 segundos.** Se descarta solo: nadie usaria el sistema.

**Usar JavaScript para todo** (que la pagina no recargue nunca y pida los datos
por atras). Es lo que hace la mayoria de las paginas modernas y resolveria esto.
Pero la consigna del proyecto es HTML, CSS, PHP y SQL: elegimos respetarla y
buscar la solucion adentro de esas herramientas. Y aparecio.

## Para pensar

1. ¿Por que el Arduino se reinicia al abrir el puerto? ¿A quien le sirve eso?
2. Si el reinicio tardara 50 milisegundos en vez de 2 segundos, ¿haria falta el
   iframe? ¿Que otra cosa cambiaria en la decision?
3. ¿Que pasaria si sacaramos el `target="panel"` del enlace a Libros?
   Descubrilo probandolo, y mira el mensaje de estado de la pagina.
4. El iframe es una tecnica vieja, de antes de que existieran estas soluciones
   modernas. ¿Por que sigue sirviendo? ¿Que problema resuelve que no cambio con
   los anios?
