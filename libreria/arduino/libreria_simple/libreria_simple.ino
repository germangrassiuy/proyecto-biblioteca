// LIBRERIA - version simple
//
// Recibe por el Monitor Serial tres datos y hace tres cosas: escribe los
// dos renglones de la pantalla, prende un LED y toca un bip.
//
//     texto|precio|existe
//
//     texto    lo que va arriba en la pantalla
//     precio   lo que va abajo, con el $ adelante
//     existe   1 = el libro esta en la base, 0 = no esta
//
// Ejemplos para pegar en el Monitor Serial:
//
//     Rayuela|1320.00|1        el libro existe:  verde y bip corto
//     NO ENCONTRADO|0|0        no existe:        rojo y bip largo
//     Total|1520.00|1          el total de la compra
//
// El Arduino NO busca el libro, NO suma y NO mide el tiempo. No podria:
// no tiene la base de datos. La computadora busca, suma, decide cuando
// mostrar el total, y le manda el resultado ya masticado. Por eso los
// tres campos alcanzan: mandar la palabra "Total" con la suma es
// exactamente lo mismo que mandar un titulo con su precio.
//
// Y por eso la pantalla no tiene ningun "if": los dos renglones se
// escriben siempre igual, venga lo que venga. Lo unico que cambia segun
// el caso es la luz y el sonido.
//
// CABLEADO
//     LCD I2C: GND a GND, VCC a 5V, SDA a SDA, SCL a SCL
//     LED verde: pin 5, con resistencia de 220 ohm a GND
//     LED rojo:  pin 6, con resistencia de 220 ohm a GND
//     Zumbador:  pin 8, la otra pata a GND

#include <Adafruit_LiquidCrystal.h>

Adafruit_LiquidCrystal lcd(0);   // el 0 es la direccion I2C de la pantalla


void setup() {
  Serial.begin(9600);

  pinMode(5, OUTPUT);   // LED verde
  pinMode(6, OUTPUT);   // LED rojo

  lcd.begin(16, 2);     // 16 columnas y 2 filas
  lcd.print("Libreria lista");
}


void loop() {

  if (Serial.available()) {

    // Un campo por linea. readStringUntil() lee hasta encontrar la barra
    // y la descarta, asi que la lectura siguiente arranca en el campo
    // que sigue.
    String texto  = Serial.readStringUntil('|');
    String precio = Serial.readStringUntil('|');
    String existe = Serial.readStringUntil('\n');

    // La pantalla: siempre lo mismo, sin preguntar nada.
    lcd.clear();
    lcd.setCursor(0, 0);          // columna 0, fila de arriba
    lcd.print(texto);
    lcd.setCursor(0, 1);          // columna 0, fila de abajo
    lcd.print("$" + precio);

    // La luz y el sonido: aca si hay que decidir.
    if (existe == "1") {
      digitalWrite(5, HIGH);      // prende el verde
      digitalWrite(6, LOW);       // y apaga el rojo
      tone(8, 2000, 90);          // bip corto y agudo

    } else {
      digitalWrite(5, LOW);
      digitalWrite(6, HIGH);      // prende el rojo
      tone(8, 400, 400);          // bip largo y grave
    }
  }
}
