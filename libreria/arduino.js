/* ============================================================
   EL UNICO ARCHIVO JAVASCRIPT DEL PROYECTO
   ============================================================

   Hace una sola cosa: mandarle una linea de texto al Arduino por el
   puerto serie. No busca libros, no calcula totales, no decide nada.
   Todo eso lo hace PHP.

   Esta aca, y no en PHP, por una razon concreta: PHP se ejecuta en el
   servidor y el cable USB esta en la maquina del usuario. JavaScript es
   el unico que corre del lado del cable.
   (Ver explicaciones/01-php-javascript-y-el-arduino.md)

   Y vive en la pagina de AFUERA, que no se recarga nunca, porque cada
   vez que se abre el puerto el Arduino se reinicia y tarda 2 segundos
   en volver.
   (Ver explicaciones/02-recarga-y-puerto-serie.md)
   ============================================================ */

let puerto   = null;
let escritor = null;
let lector   = null;

function estado(texto, clase) {
    const caja = document.getElementById('estado');
    caja.textContent = texto;
    caja.className = 'estado ' + (clase || '');
}

/* ------------------------------------------------------------
   CONECTAR - se hace UNA vez, al empezar la jornada.
   ------------------------------------------------------------
   El navegador OBLIGA a que sea el usuario el que elija el puerto con
   un clic. No se puede saltear desde el codigo, y esta bien que sea
   asi: si una pagina pudiera abrir sola los puertos USB, cualquier
   sitio que visitaras tendria acceso a lo que tengas enchufado.
   ------------------------------------------------------------ */
async function conectar() {
    if (!('serial' in navigator)) {
        estado('Este navegador no puede usar el puerto serie. Abri la pagina en Chrome o Edge de escritorio.', 'mal');
        return;
    }
    try {
        puerto = await navigator.serial.requestPort();
        await puerto.open({ baudRate: 9600 });

        escritor = puerto.writable.getWriter();
        document.getElementById('conectar').disabled = true;

        // Al abrir el puerto, el Uno se reinicia y tarda unos 2 segundos
        // en arrancar. Cuando termina manda READY. Lo escuchamos para
        // avisarle al operador que ya puede escanear.
        estado('Conectando... el Arduino se esta reiniciando (2 segundos).', '');
        escuchar();

    } catch (error) {
        estado('No se pudo conectar: ' + error.message + '. Elegi el puerto del Arduino.', 'mal');
    }
}

/* ------------------------------------------------------------
   ESCUCHAR - lee lo que el Arduino contesta.
   ------------------------------------------------------------
   No es imprescindible para que funcione, pero sin esto el operador no
   tiene forma de saber si la pantalla LCD esta rota: el Arduino avisa
   con ERR:LCD y nadie se enteraria.
   ------------------------------------------------------------ */
async function escuchar() {
    lector = puerto.readable.getReader();
    const decodificador = new TextDecoder();
    let pendiente = '';

    try {
        while (true) {
            const { value, done } = await lector.read();
            if (done) break;

            pendiente += decodificador.decode(value, { stream: true });

            // El Arduino manda una respuesta por linea. Vamos cortando
            // por el salto de linea y procesando de a una.
            let corte;
            while ((corte = pendiente.indexOf('\n')) >= 0) {
                const linea = pendiente.slice(0, corte).trim();
                pendiente = pendiente.slice(corte + 1);

                if (linea === 'READY')        estado('Arduino conectado. Ya se puede escanear.', 'ok');
                else if (linea === 'OK')      estado('Arduino conectado. Ya se puede escanear.', 'ok');
                else if (linea === 'ERR:LCD') estado('El Arduino funciona pero NO detecta la pantalla LCD. Revisa el cableado.', 'mal');
            }
        }
    } catch (error) {
        estado('Se corto la conexion con el Arduino: ' + error.message, 'mal');
    }
}

/* ------------------------------------------------------------
   alArduino - la llama la pagina de adentro (el iframe) en cada escaneo.
   ------------------------------------------------------------
   Tiene que ser una funcion global, porque el iframe la busca como
   window.parent.alArduino
   ------------------------------------------------------------ */
async function alArduino(linea) {
    if (!escritor) {
        estado('Escaneo registrado, pero el Arduino no esta conectado. Pulsa "Conectar Arduino".', 'mal');
        return;
    }
    try {
        // El Arduino lee hasta encontrar un salto de linea: por eso el \n.
        await escritor.write(new TextEncoder().encode(linea + '\n'));
    } catch (error) {
        estado('No se pudo escribir en el Arduino: ' + error.message, 'mal');
        escritor = null;
        document.getElementById('conectar').disabled = false;
    }
}

document.getElementById('conectar').addEventListener('click', conectar);
