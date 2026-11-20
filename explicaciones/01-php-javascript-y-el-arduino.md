# Por que PHP no puede hablarle al Arduino, y JavaScript si

*Explicacion para el cuadernillo del alumno.*

## La pregunta

Nuestra libreria esta hecha con PHP. El Arduino esta enchufado por USB a la
computadora. Entonces, ¿por que no le escribimos al Arduino directamente desde
PHP, y tenemos que meter JavaScript, que era justo lo que queriamos evitar?

La respuesta no tiene nada que ver con que un lenguaje sea mejor que el otro.
Tiene que ver con **donde se ejecuta cada uno**.

## Las dos computadoras

Cuando entras a una pagina web hay siempre dos computadoras en juego:

- **El servidor**: la maquina donde vive la pagina.
- **El cliente**: la maquina donde esta la persona mirando, con su navegador.

Muchas veces son dos maquinas distintas y lejanas. Cuando entras a una pagina
del exterior, el servidor puede estar en otro pais.

Ahora la parte importante:

> **PHP se ejecuta en el servidor. JavaScript se ejecuta en el cliente.**

## Que pasa realmente cuando se abre una pagina PHP

1. El navegador **pide** la pagina.
2. El servidor agarra el archivo `.php` y **lo ejecuta**: hace las consultas a
   la base, arma el texto.
3. El resultado de esa ejecucion es **HTML comun**, texto y nada mas.
4. El servidor manda ese HTML al navegador.
5. **PHP termino.** Ya no existe. No quedo corriendo nada de PHP.
6. Recien ahi el navegador dibuja la pagina. Y si hay JavaScript, ahi arranca.

Conclusion: para cuando vos estas mirando la pantalla, **PHP ya se murio**. Y
nunca, en ningun momento, estuvo en tu computadora.

## Y el Arduino, ¿donde esta?

El Arduino esta enchufado con un cable a **tu** computadora. A la del cliente.

Entonces el problema se ve solo:

| | ¿Donde corre? | ¿Alcanza el cable USB? |
|---|---|---|
| PHP | En el servidor | No. Esta en otra maquina |
| JavaScript | En tu navegador, en tu maquina | Si. Es la maquina del cable |

PHP no es que "no sepa" hablar con un puerto USB. El problema es que **esta
parado en el lugar equivocado**. Es como pedirle a alguien por telefono que te
apriete un boton que esta en tu propio escritorio: no es que no sepa apretar
botones, es que no esta ahi.

## La trampa: "pero si yo lo probe y anduvo"

Aca viene lo que hay que entender bien, porque en internet van a encontrar
codigo que parece contradecir todo esto.

En Windows, PHP **puede** abrir un puerto serie con algo asi:

```php
$puerto = fopen('COM4:', 'w');   // esto a veces funciona
```

Y si lo probas **en tu propia computadora con XAMPP**, puede llegar a andar.
¿Por que? Porque en ese caso servidor y cliente **son la misma maquina**. El
servidor es tu PC. Entonces el cable si le llega.

Pero eso es una casualidad de como estas probando, no una solucion. Se rompe en
cuanto pasa cualquiera de estas cosas, que van a pasar:

1. **Se sube la pagina a un hosting.** PHP va a intentar abrir el COM4 **del
   servidor**, que esta en un datacenter y no tiene ningun Arduino enchufado.
   El codigo no da error: le habla a un puerto que no existe.
2. **Otra persona abre la pagina desde otra PC del liceo.** El Arduino sigue
   estando en la tuya. Le va a aparecer el LED prendido... a vos.
3. **Dos personas escanean al mismo tiempo.** Los dos PHP pelean por el mismo
   puerto y uno falla.
4. **Apache corre como un servicio de Windows**, con un usuario que
   normalmente ni siquiera tiene permiso para tocar los puertos serie.
5. PHP **no tiene una API de puerto serie de verdad**: no podes configurar la
   velocidad ni leer la respuesta en condiciones. La velocidad hay que
   configurarla desde afuera con el comando `mode` de Windows.

O sea: funciona mientras estes probando, y se rompe cuando el proyecto sale del
banco de trabajo. Ese es el peor tipo de error, porque te hace creer que
entendiste algo que no.

## Por que JavaScript si

JavaScript corre **en el navegador de la persona que esta mirando**. En la
maquina del cable. Ya esta parado en el lugar correcto.

Ademas tiene una herramienta hecha para esto, que se llama **Web Serial**, con
una API de verdad: elegir el puerto, fijar la velocidad en 9600, escribir y
leer.

## El detalle de seguridad que hay que entender

Web Serial obliga a que **el usuario haga clic y elija el puerto a mano**. No se
puede saltear con codigo.

A primera vista es una molestia. En realidad es lo mas importante de todo el
diseño. Pensalo al reves:

> Si una pagina web cualquiera pudiera abrir sola los puertos USB de tu
> computadora, entrar a un sitio desconocido le daria acceso a todo lo que
> tengas enchufado.

Por eso el navegador exige un clic tuyo: el permiso lo das vos, para **ese**
puerto y **ese** sitio. Por la misma razon Web Serial solo funciona en
`localhost` o en sitios con HTTPS, y solo en Chrome o Edge de escritorio.

## El reparto que nos queda

Esto ademas nos ordena el proyecto: cada tecnologia hace la parte que le toca.

| Tramo | Quien lo hace | ¿JavaScript? |
|---|---|---|
| Lector -> campo de la pagina | El lector escribe como un teclado | **No** |
| Enviar el codigo al servidor | Un `<form>` de HTML | **No** |
| Buscar el libro en la base | PHP + SQL | **No** |
| Mostrar el resultado en pantalla | PHP genera el HTML | **No** |
| Mandarle la linea al Arduino | JavaScript con Web Serial | **Si** |
| Prender el LED y escribir en el LCD | El programa del Arduino | **No** |

De seis tramos, JavaScript aparece en **uno solo**, y por una razon concreta:
es el unico tramo que ocurre en la maquina del usuario. Todo lo demas es
HTML, CSS, PHP y SQL.

## Para pensar

1. Si PHP corre en el servidor, ¿como hace para saber que escribiste en un campo
   del formulario, si el formulario esta en tu maquina?
2. Una pagina muestra la hora. Si la calcula PHP, ¿de que reloj la saca? ¿Y si
   la calcula JavaScript?
3. ¿Por que Web Serial funciona en `localhost` sin HTTPS, si HTTPS es un
   requisito?
4. Queremos que el LED se prenda en la PC del mostrador aunque la pagina este
   en un hosting. ¿Alcanza con JavaScript? ¿Que maquina tiene que abrir el
   puerto?
