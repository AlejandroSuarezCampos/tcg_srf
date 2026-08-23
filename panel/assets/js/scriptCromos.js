/**
 * Lógica de panel/cromos.php: rellenar el modal de creación/edición con los
 * datos del cromo al pulsar "Editar", y pedir confirmación antes de navegar
 * al borrado real (cromos.php?eliminar=ID).
 *
 * El modal en sí (abrir/cerrar, Esc, clic fuera, foco atrapado) lo lleva
 * SRF.abrirModal()/cerrarModal() de assets/js/ui.js — igual que en el resto
 * del sitio, no hay nada propio de este panel.
 *
 * Nota sobre imágenes: en la base de datos las rutas se guardan en formato
 * "./assets/img/..." (relativo a la raíz del proyecto). Como este panel
 * vive en panel/, para previsualizar hay que anteponer un "." extra
 * ("." + "./assets/..." = "../assets/..."), pero el valor que se guarda
 * en el campo de texto (y que se envía al servidor) es siempre la ruta
 * original, sin tocar.
 */

const DEFAULT_PREVIEW = '../assets/img/perfil/apple-icon-120x120.png';

function rutaPreview(imagen) {
  return imagen ? ('.' + imagen) : DEFAULT_PREVIEW;
}

function abrirModalCromo(cromo) {
  const titulo = document.getElementById('modalCromoTitulo');
  const submitBtn = document.getElementById('f_submit');

  if (cromo) {
    titulo.textContent = 'Editar cromo';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('f_id_cromo').value = cromo.id_cromo || '';
    document.getElementById('f_nombre').value = cromo.nombre || '';
    document.getElementById('f_imagen_archivo').value = '';
    document.getElementById('f_imagen').value = cromo.imagen || '';
    document.getElementById('f_preview').src = rutaPreview(cromo.imagen);
    document.getElementById('f_id_equipo').value = cromo.id_equipo || '';
    document.getElementById('f_id_expansion').value = cromo.id_expansion || '';
    document.getElementById('f_posicion').value = cromo.posicion || '';
    document.getElementById('f_id_rareza').value = cromo.id_rareza || '';
    document.getElementById('f_id_afinidad').value = cromo.id_afinidad || '';
    document.getElementById('f_ataque').value = cromo.ataque || 0;
    document.getElementById('f_defensa').value = cromo.defensa || 0;
    document.getElementById('f_tecnica').value = cromo.tecnica || 0;
    // El select solo refleja un rasgo concreto si es un override MANUAL: si es
    // el que le tocó por derivación automática, se sigue viendo "Automático"
    // aunque ya tenga uno asignado (es lo que es, un resultado, no una elección).
    document.getElementById('f_compo').value =
      (cromo.compo_manual == 1 && cromo.id_rasgo_compo) ? cromo.id_rasgo_compo : '';
    document.getElementById('f_descripcion').value = cromo.descripcion || '';
    document.getElementById('f_universo').value = cromo.universo === 'ie' ? 'ie' : 'srf';
    /* Cupo vacío y cupo cero significan lo mismo —carta sin tirada limitada— y
       se enseña vacío: un 0 en la casilla se lee como un valor puesto a
       propósito, y no lo es. */
    document.getElementById('f_cupo_numerado').value =
      Number(cromo.cupo_numerado) > 0 ? cromo.cupo_numerado : '';
    document.getElementById('f_en_sobres').checked = Number(cromo.en_sobres) === 1;
    document.getElementById('f_solo_cadena').checked = Number(cromo.solo_cadena) === 1;
  } else {
    titulo.textContent = 'Nuevo cromo';
    submitBtn.textContent = 'Crear cromo';
    document.getElementById('formCromo').reset();
    document.getElementById('f_id_cromo').value = '';
    document.getElementById('f_preview').src = DEFAULT_PREVIEW;
    document.getElementById('f_universo').value = 'srf';
    document.getElementById('f_cupo_numerado').value = '';
    // Una carta nueva sale en sobres y no es secreta: lo normal.
    document.getElementById('f_en_sobres').checked = true;
    document.getElementById('f_solo_cadena').checked = false;
  }

  SRF.abrirModal('modalCromo');
}

function cerrarModalCromo() {
  SRF.cerrarModal('modalCromo');
}

// Actualiza la miniatura al escribir/pegar una ruta de imagen
document.getElementById('f_imagen')?.addEventListener('input', (e) => {
  document.getElementById('f_preview').src = rutaPreview(e.target.value);
});

// Elegir un archivo previsualiza ESE archivo (aún sin subir) y manda por
// delante a la ruta escrita a mano, que es lo que hace también el servidor.
document.getElementById('f_imagen_archivo')?.addEventListener('change', (e) => {
  const archivo = e.target.files && e.target.files[0];
  if (!archivo) return;
  document.getElementById('f_preview').src = URL.createObjectURL(archivo);
});

function pedirBorrado(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar "' + nombre + '"? Se quitará de la colección y de los mazos de CUALQUIERA que la tenga, y de su rastro en duelos ya jugados. No se puede deshacer.', function () {
    window.location.href = 'cromos.php?eliminar=' + encodeURIComponent(id) + '&csrf=' + encodeURIComponent(SRF.csrfToken());
  });
}

/* --------------------------------------------------------------------------
   ALEATORIZAR ESTADÍSTICAS

   Rellena ataque/defensa/técnica con un tiro dentro del rango real de la
   rareza y la posición elegidas. El sorteo lo hace el SERVIDOR a propósito:
   la tabla de rangos (IMPORT_RANGOS_STATS) es la misma que usa la importación
   masiva y el editor de nodos, y una copia aquí en JavaScript se quedaría
   desfasada en cuanto alguien tocase la de PHP.

   Los tres campos siguen siendo editables después: esto sugiere, no impone.
   -------------------------------------------------------------------------- */
document.getElementById('f_stats_aleatorias')?.addEventListener('click', function () {
  var boton = this;
  var aviso = document.getElementById('f_stats_aviso');
  var posicion = document.getElementById('f_posicion').value;
  var idRareza = document.getElementById('f_id_rareza').value;

  boton.disabled = true;
  fetch('../assets/ajax/cadena_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      accion: 'stats_aleatorias', csrf: SRF.csrfToken(),
      posicion: posicion, id_rareza: idRareza,
    }),
  })
    .then(function (r) { return r.json(); })
    .then(function (r) {
      boton.disabled = false;
      if (!r.ok) { aviso.textContent = r.error || 'No se pudo sortear.'; return; }

      var s = r.stats;
      if (!s.ataque && !s.defensa && !s.tecnica) {
        // Entrenadores, gerentes y escudos no juegan: no tienen rango.
        aviso.textContent = 'Esa posición no tiene estadísticas.';
        return;
      }
      document.getElementById('f_ataque').value = s.ataque;
      document.getElementById('f_defensa').value = s.defensa;
      document.getElementById('f_tecnica').value = s.tecnica;
      aviso.textContent = 'Sorteadas dentro del rango de esa rareza y posición. Puedes retocarlas.';
    })
    .catch(function () {
      boton.disabled = false;
      aviso.textContent = 'No se pudo conectar con el servidor.';
    });
});
