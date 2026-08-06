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

// card tilt on mouse
document.querySelectorAll('.tcard').forEach(card => {
  card.addEventListener('mousemove', (e) => {
    const r = card.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5;
    const y = (e.clientY - r.top) / r.height - 0.5;
    card.style.transform = `translateY(-14px) rotateX(${y * -10}deg) rotateY(${x * 10}deg) scale(1.03)`;
  });
  card.addEventListener('mouseleave', () => { card.style.transform = ''; });
});

// Modal "Vender una carta"
function abrirModalVender() {
  document.getElementById('modalVender')?.classList.add('open');
}
function cerrarModalVender() {
  document.getElementById('modalVender')?.classList.remove('open');
}
document.getElementById('modalVender')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalVender') cerrarModalVender();
});

// Comprar / retirar de forma asíncrona (sin recargar la página)
document.querySelectorAll('.js-mercado-form').forEach((form) => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const mensaje = form.dataset.confirm;
    if (mensaje && !confirm(mensaje)) return;

    const boton = form.querySelector('button[type="submit"]');
    const textoOriginal = boton.textContent;
    boton.disabled = true;
    boton.textContent = 'Procesando...';

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      const data = await res.json();

      if (!data.ok) {
        alert(data.error || 'No se pudo completar la acción.');
        boton.disabled = false;
        boton.textContent = textoOriginal;
        return;
      }

      // Si la acción devolvió el nuevo saldo (compra), actualizamos el navbar
      if (typeof data.monedas === 'number') {
        actualizarMonedasNav(data.monedas);
      }

      // Sacamos la tarjeta del mercado con una pequeña animación de salida
      const card = form.closest('.tcard');
      if (card) {
        card.style.transition = 'opacity .25s, transform .25s';
        card.style.opacity = '0';
        card.style.transform = 'scale(.94)';
        setTimeout(() => card.remove(), 250);
      }
    } catch (err) {
      console.error(err);
      alert('Ha ocurrido un error de conexión. Inténtalo de nuevo.');
      boton.disabled = false;
      boton.textContent = textoOriginal;
    }
  });
});