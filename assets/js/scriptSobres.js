// nav scroll state
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  });
}

// scroll reveal
const revealEls = document.querySelectorAll('.reveal');
const io = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
}, { threshold: 0.15 });
revealEls.forEach(el => io.observe(el));

// Modal de revelación
function abrirModalRevelacion() {
  document.getElementById('modalRevelacion')?.classList.add('open');
}
function cerrarModalRevelacion() {
  document.getElementById('modalRevelacion')?.classList.remove('open');
  document.getElementById('revealGrid').innerHTML = '';
}
document.getElementById('modalRevelacion')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalRevelacion') cerrarModalRevelacion();
});

// Pinta las cartas obtenidas en el grid del modal, una a una con un pequeño retraso
function pintarCartasReveladas(cartas) {
  const grid = document.getElementById('revealGrid');
  grid.innerHTML = '';

  cartas.forEach((carta, i) => {
    const item = document.createElement('div');
    item.className = 'reveal-card';
    item.style.animationDelay = `${i * 120}ms`;

    item.innerHTML = `
      <div class="reveal-card-inner">
        <span class="rarity ${carta.clase}">${carta.rareza}</span>
        <div class="portrait">
          ${carta.imagen
            ? `<img src="${carta.imagen}" alt="${carta.nombre}">`
            : carta.nombre.slice(0, 2).toUpperCase()}
        </div>
        <h3>${carta.nombre}</h3>
        <div class="meta-row"><span>${carta.equipo}</span></div>
      </div>
    `;
    grid.appendChild(item);
  });
}

// Comprar y abrir sobre de forma asíncrona
document.querySelectorAll('.js-sobre-form').forEach((form) => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const boton = form.querySelector('button[type="submit"]');
    if (boton.disabled) return;

    const textoOriginal = boton.textContent;
    boton.disabled = true;
    boton.textContent = 'Abriendo...';

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      const data = await res.json();

      if (!data.ok) {
        alert(data.error || 'No se pudo abrir el sobre.');
        boton.disabled = false;
        boton.textContent = textoOriginal;
        return;
      }

      if (typeof data.monedas === 'number') {
        actualizarMonedasNav(data.monedas);
        boton.textContent = textoOriginal;
        // Si ya no llegan las monedas para abrir un sobre, lo desactivamos (este u otros)
        document.querySelectorAll('.js-sobre-form').forEach((f) => {
          const precioEl = f.closest('.pack-card')?.querySelector('.pack-price');
          const precio = precioEl ? parseInt(precioEl.textContent.replace(/\D/g, ''), 10) : 0;
          const btn = f.querySelector('button[type="submit"]');
          if (!btn) return;
          if (precio > data.monedas) {
            btn.disabled = true;
            btn.title = 'No tienes monedas suficientes';
          } else {
            btn.disabled = false;
            btn.removeAttribute('title');
          }
        });
      } else {
        boton.disabled = false;
        boton.textContent = textoOriginal;
      }

      pintarCartasReveladas(data.cartas || []);
      abrirModalRevelacion();
    } catch (err) {
      console.error(err);
      alert('Ha ocurrido un error de conexión. Inténtalo de nuevo.');
      boton.disabled = false;
      boton.textContent = textoOriginal;
    }
  });
});
