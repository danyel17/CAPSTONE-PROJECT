(function () {
  const viewport = document.getElementById('menuLandingViewport');
  if (!viewport || typeof gsap === 'undefined') return;

  const ASSETS = {
    pineapple: 'soda/models/kenney-pineapple/pineapple.glb',
    pineappleAlt: 'soda/models/kenney-pineapple/pineapple.glb',
    bubble: 'https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/bubble.png',
  };

  const PALENGKE_ORANGE = [0.93, 0.42, 0.12, 1];
  const PALENGKE_BLUE_THEME = [0.55, 0.62, 0.78, 1];

  const modelViewer = viewport.querySelector('#product-model');
  const berriesFG = viewport.querySelector('.berries-container');
  const berriesBG = viewport.querySelector('.berries-container-bg');
  const leavesBG = viewport.querySelector('.leaves-container');
  const allBerries = viewport.querySelectorAll('.berry');
  const cards = viewport.querySelectorAll('.card');
  let isSwitching = false;
  let switchSpin = 0;

  function applyPalengkeTheme(flavor) {
    if (!modelViewer || !modelViewer.model) return;
    const orange = flavor !== 'blue';
    modelViewer.model.materials.forEach((material) => {
      if (material.name !== 'wall_orange') return;
      const pbr = material.pbrMetallicRoughness;
      if (!pbr) return;
      const factor = orange ? PALENGKE_ORANGE : PALENGKE_BLUE_THEME;
      if (typeof pbr.setBaseColorFactor === 'function') {
        pbr.setBaseColorFactor(factor);
      } else {
        pbr.baseColorFactor = factor;
      }
    });
  }

  if (modelViewer) {
    modelViewer.addEventListener('load', () => applyPalengkeTheme('classic'));
  }

  cards.forEach((card) => {
    card.addEventListener('click', () => {
      if (isSwitching) return;
      cards.forEach((c) => c.classList.remove('active'));
      card.classList.add('active');
      switchFlavor(card.dataset.flavor);
    });
  });

  function switchFlavor(flavor) {
    if (isSwitching) return;
    isSwitching = true;
    const berries = viewport.querySelectorAll('.berry');
    const heroCenter = viewport.querySelector('.hero-center');

    const targetColors =
      flavor === 'blue'
        ? { inner: '#7870e8', mid: '#4a44a0', outer: '#1a1640' }
        : { inner: '#6b63c9', mid: '#3f4191', outer: '#2e236c' };

    gsap.to(viewport, {
      '--bg-inner': targetColors.inner,
      '--bg-mid': targetColors.mid,
      '--bg-outer': targetColors.outer,
      duration: 1.5,
      ease: 'power2.inOut',
    });

    const spinObj = { val: 0, blur: 0 };
    gsap.to(spinObj, {
      val: 360,
      blur: 15,
      duration: 0.6,
      ease: 'power2.in',
      onUpdate: () => {
        switchSpin = spinObj.val;
        if (modelViewer) modelViewer.style.filter = `blur(${spinObj.blur}px)`;
      },
      onComplete: () => {
        if (flavor === 'blue') viewport.classList.add('blue-theme');
        else viewport.classList.remove('blue-theme');
        applyPalengkeTheme(flavor);

        gsap.to(spinObj, {
          val: 720,
          blur: 0,
          duration: 1.5,
          ease: 'back.out(0.7)',
          onUpdate: () => {
            switchSpin = spinObj.val;
            if (modelViewer) modelViewer.style.filter = `blur(${spinObj.blur}px)`;
          },
          onComplete: () => {
            switchSpin = 0;
            if (modelViewer) modelViewer.style.filter = 'none';
          },
        });
      },
    });

    let completedBerries = 0;
    berries.forEach((berry) => {
      const bW = berry.offsetWidth / 2;
      const bH = berry.offsetHeight / 2;
      const centerX = window.innerWidth / 2 - berry.offsetLeft - bW;
      const centerY = window.innerHeight / 2 - berry.offsetTop - bH;

      const startAngle = parseFloat(berry.dataset.angle) || 0;
      const currentBaseX = parseFloat(berry.dataset.baseX) || 0;
      const currentBaseY = parseFloat(berry.dataset.baseY) || 0;
      const nextBaseX = (Math.random() - 0.5) * 200;
      const nextBaseY = (Math.random() - 0.5) * 200;

      gsap.set(berry, { rotation: startAngle, x: currentBaseX, y: currentBaseY });

      gsap
        .timeline()
        .to(berry, {
          x: centerX,
          y: centerY,
          rotation: startAngle + 45,
          scale: 0.1,
          opacity: 0,
          duration: 0.5,
          ease: 'power2.in',
          onComplete: () => {
            berry.src = flavor === 'blue' ? ASSETS.pineappleAlt : ASSETS.pineapple;
            if (heroCenter) heroCenter.style.zIndex = 50;
          },
        })
        .to(berry, { duration: 0.3 })
        .to(berry, {
          onStart: () => {
            if (heroCenter) heroCenter.style.zIndex = 1;
          },
          x: nextBaseX,
          y: nextBaseY,
          rotation: startAngle + 90,
          scale: 1,
          opacity: 1,
          duration: 0.9,
          ease: 'back.out(1.5)',
          onComplete: () => {
            berry.dataset.angle = String(startAngle + 90);
            berry.dataset.baseX = String(nextBaseX);
            berry.dataset.baseY = String(nextBaseY);
            berry.dataset.rx = '0';
            berry.dataset.ry = '0';
            completedBerries++;
            if (completedBerries === berries.length) isSwitching = false;
          },
        });
    });
  }

  allBerries.forEach((b) => {
    b.dataset.rx = '0';
    b.dataset.ry = '0';
    b.dataset.angle = String(Math.random() * 360);
    b.dataset.baseX = '0';
    b.dataset.baseY = '0';
  });

  const mouse = { x: 0, y: 0, px: 0, py: 0 };
  const currentMouse = { x: 0, y: 0 };

  window.addEventListener('mousemove', (e) => {
    mouse.x = e.clientX / window.innerWidth - 0.5;
    mouse.y = e.clientY / window.innerHeight - 0.5;
    mouse.px = e.clientX;
    mouse.py = e.clientY;
  });

  function animate() {
    const time = Date.now() * 0.001;
    currentMouse.x += (mouse.x - currentMouse.x) * 0.05;
    currentMouse.y += (mouse.y - currentMouse.y) * 0.05;

    if (modelViewer) {
      modelViewer.cameraOrbit = `${25 + currentMouse.x * 35 + switchSpin}deg ${78 + currentMouse.y * 18}deg 175%`;
    }
    if (berriesFG) berriesFG.style.transform = `translate(${currentMouse.x * 60}px, ${currentMouse.y * 60}px)`;
    if (berriesBG) berriesBG.style.transform = `translate(${currentMouse.x * -30}px, ${currentMouse.y * -30}px)`;
    if (leavesBG) leavesBG.style.transform = `translate(${currentMouse.x * -15}px, ${currentMouse.y * -15}px)`;

    if (!isSwitching) {
      allBerries.forEach((berry, i) => {
        const rect = berry.getBoundingClientRect();
        const berryX = rect.left + rect.width / 2;
        const berryY = rect.top + rect.height / 2;
        const diffX = mouse.px - berryX;
        const diffY = mouse.py - berryY;
        const distance = Math.sqrt(diffX * diffX + diffY * diffY);

        let targetRx = 0;
        let targetRy = 0;
        let speedMult = 1;
        if (distance < 400) {
          const force = (400 - distance) / 400;
          targetRx = (diffX / distance) * force * -80;
          targetRy = (diffY / distance) * force * -80;
          speedMult = 1 + force * 5;
        }

        let rx = parseFloat(berry.dataset.rx) || 0;
        let ry = parseFloat(berry.dataset.ry) || 0;
        let angle = parseFloat(berry.dataset.angle) || 0;
        const baseX = parseFloat(berry.dataset.baseX) || 0;
        const baseY = parseFloat(berry.dataset.baseY) || 0;

        rx += (targetRx - rx) * 0.1;
        ry += (targetRy - ry) * 0.1;
        angle += 0.2 * speedMult;
        berry.dataset.rx = String(rx);
        berry.dataset.ry = String(ry);
        berry.dataset.angle = String(angle);

        const dur = [5, 7, 6, 8, 5.5, 6.5, 9, 11, 10][i % 9];
        const phase = (time + i * 0.7) * ((Math.PI * 2) / dur);
        const floatY = Math.sin(phase) * 15;
        const floatAngle = Math.cos(phase) * 6;
        berry.style.transform = `translate(calc(${rx + baseX}px), calc(${ry + baseY}px + ${floatY}px)) rotate(calc(${angle}deg + ${floatAngle}deg))`;
      });
    }

    document.querySelectorAll('.menu-landing-viewport .leaf, .features-section-wrapper .leaf').forEach((leaf, i) => {
      const dur = 10 + (i % 8) * 2;
      const phase = (time + i * 1.2) * ((Math.PI * 2) / dur);
      const floatY = Math.sin(phase) * 20;
      const floatX = Math.cos(phase * 0.5) * 15;
      const floatAngle = Math.sin(phase * 0.3) * 15;
      leaf.style.transform = `translate(${floatX}px, ${floatY}px) rotate(${floatAngle}deg)`;
    });

    requestAnimationFrame(animate);
  }

  animate();

  const bubblesContainer = viewport.querySelector('#bubbles-container');
  function createBubble() {
    if (!bubblesContainer) return;
    const bubble = document.createElement('img');
    bubble.src = ASSETS.bubble;
    bubble.alt = '';
    bubble.className = 'bubble-img';
    const size = Math.random() * 20 + 10 + 'px';
    bubble.style.width = size;
    bubble.style.height = 'auto';
    bubble.style.left = Math.random() * 100 + '%';
    bubble.style.bottom = '-50px';
    bubble.style.opacity = String(Math.random() * 0.4 + 0.2);
    const duration = Math.random() * 6 + 4;
    bubble.style.animation = `floatUpImg ${duration}s linear forwards`;
    bubblesContainer.appendChild(bubble);
    setTimeout(() => bubble.remove(), duration * 1000);
  }
  setInterval(createBubble, 400);
})();
