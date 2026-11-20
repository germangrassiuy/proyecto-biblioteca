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
    // Si quedo agarrado un puerto de una conexion anterior que fallo, se
    // suelta primero: si no, al abrirlo de nuevo dice "ocupado".
    await soltarPuerto();
    seDesenchufo = false;

    try {
        puerto = await navigator.serial.requestPort();
        await puerto.open({ baudRate: 9600 });

        escritor = puerto.writable.getWriter();
        document.getElementById('conectar').disabled = true;

        // El cursor quedo en el boton, y un boton desactivado lo suelta:
        // el primer codigo escaneado no iria a ningun lado. Se lo
        // devolvemos al campo de escaneo, que esta adentro del iframe.
        enfocarElCampo();

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
        if (!seDesenchufo) {
            estado('Se corto la conexion con el Arduino (' + error.message + '). Pulsa "Conectar Arduino".', 'mal');
        }
        soltarPuerto();
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
        if (!seDesenchufo) {
            estado('No se pudo mandar al Arduino (' + error.message + '). Pulsa "Conectar Arduino".', 'mal');
        }
        soltarPuerto();
    }
}

/* ------------------------------------------------------------
   SOLTAR EL PUERTO - cuando algo falla, se suelta TODO.
   ------------------------------------------------------------
   Si se corta la conexion y la pagina solo "se olvida" del puerto, el
   navegador lo sigue teniendo abierto por dentro. Al tocar "Conectar"
   de nuevo, Windows contesta que el puerto esta OCUPADO... ocupado por
   esta misma pagina. Por eso hay que cerrarlo de verdad, y en orden:
   primero se sueltan el lector y el escritor, despues se cierra el
   puerto (con alguno de los dos agarrado, close() no deja).
   Cada paso va en su try: si el aparato ya no existe, fallan, y no
   importa, lo que importa es que el puerto quede libre.
   ------------------------------------------------------------ */
async function soltarPuerto() {
    const viejo = puerto;
    puerto = null;
    if (lector) {
        try { await lector.cancel(); } catch (e) {}
        try { lector.releaseLock(); } catch (e) {}
        lector = null;
    }
    if (escritor) {
        try { escritor.releaseLock(); } catch (e) {}
        escritor = null;
    }
    if (viejo) {
        try { await viejo.close(); } catch (e) {}
    }
    document.getElementById('conectar').disabled = false;
}

/* ------------------------------------------------------------
   CUANDO WINDOWS DEJA DE VER EL ARDUINO
   ------------------------------------------------------------
   El navegador avisa cuando un aparato USB desaparece. No es lo mismo
   que un error del programa, y conviene decirlo distinto: si pasa
   justo al escanear, casi siempre es FALTA DE CORRIENTE. El Arduino
   prende el LED, el zumbador y la pantalla al mismo tiempo; si eso
   pide mas de lo que da el USB (o un hub sin fuente compartido con el
   lector), la placa se apaga un instante y Windows la pierde.
   ------------------------------------------------------------ */
let seDesenchufo = false;

navigator.serial && navigator.serial.addEventListener('disconnect', () => {
    seDesenchufo = true;
    estado('Windows dejo de ver el Arduino por USB: se desenchufo, o se apago un instante por falta de corriente. Pulsa "Conectar Arduino".', 'mal');
    soltarPuerto();
});

navigator.serial && navigator.serial.addEventListener('connect', () => {
    if (!escritor) estado('El Arduino volvio a aparecer. Pulsa "Conectar Arduino".', 'mal');
});

/* El campo esta en otra pagina (la del iframe "panel"), por eso se lo
   busca a traves de frames.panel. Si justo se esta recargando, no esta:
   no pasa nada, la recarga misma lo enfoca. */
function enfocarElCampo() {
    const campo = window.frames.panel && window.frames.panel.document.getElementById('codigo');
    if (campo) campo.focus();
}

document.getElementById('conectar').addEventListener('click', conectar);
