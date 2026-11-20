// ============================================================
//  LIBRERIA - version para TINKERCAD
//  (la misma placa, pero sin lector, sin computadora y sin base)
// ============================================================
//
//  POR QUE EXISTE ESTE ARCHIVO
//
//  En Tinkercad no hay lector de codigos de barras: no existe ese
//  componente, y aunque existiera no habria donde enchufarlo. Tampoco
//  hay computadora con PHP ni base de datos MySQL.
//
//  Y sin embargo se puede practicar TODA la parte del Arduino. La razon
//  es la misma que hace que el proyecto real funcione sin tener el
//  lector enchufado: el Arduino NUNCA busco nada en la base.
//
//      El Arduino no sabe que libros hay. No los puede saber: no tiene
//      la base de datos, no tiene disco y no habla con MySQL. La
//      computadora busca el codigo, decide si existe, calcula el total,
//      y le manda al Arduino UNA LINEA DE TEXTO con todo resuelto:
//      el titulo, el precio, y si hay que prender verde o rojo.
//
//  Entonces, si el Arduino solo recibe una linea de texto y obedece, da
//  exactamente lo mismo quien la escriba: PHP, o un alumno tipeandola a
//  mano en el Monitor Serial. Para la placa son indistinguibles.
//  Eso es lo que hacemos aca.
//
//  QUE CAMBIA respecto de libreria_lector.ino (el de la placa real)
//    - La biblioteca de la pantalla. El Arduino fisico usa hd44780, que
//      en Tinkercad NO existe. Tinkercad usa Adafruit_LiquidCrystal.
//      Es la misma pantalla y el mismo cable de cuatro hilos: lo que
//      cambia es el programa que la maneja.
//    - Los dos renglones se escriben en la LCD y TAMBIEN se dibujan en
//      el Monitor Serial. Asi el programa ya se puede probar antes de
//      terminar de cablear la pantalla.
//    - Cierra la linea recibida de tres formas distintas (ver el loop),
//      porque el Monitor Serial de Tinkercad no siempre manda el Enter.
//    - Trae un MODO_DEMO para probar el cableado sin tipear nada.
//
//  QUE NO CAMBIA, y es lo importante
//    - Los pines: 5 verde, 6 rojo, 8 zumbador, A4 y A5 la pantalla.
//    - El protocolo: la misma linea, con los mismos 6 campos.
//    - Las respuestas: READY, OK, ERR:FORMATO, ERR:LARGO.
//    - Los tres segundos medidos con millis(), sin delay().
//  Lo que el alumno practica aca sirve tal cual en la placa fisica.
//
// ------------------------------------------------------------
//  CABLEADO EN TINKERCAD
// ------------------------------------------------------------
//    LCD I2C (la de 4 patas):
//              GND -> GND        VCC -> 5V
//              SDA -> SDA        SCL -> SCL
//    (los pines SDA y SCL son los dos que estan arriba de AREF, sin
//     rotulo en el dibujo de Tinkercad. Tambien sirve SDA -> A4 y
//     SCL -> A5: son los mismos dos pines, ver abajo.)
//
//    OJO: los dos cables tienen que CRUZARSE. En el header del Arduino
//    el orden desde el borde es SCL y despues SDA; en la pantalla es al
//    revez, SDA arriba y SCL abajo. Si los cables quedan paralelos,
//    SDA termina conectado a SCL y la pantalla no muestra nada.
//
//    LED verde:  pata larga (anodo, +) -> pin 5
//                pata corta -> resistencia 220 ohm -> GND
//    LED rojo:   pata larga (anodo, +) -> pin 6
//                pata corta -> resistencia 220 ohm -> GND
//    Zumbador:   pata + -> pin 8        pata - -> GND
//
//  DOS LUGARES DONDE ENCHUFAR LA PANTALLA, Y SON EL MISMO
//
//  El Uno tiene los pines SDA y SCL arriba de AREF, y ahi es donde
//  conviene conectarla. Pero si en vez de eso se conecta SDA a A4 y SCL
//  a A5, funciona exactamente igual, y no es una casualidad: son los
//  MISMOS DOS PINES.
//
//  El chip del Uno (un ATmega328P) tiene un solo par de patas capaces de
//  hablar I2C. En el zocalo esas dos patas aparecen como A4 y A5, y
//  ademas estan repetidas arriba de AREF con su nombre propio, para que
//  se pueda usar la pantalla sin ocupar el lugar de las analogicas.
//  Dos juegos de agujeros, un solo par de cables por dentro: por eso
//  conectar en A4 y A5 es lo mismo que conectar en SDA y SCL, y por eso
//  NO se pueden usar los dos a la vez para cosas distintas.
//
//  Que la biblioteca no pregunte nunca en que pines esta la pantalla es
//  la prueba: no hay nada que elegir, el I2C sale por donde puede salir.
//
//  I2C se llama asi porque le alcanzan DOS cables para todo: SDA lleva
//  los datos y SCL el compas que marca cuando leerlos. Por eso la
//  pantalla usa 4 patas en vez de las 16 de la LCD comun: adentro trae
//  un chip que recibe por esos dos cables y se encarga del resto.
//
//  La resistencia NO es opcional, ni en Tinkercad. El pin entrega mas
//  corriente de la que el LED aguanta, y el LED se quema. Tinkercad lo
//  simula: aparece el cartel de que el LED se quemo.
//
//  Como se conecta en la protoboard: las filas de agujeros estan unidas
//  de a cinco por dentro. Cada LED va con sus dos patas en filas
//  DISTINTAS; si van en la misma fila, las dos patas quedan unidas entre
//  si (cortocircuito) y el LED no prende nunca. Lo mismo la resistencia.
//
//  El zumbador de Tinkercad es piezoelectrico (pasivo): no suena solo,
//  hay que mandarle la frecuencia. Es justo el que necesita tone().
//
// ------------------------------------------------------------
//  COMO SE USA
// ------------------------------------------------------------
//  1. Start Simulation.
//  2. Abrir el Monitor Serial (abajo del panel de codigo).
//  3. Escribir una linea en el campo de texto y pulsar Send:
//
//       S|1|V0001|Cien anios|690.00|0.00
//
//     Tiene que prender el LED verde, sonar un bip corto y agudo, y
//     aparecer el titulo arriba y el precio abajo en la pantalla.
//
//       V|0|ZZZZZ|-|0.00|1540.00
//
//     Tiene que prender el LED rojo, sonar un bip largo y grave, y
//     aparecer NO ENCONTRADO.
//
//  Hay una lista de lineas para copiar y pegar en LEEME.txt.
// ============================================================

// Esta biblioteca ya viene en Tinkercad: no hay que instalar nada, pero
// hay que agregarla con el boton "Libraries" del panel de codigo.
// OJO: en el Arduino IDE de la PC esta biblioteca NO esta, y no hace
// falta, porque ahi corre libreria_lector.ino, que usa hd44780.
#include <Adafruit_LiquidCrystal.h>

// El 0 no es el pin ni la fila: es la DIRECCION de la pantalla dentro
// del cable I2C. Por esos dos cables pueden colgarse varios aparatos a
// la vez, asi que cada uno atiende cuando lo llaman por su numero. La
// pantalla de Tinkercad viene con el numero 0.
Adafruit_LiquidCrystal lcd(0);

// ---- Los pines. Los mismos que en la placa real ----
const byte PIN_VERDE    = 5;
const byte PIN_ROJO     = 6;
const byte PIN_ZUMBADOR = 8;

// ---- Los sonidos: frecuencia en hercios, duracion en milisegundos ----
// Agudo y corto = encontrado. Grave y largo = no encontrado. Se
// distinguen sin mirar la pantalla, que es para lo que sirve un sonido
// en un mostrador: el operador mira al cliente, no al monitor.
const unsigned int BIP_OK_TONO      = 2000;
const unsigned int BIP_OK_DURACION  = 90;
const unsigned int BIP_MAL_TONO     = 400;
const unsigned int BIP_MAL_DURACION = 400;
const unsigned int BIP_CIERRE_TONO  = 1200;
const unsigned int BIP_CIERRE_DURA  = 180;

// ---- Los tiempos ----
const unsigned long ESPERA_DEL_TOTAL   = 3000;  // sin recibir nada -> total
const unsigned long DURACION_DE_LA_LUZ = 3000;  // cuanto queda prendido el LED

// Tercera forma de cerrar la linea, explicada en el loop().
const unsigned long CIERRE_POR_SILENCIO = 1000;

// ---- Modo demo ----
// Ponerlo en true para que la placa se mande los codigos a si misma, uno
// cada 4 segundos, sin tipear nada. Sirve para dos cosas: probar que el
// cableado esta bien antes de pelearse con el Monitor Serial, y ver el
// recorrido completo de una venta (encontrado, no encontrado, cierre).
const bool MODO_DEMO = false;

// Las cuatro lineas de la demo. No son un ejemplo al azar: recorren las
// reglas del sistema en orden.
const char *DEMO[] = {
  "S|1|V0001|Cien anios|690.00|0.00",     // modo scanner: verde, no suma
  "V|1|A0007|El Aleph|450.00|450.00",     // modo venta: verde y arranca el total
  "V|0|ZZZZZ|-|0.00|450.00",              // no existe: rojo, y el total NO se pierde
  "C|1|-|-|0.00|450.00"                   // pedido cerrado: bip de cierre
};
const byte DEMO_PASOS = sizeof(DEMO) / sizeof(DEMO[0]);

byte          demoPaso    = 0;
unsigned long momentoDemo = 0;


// ---- Donde se junta la linea que llega, letra por letra ----
// Es un buffer, o sea un cajon de espera: las letras del puerto serie
// llegan de a una y no sirven de nada sueltas, asi que se guardan hasta
// tener la linea entera y solo entonces se procesa.
char linea[120];
byte usados     = 0;
bool desbordada = false;
byte barras     = 0;             // cuantos | llegaron: hacen falta 5
unsigned long momentoLetra = 0;  // cuando llego la ultima letra

// ---- Estado del modo venta ----
bool          totalPendiente = false;  // hay un total esperando para salir
unsigned long momentoUltimo  = 0;      // cuando llego el ultimo mensaje
char          totalGuardado[16];

// ---- Estado de los LED ----
bool          luzEncendida = false;
unsigned long momentoLuz   = 0;


void setup() {
  Serial.begin(9600);

  // pinMode le avisa a la placa que esos tres pines son SALIDAS: van a
  // mandar corriente hacia afuera, no a medir lo que entra.
  pinMode(PIN_VERDE,    OUTPUT);
  pinMode(PIN_ROJO,     OUTPUT);
  pinMode(PIN_ZUMBADOR, OUTPUT);
  apagarLuces();

  // 16 columnas y 2 filas: el tamanio de la pantalla. La biblioteca
  // necesita saberlo para calcular donde empieza el segundo renglon.
  lcd.begin(16, 2);

  // La luz de fondo. En Tinkercad esta linea NO hace falta: se comprobo
  // que la pantalla arranca con la luz prendida. Se deja igual por dos
  // razones: no molesta, y deja a la vista que el backlight es algo que
  // se maneja por programa y no siempre viene prendido.
  //
  // Ojo con esta linea al pasar de un lado al otro: en la placa real, con
  // la biblioteca hd44780, el mismo pedido se escribe lcd.backlight().
  // Es el unico lugar donde las dos bibliotecas no se llaman igual.
  // La version simple (arduino/libreria_simple/) no la trae, justamente
  // porque en Tinkercad no se necesita.
  lcd.setBacklight(1);

  // En la placa real, aca se pregunta si la pantalla contesto y, si no,
  // se avisa con ERR:LCD. Esta biblioteca no lo puede decir, asi que
  // siempre contestamos READY. La computadora escucha esa palabra para
  // saber que la placa arranco (arduino.js la espera).
  Serial.println("READY");
  mostrar("Libreria lista", "Escanee un libro");

  totalGuardado[0] = '\0';
  momentoLetra = millis();
  momentoDemo  = millis();
}


void loop() {

  // ---- 1. Juntar las letras que llegan ----
  while (Serial.available()) {
    char c = Serial.read();

    // TRES FORMAS DE SABER QUE LA LINEA TERMINO
    //
    // En el Monitor Serial del Arduino IDE, y en lo que manda arduino.js,
    // la linea termina con un salto de linea ('\n'). Pero el Monitor
    // Serial de Tinkercad no siempre lo agrega al pulsar Send, y si
    // esperaramos ese '\n' que nunca llega, la placa se quedaria muda y
    // pareceria que el programa esta roto. Asi que aceptamos tres
    // finales:
    //
    //   '\n' o '\r'   el salto de linea normal, venga como venga.
    //   '#'           un final que el alumno puede escribir a mano:
    //                   S|1|V0001|Cien anios|690.00|0.00#
    //   el silencio    ver el punto 2, mas abajo.
    //
    // Es codigo de mas que la placa real no necesita. Esta aca a
    // proposito: adaptarse al aparato que uno tiene, en vez de suponer
    // como deberia comportarse, es parte del trabajo.
    if (c == '\n' || c == '\r' || c == '#') {
      cerrarLinea();

    } else if (usados < sizeof(linea) - 1) {
      linea[usados++] = c;
      if (c == '|') barras++;
      momentoLetra = millis();

    } else {
      // Mensaje mas largo de lo que entra en el cajon. Se marca y se
      // descarta ENTERO: es mejor avisar que mostrar un dato cortado por
      // la mitad, porque un precio cortado se lee como un precio valido.
      desbordada = true;
    }
  }

  // ---- 2. Cerrar la linea por silencio ----
  // La tercera forma. Si ya llegaron los 5 separadores, la linea esta
  // completa aunque no haya llegado ningun Enter; y si encima pasa un
  // segundo entero sin que llegue una letra nueva, es que no viene nada
  // mas. Pedimos las dos cosas juntas para no cortar una linea a medio
  // escribir: con menos de 5 barras no se cierra nada.
  if (usados > 0 && barras >= 5 && (millis() - momentoLetra >= CIERRE_POR_SILENCIO)) {
    cerrarLinea();
  }

  // ---- 3. El reloj de los 3 segundos ----
  // millis() devuelve cuantos milisegundos pasaron desde que arranco la
  // placa. Restandole el momento del ultimo mensaje sabemos cuanto hace
  // que nadie escanea, sin frenar el programa como haria un delay().
  if (totalPendiente && (millis() - momentoUltimo >= ESPERA_DEL_TOTAL)) {
    char fila[17];
    snprintf(fila, sizeof(fila), "$%s", totalGuardado);
    mostrar("TOTAL COMPRA", fila);
    totalPendiente = false;
  }

  // ---- 4. El LED se apaga solo ----
  // Mismo mecanismo que el total, y por la misma razon: delay() congela
  // el programa entero, y la placa dejaria de escuchar el puerto serie
  // mientras espera. Con millis() solo miramos el reloj al pasar.
  if (luzEncendida && (millis() - momentoLuz >= DURACION_DE_LA_LUZ)) {
    apagarLuces();
    luzEncendida = false;
  }

  // ---- 5. La demo, si esta prendida ----
  if (MODO_DEMO && (millis() - momentoDemo >= 4000)) {
    momentoDemo = millis();

    // Se copia al buffer antes de procesar. procesar() ROMPE el texto que
    // recibe: le mete ceros encima de las barras para cortarlo en campos.
    // Y el texto de DEMO es una constante del programa, que no se puede
    // modificar. Copiarlo primero es lo que evita el problema.
    strcpy(linea, DEMO[demoPaso]);
    procesar(linea);

    demoPaso = (demoPaso + 1) % DEMO_PASOS;
  }
}


// ------------------------------------------------------------
//  cerrarLinea() - la linea termino: procesarla y vaciar el cajon
// ------------------------------------------------------------
//  Esta en una funcion aparte porque hay tres caminos distintos que
//  terminan aca (Enter, '#' y silencio). Si el codigo estuviera repetido
//  tres veces, arreglar algo significaria acordarse de arreglarlo en las
//  tres, y tarde o temprano una queda sin arreglar.
// ------------------------------------------------------------
void cerrarLinea() {
  linea[usados] = '\0';        // el '\0' es la marca de "el texto termina aca"

  if (desbordada) {
    Serial.println("ERR:LARGO");
  } else if (usados > 0) {
    procesar(linea);
  }
  // Si usados es 0 no llego nada: fue un Enter suelto. No se contesta
  // nada, porque no hubo pregunta.

  usados     = 0;
  barras     = 0;
  desbordada = false;
}


// ------------------------------------------------------------
//  procesar() - entender el mensaje que llego
// ------------------------------------------------------------
//  El formato es:   accion|encontrado|codigo|titulo|precio|total
//  Por ejemplo:     V|1|V0001|Cien anios|690.00|1540.00
//
//  accion:      S = modo scanner, V = modo venta, C = pedido cerrado
//  encontrado:  1 = el libro existe en la base, 0 = no esta
//  codigo:      el codigo interno del ejemplar (no el ISBN de la tapa)
//  titulo:      ya resuelto por la computadora
//  precio:      el de ESE ejemplar
//  total:       lo que suma el pedido hasta ahora
//
//  El campo "encontrado" ES el color del LED. La placa no lo decide ni
//  lo podria decidir: no sabe que libros existen. Solo obedece.
//
//  Se separa cortando por la barra |. Por eso la computadora saca las
//  barras que pudiera traer un titulo antes de mandarlo: si no,
//  contariamos mal los campos y se correria todo.
// ------------------------------------------------------------
void procesar(char *texto) {

  char *campo[6];
  byte  cuantos = 0;

  campo[cuantos++] = texto;
  for (char *p = texto; *p && cuantos < 6; p++) {
    if (*p == '|') {
      *p = '\0';                 // corta aca: termina el campo anterior
      campo[cuantos++] = p + 1;  // el siguiente empieza en la letra que sigue
    }
  }

  if (cuantos < 6) {
    // Faltan campos. No se adivina ni se completa con lo que haya: un
    // mensaje incompleto se rechaza y se avisa.
    Serial.println("ERR:FORMATO");
    return;
  }

  char  accion     = campo[0][0];
  bool  encontrado = (campo[1][0] == '1');
  char *codigo     = campo[2];
  char *titulo     = campo[3];
  char *precio     = campo[4];
  char *total      = campo[5];

  char fila[17];

  // ---- Pedido cerrado ----
  if (accion == 'C') {
    apagarLuces();
    luzEncendida = false;
    tone(PIN_ZUMBADOR, BIP_CIERRE_TONO, BIP_CIERRE_DURA);
    snprintf(fila, sizeof(fila), "$%s", total);
    mostrar("Pedido cerrado", fila);
    totalPendiente = false;
    Serial.println("OK");
    return;
  }

  // ---- Un escaneo ----
  if (encontrado) {
    digitalWrite(PIN_VERDE, HIGH);
    digitalWrite(PIN_ROJO,  LOW);
    // tone() NO frena el programa: deja sonando el pin por su cuenta y
    // sigue de largo. Por eso se puede escanear el libro siguiente sin
    // esperar a que termine el bip. Con delay() habria que esperar.
    tone(PIN_ZUMBADOR, BIP_OK_TONO, BIP_OK_DURACION);
    snprintf(fila, sizeof(fila), "$%s", precio);
    mostrar(titulo, fila);          // titulo arriba, precio abajo
  } else {
    digitalWrite(PIN_VERDE, LOW);
    digitalWrite(PIN_ROJO,  HIGH);
    tone(PIN_ZUMBADOR, BIP_MAL_TONO, BIP_MAL_DURACION);
    // Se muestra el codigo, no el titulo: si no esta en la base no hay
    // titulo que mostrar, y el codigo es lo unico que sirve para buscar
    // el problema (etiqueta mal pegada, ejemplar sin cargar).
    mostrar("NO ENCONTRADO", codigo);
  }

  // Arranca la cuenta para que el LED se apague solo.
  luzEncendida = true;
  momentoLuz   = millis();

  // ---- En modo venta arrancamos el reloj del total ----
  if (accion == 'V') {
    strncpy(totalGuardado, total, sizeof(totalGuardado) - 1);
    totalGuardado[sizeof(totalGuardado) - 1] = '\0';
    momentoUltimo  = millis();
    totalPendiente = true;   // si en 3 segundos no llega nada, sale el total
  } else {
    // En modo scanner no hay total: la ficha del libro queda en pantalla.
    totalPendiente = false;
  }

  Serial.println("OK");
}


// ------------------------------------------------------------
//  mostrar() - los dos renglones de la pantalla
// ------------------------------------------------------------
//  ARRIBA va el titulo del libro, ABAJO el precio. Siempre en ese orden:
//  el titulo es para el cliente, que quiere confirmar que le estan
//  cobrando SU libro, y el precio es el dato que se discute. Que esten
//  siempre en el mismo lugar es lo que permite leerlos de un vistazo.
//
//  La pantalla tiene 16 columnas y 2 filas. Un titulo mas largo que 16
//  letras se corta: no hay donde ponerlo. Lo cortamos nosotros y no
//  dejamos que la pantalla haga cualquier cosa (algunas siguen
//  escribiendo en la nada, otras pasan al renglon de abajo y te tapan el
//  precio).
//
//  Lo mismo se dibuja en el Monitor Serial, con su marco. No es un
//  adorno: mientras la pantalla no este cableada, es la unica forma de
//  ver si el programa anda, y ademas deja el historial de todo lo que
//  paso, que en la LCD se pierde con el mensaje siguiente. Si molesta,
//  se borran las tres lineas del final de esta funcion y no pasa nada.
// ------------------------------------------------------------
void mostrar(const char *arriba, const char *abajo) {

  lcd.clear();                 // borrar antes: si no, queda mezclado con
                               // lo anterior cuando el texto nuevo es mas
                               // corto que el que estaba
  lcd.setCursor(0, 0);         // columna 0, fila 0: el renglon de arriba
  for (byte i = 0; i < 16 && arriba[i] != '\0'; i++) lcd.write(arriba[i]);

  lcd.setCursor(0, 1);         // columna 0, fila 1: el de abajo
  for (byte i = 0; i < 16 && abajo[i] != '\0'; i++) lcd.write(abajo[i]);

  // Y el espejo en el Monitor Serial.
  Serial.println("+----------------+");
  renglon(arriba);
  renglon(abajo);
  Serial.println("+----------------+");
}


// Dibuja un renglon con el ancho exacto de la pantalla: lo que entra, y
// espacios hasta completar los 16. Asi en el monitor se ve igual de
// cortado que en la LCD.
void renglon(const char *texto) {
  Serial.print('|');

  byte i = 0;
  while (i < 16 && texto[i] != '\0') {   // lo que entra
    Serial.write(texto[i]);
    i++;
  }
  while (i < 16) {                       // y espacios hasta completar
    Serial.write(' ');
    i++;
  }

  Serial.println('|');
}


void apagarLuces() {
  digitalWrite(PIN_VERDE, LOW);
  digitalWrite(PIN_ROJO,  LOW);
}
