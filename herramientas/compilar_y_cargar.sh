#!/bin/bash
# Compila un programa de Arduino Uno y lo carga en la placa, SIN abrir el
# Arduino IDE. Usa el compilador (avr-gcc) y el cargador (avrdude) que el IDE
# deja instalados en AppData\Local\Arduino15, y la libreria hd44780 en
# Documentos\Arduino\libraries. OPCIONAL: con el Arduino IDE se hace lo mismo.
#
# Uso (desde git-bash):
#   bash compilar_y_cargar.sh ../libreria/arduino/libreria_simple_fisico COM4
#
# Antes de cargar: cerrar la pagina del mostrador (o desconectar el Arduino
# en ella), porque si tiene el puerto abierto avrdude no puede usarlo.
set -e
SK="$(cd "$1" && pwd)"; PUERTO="${2:-COM4}"
NAME=$(basename "$SK")
OUT="$TEMP/libreria_build"
# Las carpetas del Arduino IDE de ESTA computadora. Se toma la version mas
# nueva que haya de cada herramienta, asi no depende de cual se instalo.
A="$(cygpath -u "$LOCALAPPDATA")/Arduino15/packages/arduino"
HW=$(ls -d "$A"/hardware/avr/* | tail -1)
BIN=$(ls -d "$A"/tools/avr-gcc/* | tail -1)/bin
AD=$(ls -d "$A"/tools/avrdude/* | tail -1)
CORE=$HW/cores/arduino
VAR=$HW/variants/standard
WIRE=$HW/libraries/Wire/src
SSER=$HW/libraries/SoftwareSerial/src
HD="$(cygpath -u "$USERPROFILE")/Documents/Arduino/libraries/hd44780"

rm -rf "$OUT"; mkdir -p "$OUT/obj"
DEFS="-mmcu=atmega328p -DF_CPU=16000000L -DARDUINO=10819 -DARDUINO_AVR_UNO -DARDUINO_ARCH_AVR"
INC="-I$CORE -I$VAR -I$WIRE -I$WIRE/utility -I$SSER -I$HD"
CFL="-c -g -Os -w -ffunction-sections -fdata-sections $DEFS $INC"
CXXFL="$CFL -std=gnu++11 -fpermissive -fno-exceptions -fno-threadsafe-statics -Wno-error=narrowing"

# El .ino se convierte en .cpp como hace el IDE: se agrega Arduino.h y los
# prototipos de las funciones definidas despues de loop().
{
  echo '#include <Arduino.h>'
  grep -E '^void [a-zA-Z_]+\(.*\) *\{' "$SK/$NAME.ino" | grep -vE '^void (setup|loop)\(' | sed 's/ *{.*$/;/'
  echo "#line 1 \"$NAME.ino\""
  cat "$SK/$NAME.ino"
} > "$OUT/$NAME.cpp"

n=0
comp() {
  local src="$1"; n=$((n+1)); local o="$OUT/obj/${n}_$(basename "$src").o"
  case "$src" in
    *.c)   "$BIN/avr-gcc" $CFL -std=gnu11 "$src" -o "$o" ;;
    *.S)   "$BIN/avr-gcc" -c -g -x assembler-with-cpp $DEFS $INC "$src" -o "$o" ;;
    *.cpp) "$BIN/avr-g++" $CXXFL "$src" -o "$o" ;;
  esac
}
comp "$OUT/$NAME.cpp"
for f in "$WIRE/Wire.cpp" "$WIRE/utility/twi.c" "$SSER/SoftwareSerial.cpp" "$HD/hd44780.cpp"; do comp "$f"; done
for f in "$CORE"/*.c "$CORE"/*.cpp "$CORE"/*.S; do [ -f "$f" ] && comp "$f"; done

"$BIN/avr-gcc" -w -Os -g -Wl,--gc-sections -mmcu=atmega328p -o "$OUT/$NAME.elf" "$OUT"/obj/*.o -lm
"$BIN/avr-objcopy" -O ihex -R .eeprom "$OUT/$NAME.elf" "$OUT/$NAME.hex"
"$BIN/avr-size" -C --mcu=atmega328p "$OUT/$NAME.elf" | grep -E 'Program|Data'

# avrdude es un programa de Windows: no entiende rutas /c/..., y los ":" de
# C:\ chocan con su sintaxis. Por eso se carga parado en la carpeta.
cd "$OUT"
"$AD/bin/avrdude.exe" -C "$(cygpath -w "$AD/etc/avrdude.conf")" -p atmega328p -c arduino \
  -P "$PUERTO" -b 115200 -D -U "flash:w:$NAME.hex:i" 2>&1 | tail -3
