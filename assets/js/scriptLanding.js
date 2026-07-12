// nav scroll state
  const nav = document.getElementById('nav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  });

  // scroll reveal
  const revealEls = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.15 });
  revealEls.forEach(el => io.observe(el));

  // bolt flicker
  function flicker(el, delay){
    setTimeout(function loop(){
      el.style.transition = 'opacity .1s';
      el.style.opacity = Math.random() > 0.5 ? 1 : 0;
      setTimeout(()=> el.style.opacity = 0, 120);
      setTimeout(loop, 2500 + Math.random()*3000);
    }, delay);
  }
  flicker(document.getElementById('bolt1'), 800);
  flicker(document.getElementById('bolt2'), 2200);

  // ranking tabs
  document.querySelectorAll('.rank-tab').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      document.querySelectorAll('.rank-tab').forEach(t=>t.classList.remove('active'));
      document.querySelectorAll('.rank-view').forEach(v=>v.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.target).classList.add('active');
    });
  });

  // particles canvas
  const canvas = document.getElementById('particles');
  const ctx = canvas.getContext('2d');
  let w, h, particles = [];
  function resize(){
    w = canvas.width = canvas.offsetParent.clientWidth || window.innerWidth;
    h = canvas.height = canvas.parentElement.clientHeight;
  }
  function initParticles(){
    particles = [];
    const count = Math.min(70, Math.floor(w/18));
    for(let i=0;i<count;i++){
      particles.push({
        x: Math.random()*w, y: Math.random()*h,
        r: Math.random()*1.8 + 0.4,
        vy: -(Math.random()*0.4 + 0.15),
        vx: (Math.random()-0.5)*0.2,
        a: Math.random()*0.6 + 0.15
      });
    }
  }
  function draw(){
    ctx.clearRect(0,0,w,h);
    particles.forEach(p=>{
      p.y += p.vy; p.x += p.vx;
      if(p.y < -10){ p.y = h+10; p.x = Math.random()*w; }
      ctx.beginPath();
      ctx.fillStyle = `rgba(140,170,255,${p.a})`;
      ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
      ctx.fill();
    });
    requestAnimationFrame(draw);
  }
  resize(); initParticles(); draw();
  window.addEventListener('resize', ()=>{ resize(); initParticles(); });

  // card tilt on mouse
  document.querySelectorAll('.tcard').forEach(card=>{
    card.addEventListener('mousemove', (e)=>{
      const r = card.getBoundingClientRect();
      const x = (e.clientX - r.left)/r.width - 0.5;
      const y = (e.clientY - r.top)/r.height - 0.5;
      card.style.transform = `translateY(-14px) rotateX(${y*-10}deg) rotateY(${x*10}deg) scale(1.03)`;
    });
    card.addEventListener('mouseleave', ()=>{ card.style.transform = ''; });
  });