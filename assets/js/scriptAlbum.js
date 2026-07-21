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

// card tilt on mouse (solo para cartas ya descubiertas)
document.querySelectorAll('.tcard:not(.locked)').forEach(card => {
  card.addEventListener('mousemove', (e) => {
    const r = card.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5;
    const y = (e.clientY - r.top) / r.height - 0.5;
    card.style.transform = `translateY(-14px) rotateX(${y * -10}deg) rotateY(${x * 10}deg) scale(1.03)`;
  });
  card.addEventListener('mouseleave', () => { card.style.transform = ''; });
});

document.addEventListener("DOMContentLoaded", () => {

    const btn = document.getElementById("btnFiltrar");

    btn.addEventListener("click", aplicarFiltros);

    document.getElementById("f-buscar").addEventListener("keyup", aplicarFiltros);
    document.getElementById("f-equipo").addEventListener("change", aplicarFiltros);
    document.getElementById("f-afinidad").addEventListener("change", aplicarFiltros);

    document.querySelectorAll(".checkbox-row input").forEach(cb=>{
        cb.addEventListener("change", aplicarFiltros);
    });

});

function aplicarFiltros(){

    const nombre = document.getElementById("f-buscar").value.toLowerCase().trim();

    const equipo = document.getElementById("f-equipo").value;

    const afinidad = document.getElementById("f-afinidad").value;

    const rarezas = [];

    document.querySelectorAll(".checkbox-row input:checked")
        .forEach(cb=>rarezas.push(cb.value));

    const cartas = document.querySelectorAll(".tcard");

    cartas.forEach(carta=>{

        let mostrar = true;

        if(nombre!=""){
            if(!carta.dataset.nombre.toLowerCase().includes(nombre))
                mostrar=false;
        }

        if(equipo!=""){
            if(carta.dataset.equipo!=equipo)
                mostrar=false;
        }

        if(afinidad!=""){
            if(carta.dataset.afinidad!=afinidad)
                mostrar=false;
        }

        if(rarezas.length>0){
            if(!rarezas.includes(carta.dataset.rareza))
                mostrar=false;
        }

        carta.style.display = mostrar ? "" : "none";

    });

    ocultarExpansionesVacias();

}

function ocultarExpansionesVacias(){

    document.querySelectorAll(".expansion-group").forEach(exp=>{

        const visibles = exp.querySelectorAll(".tcard:not([style*='display: none'])");

        exp.style.display = visibles.length ? "" : "none";

    });

}