<?php
// ============================================================
//  MOSTRADOR - la pantalla de trabajo del local
// ============================================================
//  ESTA PAGINA NO SE RECARGA NUNCA. Es a proposito.
//
//  Adentro tiene un <iframe> con escaneo.php, y ESE es el que se
//  recarga con cada escaneo. Asi la conexion con el Arduino, que se abre
//  aca afuera, queda abierta toda la jornada.
//
//  Si el escaneo recargara esta pagina, la conexion se cortaria y habria
//  que volver a hacerla en cada libro. Cuando esto iba por el cable USB
//  eso costaba 1,97 segundos MEDIDOS por libro, porque cada apertura del
//  puerto reiniciaba al Arduino. Ahora va por Bluetooth y el motivo
//  cambio pero no desaparecio: el navegador exige un CLIC del usuario
//  para elegir el aparato, asi que una reconexion por libro seria
//  directamente imposible de usar.
//  (Ver explicaciones/02-recarga-y-puerto-serie.md)
//
//  Por eso la cabecera se pide con los enlaces en otra pestania: si
//  navegaran en esta ventana, cerrarian el puerto.
// ============================================================
require 'db.php';
soloAdmin();

cabecera('Mostrador', 'mostrador.php', true);
?>

<main>
    <div class="conexion">
        <button id="conectar">Conectar Arduino</button>
        <span id="estado" class="estado">Sin conectar. La busqueda funciona igual; solo no se prende el LED ni la pantalla.</span>
        <!-- El boton dice "Arduino" y no "Bluetooth" porque al operador
             del mostrador le importa a QUE se conecta, no por que cable. -->

    </div>

    <!-- Todo lo que cambia pasa aca adentro. -->
    <iframe src="escaneo.php" name="panel" title="Pantalla de escaneo"></iframe>
</main>

<!-- El transporte hacia el Arduino. Hay dos, y se usa UNO:
       arduino.js    por el cable USB (Web Serial)      <- el de ahora
       bluetooth.js  por el modulo HC-08 (Web Bluetooth)
     Los dos tienen la misma funcion alArduino(), asi que el resto del
     sistema no se entera de cual esta puesto: se cambia esta linea sola.
     Volvio al cable el 19/11/2026, con la placa enchufada por USB. -->
<script src="arduino.js"></script>
</body>
</html>
