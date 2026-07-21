/**
 * scriptsAsync.js — utilidades asíncronas compartidas por todo el sitio.
 * Cargado desde navbar.php, así que está disponible en cualquier página
 * que incluya la nav (landing, colección, mercado, álbum, perfil...).
 */

// Actualiza el chip de monedas del navbar sin recargar la página.
// - Si se le pasa un número, lo pinta directamente (rápido, sin red).
// - Si no se le pasa nada, pregunta al servidor el saldo real actual.
async function actualizarMonedasNav(nuevoValor) {
  const coinsEl = document.getElementById('navCoins');
  if (!coinsEl) return;

  if (typeof nuevoValor === 'number') {
    coinsEl.textContent = nuevoValor.toLocaleString('es-ES');
    return;
  }

  try {
    const res = await fetch('ajax/monedas.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) return;
    const data = await res.json();
    if (data && data.ok && typeof data.monedas === 'number') {
      coinsEl.textContent = data.monedas.toLocaleString('es-ES');
    }
  } catch (e) {
    console.error('No se pudo actualizar el saldo de monedas', e);
  }
}