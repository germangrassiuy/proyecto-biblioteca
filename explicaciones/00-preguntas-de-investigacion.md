# Preguntas de investigación

Antes de escribir el programa hay que entender con qué se está trabajando.
Estas son las preguntas que tienen que poder contestar para armar el
mostrador de la librería: pantalla, luces, sonido y la comunicación con
la computadora.

**Cómo se contestan.** Con sus palabras, cortito, y **diciendo dónde lo
encontraron** (la página, el video, la hoja de datos). Una respuesta
copiada y pegada que no se puede explicar en voz alta no cuenta.

**Ojo:** varias de estas preguntas **no se contestan buscando, se
contestan probando** en Tinkercad. Están marcadas con 🔧. Esas son las
más importantes: buscar es fácil, comprobar es lo que hace un técnico.

---

## Bloque 1 — La pantalla

1. En Tinkercad hay dos pantallas LCD de 16x2: una tiene 16 patas y la
   otra 4. ¿Por qué una necesita 16 cables y la otra se arregla con 4?
   ¿Qué tiene adentro la de 4 que la otra no tiene?

2. Las patas de nuestra pantalla dicen GND, VCC, SDA y SCL. Ya saben qué
   son las dos primeras. **¿Qué significan SDA y SCL, y qué lleva cada
   uno de esos dos cables?** (No llevan lo mismo: uno lleva los datos y
   el otro no.)

3. ¿Qué es I2C? ¿Por qué le alcanzan dos cables para transmitir texto
   entero?

4. En el Arduino Uno, ¿dónde se conectan SDA y SCL? Busquen bien: **hay
   más de un lugar donde se puede enchufar.** ¿Son pines distintos o son
   los mismos repetidos?

5. Si la pantalla está conectada en los pines SDA y SCL, ¿puedo usar A4 y
   A5 para conectar un sensor? Justifiquen con lo que encontraron en la
   pregunta 4.

6. Nuestra pantalla se declara en el programa con un `0`:
   `Adafruit_LiquidCrystal lcd(0);`. Ese 0 no es un pin ni una fila.
   ¿Qué es? ¿Qué pasaría si colgáramos dos pantallas de los mismos dos
   cables?

7. 🔧 ¿Qué pasa si cruzan SDA con SCL? ¿Se rompe algo, no funciona, o
   funciona igual? Pruébenlo y después expliquen por qué.

8. `lcd.begin(16, 2);` — ¿qué significan esos dos números? 🔧 ¿Qué pasa
   si ponen `(20, 4)` en una pantalla de 16x2?

---

## Bloque 2 — Las bibliotecas

9. ¿Qué es una biblioteca (*library*)? ¿Por qué no podemos manejar la
   pantalla escribiendo las instrucciones nosotros?

10. Busquen bibliotecas para manejar una LCD I2C con Arduino. **Nombren
    al menos tres** y quién las escribió.

11. ¿Cuáles de esas tres se pueden usar **en Tinkercad**? ¿Cómo se agrega
    una biblioteca en Tinkercad? (Hay un botón. Encuéntrenlo.)

12. ¿La biblioteca que funciona en Tinkercad es la misma que usaríamos en
    el Arduino IDE con la pantalla de verdad? Si no es la misma,
    **¿cuántas líneas del programa hay que cambiar para pasar de una a
    la otra?**

13. 🔧 La pantalla puede verse **completamente negra aunque el programa
    esté perfecto**. Averigüen qué es el *backlight* y cómo se prende
    desde el programa. ¿Se llama igual en las dos bibliotecas de la
    pregunta 12?

14. Cuando la pantalla se ve negra, hay por lo menos **tres causas
    distintas** posibles. Nómbrenlas. ¿Qué mirarían, que no sea la
    pantalla, para saber cuál de las tres es? (Esta es la pregunta más
    difícil de la lista.)

---

## Bloque 3 — El zumbador

15. Hay zumbadores **activos** y **pasivos**. ¿En qué se diferencian?
    ¿Cuál de los dos es el de Tinkercad?

16. ¿Qué hace la instrucción `tone()`? Lleva tres números adentro:
    ¿qué es cada uno?

17. ¿Qué es la frecuencia y qué tiene que ver con que un sonido se
    escuche agudo o grave? ¿Entre qué frecuencias escucha una persona?

18. 🔧 En nuestro programa, el bip de "encontrado" es agudo y corto y el
    de "no encontrado" es grave y largo. **¿Para qué molestarse en que
    suenen distinto, si la pantalla ya dice qué pasó?** Piensen en
    alguien atendiendo una caja con gente esperando.

19. ¿El zumbador necesita una resistencia como el LED? ¿Por qué sí o por
    qué no?

20. 🔧 ¿El zumbador tiene polaridad, como el LED? ¿Qué pasa si lo
    conectan al revés?

21. `tone()` **no frena el programa**: deja el pin sonando y sigue. Hay
    otra forma de hacer sonar algo que sí lo frena, usando `delay()`.
    ¿Cuál es la diferencia, y por qué en una caja de mostrador importa?

---

## Bloque 4 — El puerto serie

22. ¿Qué es el puerto serie? ¿Por qué se llama "serie" y no "paralelo"?

23. `Serial.begin(9600)` — ¿qué es ese 9600? 🔧 ¿Qué pasa si el programa
    dice 9600 y el Monitor Serial está puesto en otro número?

24. 🔧 **¿Cómo se le manda información al Arduino desde el Monitor Serial
    de Tinkercad?** ¿Se puede, o solamente se puede leer lo que el
    Arduino escribe? Encuentren dónde se escribe y cómo se envía.

25. 🔧 Cuando pulsan *Send* en Tinkercad, ¿se manda también el Enter al
    final (el carácter de fin de línea), o solo las letras que
    escribieron? **Averígüenlo probando**, no buscando: hagan un
    programa de tres líneas que avise cuando le llega un fin de línea.

26. **¿Esto que estamos haciendo sirve en la realidad, o es un invento de
    Tinkercad?** ¿Por dónde viaja el puerto serie en un Arduino de
    verdad, por qué cable? ¿Puede una página web mandarle datos por ahí?

27. ¿Qué hace `Serial.available()`? ¿Por qué el programa lo pregunta
    antes de leer, en vez de leer directamente?

28. ¿Qué diferencia hay entre `Serial.read()`, `Serial.readString()` y
    `Serial.readStringUntil()`? ¿Por qué nosotros usamos el tercero?

29. 🔧 ¿Qué pasa si le mandan `hola` en vez del formato que espera el
    programa? ¿Y si le mandan la mitad del mensaje?

---

## Bloque 5 — El lector de códigos de barras

30. En Tinkercad **no existe** el lector de códigos. ¿Cómo se conecta un
    lector real a una computadora?

31. Nuestro lector es "HID teclado". ¿Qué significa eso? **¿Por qué,
    entonces, tipear el código a mano es exactamente lo mismo que
    escanearlo?** (De esto depende que todo el trabajo que hicimos sin
    lector siga sirviendo cuando lo enchufemos.)

32. ¿Qué es un código EAN-13? ¿Qué es Code 128? ¿Qué es el dígito de
    control y para qué sirve?

33. Nuestro lector es 1D. ¿Qué diferencia hay entre 1D y 2D? ¿Puede leer
    un código QR?

34. Todos los ejemplares de la misma edición de un libro traen **el mismo
    ISBN** impreso en la tapa. Si la librería tiene un ejemplar para
    vender a $450 y otro para alquilar a $70, **¿alcanza con escanear el
    ISBN de la tapa?** ¿Qué haría falta?

---

## Bloque 6 — El reparto del trabajo

Estas no se buscan en internet: se razonan. Son las que van a tener que
defender si alguien les pregunta por qué el sistema está hecho así.

35. ¿Puede el Arduino consultar la base de datos de la librería y buscar
    el libro él mismo? ¿Por qué no?

36. ¿Cuánta memoria tiene un Arduino Uno? Compárenla con la de la
    computadora que están usando. Escriban las dos cifras juntas.

37. El total de la compra: **¿lo suma la computadora o lo suma el
    Arduino?** Justifiquen. Den por lo menos dos razones.

38. El mensaje que llega al Arduino trae un `1` o un `0` para decirle qué
    LED prender. ¿Por qué se le manda ya decidido, en vez de que la placa
    lo deduzca sola?

39. 🔧 Al abrir el puerto serie, el Arduino Uno **se reinicia**.
    Averigüen por qué, y cuánto tarda en volver. Si el total de la venta
    estuviera guardado en la memoria del Arduino, ¿qué pasaría?

40. ¿Qué parte de este sistema decide **qué es verdad** (cuánto cuesta,
    si existe, cuánto stock hay) y qué parte decide solamente **cómo se
    ve**? Repártanlo en dos columnas.

---

## Para la segunda etapa

Cuando el circuito ya funcione, el programa tiene que mostrar el total de
la compra a los 3 segundos de que nadie escanee nada.

41. ¿Qué devuelve `millis()`?

42. ¿Por qué no se puede usar `delay(3000)` para esperar esos 3 segundos?
    ¿Qué dejaría de hacer el Arduino mientras espera?

43. `millis()` cuenta milisegundos desde que arrancó la placa, y el
    número tiene un tope. ¿A los cuántos días se llena y vuelve a cero?
