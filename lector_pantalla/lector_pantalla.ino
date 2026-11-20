// Arduino Uno + LCD 16x2 I2C. Biblioteca: hd44780, de Bill Perry.
// GND->GND, VCC->5V, SDA->A4, SCL->A5 (LCD tipica de 5V).
#include <Wire.h>
#include <hd44780.h>
#include <hd44780ioClass/hd44780_I2Cexp.h>

hd44780_I2Cexp lcd; // Detecta direccion y distribucion del adaptador.
char codigo[17];
byte usados = 0;
bool invalido = false;
bool pantallaLista = false;

void setup() {
  Serial.begin(9600);
  pantallaLista = (lcd.begin(16, 2) == 0);
  if (pantallaLista) {
    lcd.backlight();
    lcd.print("Lector listo");
    lcd.setCursor(0, 1);
    lcd.print("Escanee un libro");
    Serial.println("READY");
  } else {
    Serial.println("ERR:LCD");
  }
}

void loop() {
  while (Serial.available()) {
    char c = Serial.read();
    if (c == '\r') continue;
    if (c == '\n') {
      if (invalido || usados == 0) {
        Serial.println("ERR:CODIGO");
      } else if (!pantallaLista) {
        Serial.println("ERR:LCD");
      } else {
        codigo[usados] = '\0';
        lcd.clear();
        lcd.print("Codigo recibido:");
        lcd.setCursor(0, 1);
        lcd.print(codigo);
        Serial.print("OK:");
        Serial.println(codigo);
      }
      usados = 0;
      invalido = false;
    } else if (c >= '0' && c <= '9' && usados < 16) {
      codigo[usados++] = c;
    } else {
      invalido = true; // No mostrar entradas truncadas o no numericas.
    }
  }
}
