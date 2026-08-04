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

// tabs de perfil
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.target).classList.add('active');
  });
});

// canje de códigos
const formCanjearCodigo = document.getElementById('formCanjearCodigo');
const inputCodigo = document.getElementById('inputCodigo');
const codigoFeedback = document.getElementById('codigoFeedback');

if (formCanjearCodigo && inputCodigo && codigoFeedback) {
  formCanjearCodigo.addEventListener('submit', async (e) => {
    e.preventDefault();

    const codigo = inputCodigo.value.trim().toUpperCase();
    if (!codigo) {
      codigoFeedback.textContent = 'Escribe un código antes de canjearlo.';
      codigoFeedback.className = 'redeem-feedback error';
      return;
    }

    codigoFeedback.textContent = 'Canjeando...';
    codigoFeedback.className = 'redeem-feedback';

    const formData = new FormData();
    formData.append('codigo', codigo);

    try {
      const respuesta = await fetch('./assets/ajax/canjear_codigo.php', {
        method: 'POST',
        body: formData,
      });

      const resultado = await respuesta.json();

      if (resultado.ok) {
        codigoFeedback.textContent = `¡Código canjeado! Ganaste ${resultado.monedas_ganadas} monedas.`;
        codigoFeedback.className = 'redeem-feedback success';
        formCanjearCodigo.reset();
      } else {
        codigoFeedback.textContent = resultado.error || 'No se pudo canjear el código.';
        codigoFeedback.className = 'redeem-feedback error';
      }
    } catch (error) {
      codigoFeedback.textContent = 'No se pudo conectar con el servidor.';
      codigoFeedback.className = 'redeem-feedback error';
    }
  });
}
