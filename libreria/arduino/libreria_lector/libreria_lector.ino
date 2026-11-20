// ============================================================
//  LIBRERIA - pantalla y luces del mostrador
// ============================================================
//  Recibe por el puerto serie una linea de texto que le manda la
//  computadora, y hace tres cosas:
//    1. Escribe los datos del libro en la pantalla LCD.
//    2. Prende el LED verde si el libro existe, o el rojo si no.
//    3. En modo venta, si pasan 3 segundos sin recibir nada,
//       muestra solo el total de la compra.
//
//  IMPORTANTE: el Arduino NO busca nada ni suma nada. No tiene la base
//  de datos ni la puede tener. Todo lo decide PHP en la computadora, y
//  aca llega ya masticado. El Arduino solo muestra.
//
//  CABLEADO
//    LCD I2C:   GND->GND   VCC->5V   SDA->A4   SCL->A5
//    LED verde: pata larga -> pin 5, pata corta -> resistencia -> GND
//    LED rojo:  pata larga -> pin 6, pata corta -> resistencia -> GND
//    Zumbador:  pata + -> pin 8, pata - -> GND
//
//  La resistencia (220 o 330 ohm) NO es opcional: sin ella el LED se
//  quema, porque el pin entrega mas corriente de la que el LED aguanta.
//
//  SOBRE EL ZUMBADOR: hay de dos clases y conviene saber cual se tiene.
//    - PASIVO: no suena solo; hay que mandarle la frecuencia. Es el que
//      espera este programa, porque usa tone(pin, frecuencia, duracion)
//      y asi podemos hacer un bip agudo y otro grave.
//    - ACTIVO: trae su propio oscilador y suena siempre igual con solo
//      darle corriente. Con este programa tambien suena, pero los dos
//      bips van a sonar iguales: no puede cambiar de tono.
//  Mientras no haya nada conectado al pin 8, el programa funciona igual
//  y no pasa nada.
//
//  BIBLIOTECA NECESARIA: "hd44780" de Bill Perry, desde el gestor de
//  bibliotecas del Arduino IDE. Detecta sola la direccion del I2C.
// ============================================================

#include <Wire.h>
#include <hd44780.h>
#include <hd44780ioClass/hd44780_I2Cexp.h>

const byte PIN_VERDE     = 5;
const byte PIN_ROJO      = 6;
const byte PIN_ZUMBADOR  = 8;   // el "bip". Todavia sin conectar: no molesta.

// Los dos sonidos. Frecuencia en hercios, duracion en milisegundos.
// Agudo y corto = encontrado. Grave y largo = no encontrado. Se
// distinguen sin mirar la pantalla, que es justamente para lo que sirve
// un sonido en un mostrador.
const unsigned int BIP_OK_TONO       = 2000;
const unsigned int BIP_OK_DURACION   = 90;
const unsigned int BIP_MAL_TONO      = 400;
const unsigned int BIP_MAL_DURACION  = 400;
const unsigned int BIP_CIERRE_TONO   = 1200;
const unsigned int BIP_CIERRE_DURA   = 180;

// Cuanto esperamos, sin recibir nada, antes de mostrar el total.
const unsigned long ESPERA_DEL_TOTAL = 3000;   // milisegundos

// Cuanto queda encendido el LED despues de un escaneo.
// Se usa el mismo tiempo que el total para que todo el tablero cambie
// junto: a los 3 segundos se apaga la luz y aparece el total.
const unsigned long DURACION_DE_LA_LUZ = 3000;

hd44780_I2Cexp lcd;
bool pantallaLista = false;

// Donde se va guardando la linea que llega, letra por letra.
char linea[120];
byte usados     = 0;
bool desbordada = false;

// Estado del modo venta.
bool          totalPendiente = false;  // hay un total esperando para mostrarse
unsigned long momentoUltimo  = 0;      // cuando llego el ultimo mensaje
char          totalGuardado[16];

// Estado de los LED.
bool          luzEncendida = false;
unsigned long momentoLuz   = 0;        // cuando se prendio el LED


void setup() {
  Serial.begin(9600);

  pinMode(PIN_VERDE,    OUTPUT);
  pinMode(PIN_ROJO,     OUTPUT);
  pinMode(PIN_ZUMBADOR, OUTPUT);
  apagarLuces();

  pantallaLista = (lcd.begin(16, 2) == 0);

  if (pantallaLista) {
    lcd.backlight();
    mostrar("Libreria lista", "Escanee un libro");
    Serial.println("READY");
  } else {
    // Sin pantalla el resto igual funciona: los LED no dependen del LCD.
    Serial.println("ERR:LCD");
  }

  totalGuardado[0] = '\0';
}


void loop() {

  // ---- 1. Juntar lo que llega, hasta el salto de linea ----
  while (Serial.available()) {
    char c = Serial.read();

    if (c == '\r') continue;             // Windows manda \r\n; el \r sobra

    if (c == '\n') {                     // llego el final del mensaje
      linea[usados] = '\0';
      if (desbordada) {
        Serial.println("ERR:LARGO");
      } else if (usados > 0) {
        procesar(linea);
      }
      usados     = 0;
      desbordada = false;

    } else if (usados < sizeof(linea) - 1) {
      linea[usados++] = c;

    } else {
      // Mensaje mas largo de lo que entra. Se marca y se descarta
      // entero: mejor avisar que mostrar un dato cortado por la mitad.
      desbordada = true;
    }
  }

  // ---- 2. El reloj de los 3 segundos ----
  // millis() devuelve cuantos milisegundos pasaron desde que arranco la
  // placa. Restandole el momento del ultimo mensaje sabemos cuanto hace
  // que nadie escanea, sin frenar el programa como haria un delay().
  if (totalPendiente && (millis() - momentoUltimo >= ESPERA_DEL_TOTAL)) {
    char fila[17];
    snprintf(fila, sizeof(fila), "$%s", totalGuardado);
    mostrar("TOTAL COMPRA", fila);
    totalPendiente = false;
  }

  // ---- 3. El LED se apaga solo ----
  // Mismo mecanismo que el total: no se usa delay(), porque delay()
  // congela el programa entero y la placa dejaria de escuchar el puerto
  // mientras espera. Con millis() solo miramos el reloj al pasar.
  if (luzEncendida && (millis() - momentoLuz >= DURACION_DE_LA_LUZ)) {
    apagarLuces();
    luzEncendida = false;
  }
}


// ------------------------------------------------------------
//  procesar() - entender el mensaje que mando la computadora
// ------------------------------------------------------------
//  El formato es:   accion|encontrado|codigo|titulo|precio|total
//  Por ejemplo:     V|1|V0001|Cien anios|690.00|1540.00
//
//  accion:     S = modo scanner, V = modo venta, C = pedido cerrado
//  encontrado: 1 = el libro existe, 0 = no esta en la base
//
//  Se separa cortando por la barra |. Por eso la computadora se encarga
//  de sacar las barras que pudiera tener un titulo antes de mandarlo:
//  si no, contariamos mal los campos.
// ------------------------------------------------------------
void procesar(char *texto) {

  char *campo[6];
  byte  cuantos = 0;

  campo[cuantos++] = texto;
  for (char *p = texto; *p && cuantos < 6; p++) {
    if (*p == '|') {
      *p = '\0';                 // corta aca: termina el campo anterior
      campo[cuantos++] = p + 1;  // y el siguiente empieza en la letra que sigue
    }
  }

  if (cuantos < 6) {
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
    mostrar(titulo, fila);
  } else {
    digitalWrite(PIN_VERDE, LOW);
    digitalWrite(PIN_ROJO,  HIGH);
    tone(PIN_ZUMBADOR, BIP_MAL_TONO, BIP_MAL_DURACION);
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
    totalPendiente = true;   // si en 3 segundos no llega nada, se muestra
  } else {
    // En modo scanner no hay total: la ficha del libro queda en pantalla.
    totalPendiente = false;
  }

  Serial.println("OK");
}


// ------------------------------------------------------------
//  mostrar() - escribir dos renglones en la pantalla
// ------------------------------------------------------------
//  La pantalla tiene 16 columnas y 2 filas. Si el titulo es mas largo
//  que 16 letras, se corta: no hay donde ponerlo. Cortamos nosotros y
//  no dejamos que la pantalla haga cualquier cosa.
// ------------------------------------------------------------
void mostrar(const char *arriba, const char *abajo) {
  if (!pantallaLista) return;

  lcd.clear();

  lcd.setCursor(0, 0);
  for (byte i = 0; i < 16 && arriba[i] != '\0'; i++) lcd.write(arriba[i]);

  lcd.setCursor(0, 1);
  for (byte i = 0; i < 16 && abajo[i] != '\0'; i++) lcd.write(abajo[i]);
}


void apagarLuces() {
  digitalWrite(PIN_VERDE, LOW);
  digitalWrite(PIN_ROJO,  LOW);
}
