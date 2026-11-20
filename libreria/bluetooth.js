/* ============================================================
   EL TRANSPORTE HACIA EL ARDUINO, POR BLUETOOTH
   ============================================================

   Hace una sola cosa: mandarle una linea de texto al Arduino. No busca
   libros, no calcula totales, no decide nada. Todo eso lo hace PHP.

   Es el reemplazo de arduino.js, que hacia lo mismo por el cable USB
   con Web Serial. Se cambio por Bluetooth por una razon concreta: la
   computadora tiene un solo puerto USB libre, y mientras lo ocupaba el
   Arduino, el lector de codigos quedaba desenchufado. Con el Arduino
   por Bluetooth y alimentado de un transformador, ese USB queda para el
   lector.

   POR QUE ESTO NO ROMPE LA REGLA DEL STACK DEL PROYECTO

   El sistema se escribe en HTML, CSS, PHP y SQL. JavaScript entra solo
   donde no hay alternativa, y este es el caso: PHP corre en el servidor
   y el Bluetooth esta en la maquina del usuario. JavaScript es el unico
   que corre del lado del aparato.
   (Ver explicaciones/01-php-javascript-y-el-arduino.md)

   Y vive en la pagina de AFUERA, que no se recarga nunca, porque la
   conexion Bluetooth se cortaria con cada recarga igual que se cortaba
   el puerto serie.
   (Ver explicaciones/02-recarga-y-puerto-serie.md)

   EL MODULO: un HC-08, que es BLE (Bluetooth de bajo consumo). Su canal
   serie es la caracteristica FFE1 del servicio FFE0: lo que se escribe
   ahi sale por su pata TXD hacia el pin 10 del Arduino.
   ============================================================ */

/* La direccion del canal serie del modulo. No son numeros inventados:
   son los que publica el propio HC-08 y se pueden leer con cualquier
   app de BLE. */
const SERVICIO      = 0xffe0;
const CARACTERISTICA = 0xffe1;

/* De a cuantos bytes se puede escribir. BLE manda paquetes chicos: si se
   le pasa una linea mas larga, se corta sola y el Arduino recibe media
   linea, que es peor que no recibir nada. Por eso se parte a mano. */
const PEDAZO = 20;

let dispositivo = null;
let canal       = null;

function estado(texto, clase) {
    const caja = document.getElementById('estado');
    caja.textContent = texto;
    caja.className = 'estado ' + (clase || '');
}

/* ------------------------------------------------------------
   CONECTAR - se hace UNA vez, al empezar la jornada.
   ------------------------------------------------------------
   El navegador OBLIGA a que sea el usuario el que elija el aparato con
   un clic, y esta bien que sea asi: si una pagina pudiera conectarse
   sola a lo que tengas cerca, cualquier sitio que visitaras podria
   hablarle a tus aparatos.
   ------------------------------------------------------------ */
async function conectar() {
    if (!navigator.bluetooth) {
        estado('Este navegador no puede usar Bluetooth. Abri la pagina en Chrome o Edge de escritorio.', 'mal');
        return;
    }

    try {
        estado('Elegi el HC-08 en la lista que abrio el navegador.', '');

        dispositivo = await navigator.bluetooth.requestDevice({
            // Se filtra por nombre y no por servicio porque el HC-08 no
            // anuncia sus servicios: hay que conectarse para verlos.
            filters: [{ namePrefix: 'HC-08' }],
            optionalServices: [SERVICIO]
        });

        // Si el modulo se apaga o se va de rango, avisamos en pantalla.
        dispositivo.addEventListener('gattserverdisconnected', () => {
            canal = null;
            document.getElementById('conectar').disabled = false;
            estado('Se corto el Bluetooth. Pulsa "Conectar Arduino" para reconectar.', 'mal');
        });

        const servidor = await dispositivo.gatt.connect();
        const servicio = await servidor.getPrimaryService(SERVICIO);
        canal          = await servicio.getCharacteristic(CARACTERISTICA);

        document.getElementById('conectar').disabled = true;
        estado('Arduino conectado por Bluetooth. Ya se puede escanear.', 'ok');

        // El cursor quedo en el boton, y un boton desactivado lo suelta:
        // el primer codigo escaneado no iria a ningun lado. Se lo
        // devolvemos al campo de escaneo, que esta adentro del iframe.
        enfocarElCampo();

    } catch (error) {
        // Si el usuario cierra la lista sin elegir nada, tambien cae aca.
        estado('No se pudo conectar: ' + error.message, 'mal');
    }
}

/* ------------------------------------------------------------
   alArduino - la llama la pagina de adentro (el iframe) en cada escaneo.
   ------------------------------------------------------------
   Tiene que ser una funcion global, porque el iframe la busca como
   window.parent.alArduino

   El nombre quedo igual que cuando esto iba por el cable USB, a
   proposito: asi el resto del sistema no se enteró del cambio. Lo unico
   que se reemplazo fue el transporte.
   ------------------------------------------------------------ */
async function alArduino(linea) {
    if (!canal) {
        estado('Escaneo registrado, pero el Arduino no esta conectado. Pulsa "Conectar Arduino".', 'mal');
        return;
    }

    try {
        // El Arduino lee hasta encontrar un salto de linea: por eso el \n.
        const bytes = new TextEncoder().encode(linea + '\n');

        // Y aca la parte importante: de a 20 bytes. Un titulo largo como
        // "El nombre de la rosa|850.00|1" pasa los 20, asi que se manda
        // en dos veces. El modulo los va sacando en orden, y para el
        // Arduino es un chorro de letras continuo: no nota la diferencia.
        for (let i = 0; i < bytes.length; i += PEDAZO) {
            const trozo = bytes.slice(i, i + PEDAZO);
            if (canal.writeValueWithoutResponse) {
                await canal.writeValueWithoutResponse(trozo);
            } else {
                await canal.writeValue(trozo);   // navegadores mas viejos
            }
        }

    } catch (error) {
        estado('No se pudo escribir por Bluetooth: ' + error.message, 'mal');
        canal = null;
        document.getElementById('conectar').disabled = false;
    }
}

/* El campo esta en otra pagina (la del iframe "panel"), por eso se lo
   busca a traves de frames.panel. Si justo se esta recargando, no esta:
   no pasa nada, la recarga misma lo enfoca. */
function enfocarElCampo() {
    const campo = window.frames.panel && window.frames.panel.document.getElementById('codigo');
    if (campo) campo.focus();
}

document.getElementById('conectar').addEventListener('click', conectar);
