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

  // ranking tabs
  document.querySelectorAll('.rank-tab').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      document.querySelectorAll('.rank-tab').forEach(t=>t.classList.remove('active'));
      document.querySelectorAll('.rank-view').forEach(v=>v.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.target).classList.add('active');
    });
  });

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
