/* ==========================================================================
   MISIONES (misiones.php) — cuenta atrás hasta el próximo reinicio.
   El servidor manda cuántos SEGUNDOS faltan (calculados en MySQL, ver
   Tcg::proximosReinicios()); aquí solo se decrementa ese número cada
   segundo con setInterval. No hay que sincronizar reloj ni zona horaria
   con el navegador: el punto de partida ya viene correcto de origen.
   ========================================================================== */
(function () {
  'use strict';

  function formatear(segundos) {
    segundos = Math.max(0, segundos);
    var dias = Math.floor(segundos / 86400);
    var horas = Math.floor((segundos % 86400) / 3600);
    var min = Math.floor((segundos % 3600) / 60);
    var seg = segundos % 60;
    var dos = function (n) { return String(n).padStart(2, '0'); };

    return dias > 0
      ? dias + 'd ' + dos(horas) + 'h ' + dos(min) + 'm ' + dos(seg) + 's'
      : dos(horas) + 'h ' + dos(min) + 'm ' + dos(seg) + 's';
  }

  document.querySelectorAll('.mision-cuenta-atras').forEach(function (elemento) {
    var restante = parseInt(elemento.dataset.segundos, 10) || 0;
    var valor = elemento.querySelector('.mision-cuenta-atras-valor');

    var intervalo = setInterval(function () {
      restante -= 1;
      if (restante <= 0) {
        clearInterval(intervalo);
        // Al llegar a cero el periodo ya cambió en el servidor: recargar es
        // lo único que hace falta para ver las misiones del periodo nuevo,
        // el mismo patrón perezoso que usa el resto del proyecto (§8, "no
        // hay cron").
        window.location.reload();
        return;
      }
      valor.textContent = formatear(restante);
    }, 1000);
  });
})();
