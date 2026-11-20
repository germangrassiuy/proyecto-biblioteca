// LIBRERIA - version simple, para el ARDUINO FISICO
//
// Recibe tres datos y hace tres cosas: escribe los dos renglones de la
// pantalla, prende un LED y toca un bip.
//
//     texto|precio|existe
//     Rayuela|1320.00|1
//
//     texto    lo que va arriba en la pantalla
//     precio   lo que va abajo, con el $ adelante
//     existe   1 = el libro esta en la base, 0 = no esta
//
// POR DONDE LE LLEGAN LOS DATOS
//
// Por los dos lados, y el programa no distingue:
//
//   - Por el modulo BLUETOOTH (HC-08), en los pines 2 y 3.
//   - Por el cable USB, escribiendo en el Monitor Serial.
//
// El que tenga algo para decir, habla. Los dos son puertos serie, o sea
// que mandan el texto letra por letra, asi que se leen igual.
//
// POR QUE IMPORTA EL BLUETOOTH: la computadora tiene un solo USB libre.
// Si el Arduino recibe por Bluetooth y se alimenta de un cargador, ese
// USB queda libre para enchufar el lector de codigos de barras.
//
// El Arduino NO busca el libro, NO suma y NO mide el tiempo. No podria:
// no tiene la base de datos. La computadora busca, suma, decide cuando
// mostrar el total, y le manda el resultado ya masticado.
//
// CABLEADO
//     LCD I2C:   GND a GND, VCC a 5V, SDA a SDA, SCL a SCL
//                (SDA y SCL tambien son A4 y A5: son los mismos pines)
//     LED verde: pin 12, con resistencia de 220 ohm a GND
//     LED rojo:  pin 10, con resistencia de 220 ohm a GND
//     Zumbador:  pin 7, la otra pata a GND
//     HC-08:     VCC a 5V (esta plaquita acepta de 3,6 a 6 V)
//                GND a GND
//                TXD al pin 2
//                RXD SIN CONECTAR  <-- a proposito, ver abajo
//
// Los numeros de pin estan escritos UNA sola vez, en las constantes de
// abajo (PIN_VERDE, PIN_ROJO...). Si se recablea la placa, se cambia
// ahi y nada mas. Escritos sueltos por todo el programa, es facil
// cambiar cuatro y olvidarse del quinto.
//
// POR QUE EL RXD DEL MODULO QUEDA SUELTO
//
// Porque el dato viaja en un solo sentido: el celular o la PC escriben,
// el modulo lo saca por su TXD y el Arduino lo lee. El Arduino no tiene
// nada que contestarle.
//
// Y de paso evita un problema: la pata RXD del modulo trabaja a 3,3 V y
// el Arduino manda 5 V, asi que ese cable necesitaria dos resistencias
// haciendo de divisor de tension. Si algun dia hay que contestarle,
// hay que ponerlas. Al reves no hace falta nada: 3,3 V entra perfecto
// como "alto" en un pin de 5 V.
//
// POR QUE EN LOS PINES 2 Y 3 Y NO EN EL 0 Y EL 1
//
// El 0 y el 1 son el puerto serie de verdad del chip, pero son los
// mismos que usa el cable USB: con el modulo colgado ahi se pelean, y
// encima se complica cargar programas. SoftwareSerial imita un puerto
// serie en dos pines cualesquiera. Es mas lento y consume mas, pero para
// 9600 baudios alcanza de sobra y deja el USB libre.
//
// LIBRERIA DE LA PANTALLA: "hd44780" de Bill Perry. Es la que sirve en la
// placa fisica, y no es la misma que en Tinkercad. La de Tinkercad
// (Adafruit LiquidCrystal) esta hecha para el modulo de Adafruit, que
// lleva un chip MCP23008; los modulos genericos llevan un PCF8574.
// hd44780 detecta sola cual de los dos es.

#include <Wire.h>
#include <hd44780.h>
#include <hd44780ioClass/hd44780_I2Cexp.h>
#include <SoftwareSerial.h>

const int PIN_VERDE    = 12;
const int PIN_ROJO     = 10;
const int PIN_ZUMBADOR = 7;
const int PIN_BT_RX    = 2;   // escucha: aca llega el TXD del HC-08
const int PIN_BT_TX    = 3;   // hablaria: queda sin usar (RXD suelto)

hd44780_I2Cexp lcd;        // sin direccion: la busca sola en el bus I2C

SoftwareSerial bt(PIN_BT_RX, PIN_BT_TX);

const bool MODO_PRUEBA = false;   // true = se manda 4 libros solo, en bucle


void setup() {
  Serial.begin(9600);      // el USB, para avisar lo que pasa
  bt.begin(9600);          // el modulo HC-08 viene de fabrica a 9600

  pinMode(PIN_VERDE, OUTPUT);
  pinMode(PIN_ROJO,  OUTPUT);

  // begin() devuelve 0 si encontro la pantalla. Si devuelve otra cosa,
  // el cable I2C esta flojo o el modulo no contesta: conviene avisar por
  // el puerto serie, porque una pantalla muda no dice por que esta muda.
  if (lcd.begin(16, 2) != 0) Serial.println("ERR:LCD");

  lcd.backlight();         // en la placa fisica la luz de fondo SI hay que
                           // prenderla: no arranca prendida como en Tinkercad
  lcd.print("Libreria lista");
  Serial.println("READY");

  delay(1500);             // para alcanzar a leer el cartel de arranque
}


void loop() {

  // ---- Modo prueba: cuatro libros, uno atras del otro, para siempre ----
  //
  // No hace falta escribir ningun bucle: loop() ya se repite solo. Aca
  // delay() no molesta, porque mientras se mira la pantalla no hay nada
  // mas que hacer. En el modo de abajo no se usa: ahi la placa tiene que
  // estar escuchando los puertos, y delay() la dejaria sorda.
  if (MODO_PRUEBA) {
    mostrar("Rayuela", "1320.00", true);            delay(2500);
    mostrar("Cien anos de sol", "450.00", true);    delay(2500);
    mostrar("NO ENCONTRADO", "0", false);           delay(2500);
    mostrar("Total", "1770.00", true);              delay(2500);
    return;
  }

  // ---- De donde llego algo: del Bluetooth o del USB ----
  //
  // Stream es lo que tienen en comun los dos puertos: es la parte de
  // "por aca entra y sale texto". Guardando en una variable cual de los
  // dos hablo, el codigo de abajo se escribe UNA vez y sirve para los
  // dos. Si no, habria dos copias del mismo bloque, y arreglar algo en
  // una y olvidarse de la otra es cuestion de tiempo.
  Stream *puerto = NULL;

  if (bt.available())          puerto = &bt;
  else if (Serial.available()) puerto = &Serial;

  if (puerto == NULL) return;  // no llego nada: a dar otra vuelta

  // Un campo por vez. readStringUntil() lee hasta encontrar la barra y la
  // descarta, asi que la lectura siguiente arranca en el campo que sigue.
  String texto  = puerto->readStringUntil('|');
  String precio = puerto->readStringUntil('|');
  String existe = puerto->readStringUntil('\n');

  // El Monitor Serial puede agregar un retorno de carro ademas del salto
  // de linea, y las apps de Bluetooth tambien. Ese caracter invisible se
  // pega al ultimo dato: existe valdria "1\r", que NO es igual a "1", y
  // el LED verde no prenderia nunca. trim() lo limpia.
  texto.trim();
  precio.trim();
  existe.trim();

  if (texto.length() == 0) return;   // linea vacia: no hay nada que mostrar

  // Eco por el USB de lo que se entendio. No es un adorno: es la unica
  // forma de saber si el problema esta en el modulo, en el cable o en el
  // programa cuando algo no aparece en la pantalla.
  Serial.print("recibi: [");
  Serial.print(texto);
  Serial.print("] [");
  Serial.print(precio);
  Serial.print("] [");
  Serial.print(existe);
  Serial.println("]");

  mostrar(texto, precio, existe == "1");
}


// ------------------------------------------------------------
//  mostrar() - los dos renglones, la luz y el bip
// ------------------------------------------------------------
//  Esta en una funcion aparte porque los tres caminos (modo prueba,
//  Bluetooth y USB) hacen exactamente lo mismo con los datos: lo unico
//  que cambia es de donde salieron.
// ------------------------------------------------------------
void mostrar(String texto, String precio, bool existe) {

  // La pantalla: siempre lo mismo, sin preguntar nada.
  lcd.clear();
  lcd.setCursor(0, 0);          // columna 0, fila de arriba
  lcd.print(texto);
  lcd.setCursor(0, 1);          // columna 0, fila de abajo
  lcd.print("$" + precio);

  // La luz y el sonido: aca si hay que decidir.
  if (existe) {
    digitalWrite(PIN_VERDE, HIGH);      // prende el verde
    digitalWrite(PIN_ROJO,  LOW);       // y apaga el rojo
    tone(PIN_ZUMBADOR, 2000, 90);       // bip corto y agudo

  } else {
    digitalWrite(PIN_VERDE, LOW);
    digitalWrite(PIN_ROJO,  HIGH);      // prende el rojo
    tone(PIN_ZUMBADOR, 400, 400);       // bip largo y grave
  }
}
