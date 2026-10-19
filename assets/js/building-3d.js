/**
 * Daet Public Market 3D building (Three.js r128) — drag to rotate.
 */
function init3DBuilding() {
  var canvas = document.getElementById('building3dCanvas');
  if (!canvas || canvas.getAttribute('data-three-inited') === '1') return;
  canvas.setAttribute('data-three-inited', '1');

  var scene = new THREE.Scene();
  scene.background = new THREE.Color(0x87ceeb);

  var cw = canvas.clientWidth || canvas.offsetWidth || 800;
  var ch = canvas.clientHeight || canvas.offsetHeight || 600;
  if (ch < 2) ch = 600;
  if (cw < 2) cw = 800;

  var camera = new THREE.PerspectiveCamera(75, cw / ch, 0.1, 1000);
  camera.position.set(0, 6, 18);
  camera.lookAt(0, 3, 0);

  var renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(cw, ch);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  canvas.appendChild(renderer.domElement);

  var ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
  scene.add(ambientLight);

  var directionalLight = new THREE.DirectionalLight(0xffffff, 0.9);
  directionalLight.position.set(15, 15, 10);
  directionalLight.castShadow = true;
  directionalLight.shadow.mapSize.width = 2048;
  directionalLight.shadow.mapSize.height = 2048;
  scene.add(directionalLight);

  var buildingGroup = new THREE.Group();

  var buildingLength = 25;
  var buildingWidth = 5;
  var groundFloorHeight = 3.5;
  var upperStoryHeight = 2.5;
  var totalHeight = groundFloorHeight + upperStoryHeight;

  var groundFloorGeometry = new THREE.BoxGeometry(buildingLength, groundFloorHeight, buildingWidth);
  var orangeMaterial = new THREE.MeshLambertMaterial({ color: 0xff8c42 });
  var groundFloor = new THREE.Mesh(groundFloorGeometry, orangeMaterial);
  groundFloor.position.y = groundFloorHeight / 2;
  groundFloor.castShadow = true;
  groundFloor.receiveShadow = true;
  buildingGroup.add(groundFloor);

  var upperStoryGeometry = new THREE.BoxGeometry(buildingLength, upperStoryHeight, buildingWidth);
  var upperStory = new THREE.Mesh(upperStoryGeometry, orangeMaterial);
  upperStory.position.y = groundFloorHeight + upperStoryHeight / 2;
  upperStory.castShadow = true;
  upperStory.receiveShadow = true;
  buildingGroup.add(upperStory);

  var trimHeight = 0.4;
  var trimGeometry = new THREE.BoxGeometry(buildingLength, trimHeight, buildingWidth + 0.3);
  var blueMaterial = new THREE.MeshLambertMaterial({ color: 0x1e3a8a });
  var rooflineTrim = new THREE.Mesh(trimGeometry, blueMaterial);
  rooflineTrim.position.y = totalHeight + trimHeight / 2;
  buildingGroup.add(rooflineTrim);

  var pedimentPositions = [-10, -6, -2, 2, 6, 10];
  pedimentPositions.forEach(function (x) {
    var pedimentShape = new THREE.Shape();
    pedimentShape.moveTo(-0.8, 0);
    pedimentShape.lineTo(0, 0.6);
    pedimentShape.lineTo(0.8, 0);
    pedimentShape.lineTo(-0.8, 0);
    var pedimentGeometry = new THREE.ShapeGeometry(pedimentShape);
    var pediment = new THREE.Mesh(pedimentGeometry, blueMaterial);
    pediment.rotation.x = -Math.PI / 2;
    pediment.position.set(x, totalHeight + trimHeight + 0.3, buildingWidth / 2 + 0.15);
    pediment.scale.set(1, 1, 0.1);
    buildingGroup.add(pediment);
  });

  var pilasterWidth = 0.4;
  var pilasterDepth = buildingWidth + 0.15;
  var pilasterGeometry = new THREE.BoxGeometry(pilasterWidth, totalHeight, pilasterDepth);
  var pilasterPositions = [-11, -7, -3, 1, 5, 9];
  pilasterPositions.forEach(function (x) {
    var pilaster = new THREE.Mesh(pilasterGeometry, blueMaterial);
    pilaster.position.set(x, totalHeight / 2, 0);
    buildingGroup.add(pilaster);
  });

  var archShape = new THREE.Shape();
  archShape.moveTo(-2, 0);
  archShape.quadraticCurveTo(0, -2, 2, 0);
  var archGeometry = new THREE.ShapeGeometry(archShape);
  var arch = new THREE.Mesh(archGeometry, blueMaterial);
  arch.rotation.x = -Math.PI / 2;
  arch.position.set(0, totalHeight - 0.5, buildingWidth / 2 + 0.1);
  arch.scale.set(1, 1, 0.2);
  buildingGroup.add(arch);

  var windowGeometry = new THREE.BoxGeometry(0.7, 0.5, 0.1);
  var windowMaterial = new THREE.MeshLambertMaterial({ color: 0x1a1a1a });
  for (var wx = -11.5; wx <= 11.5; wx += 2.2) {
    var win = new THREE.Mesh(windowGeometry, windowMaterial);
    win.position.set(wx, groundFloorHeight + upperStoryHeight / 2, buildingWidth / 2 + 0.05);
    buildingGroup.add(win);
  }

  var logoRadius = 1.8;
  var logoOuterGeometry = new THREE.CylinderGeometry(logoRadius, logoRadius, 0.25, 32);
  var logoOuter = new THREE.Mesh(logoOuterGeometry, blueMaterial);
  logoOuter.rotation.x = Math.PI / 2;
  logoOuter.position.set(-10.5, groundFloorHeight + upperStoryHeight / 2, buildingWidth / 2 + 0.1);
  buildingGroup.add(logoOuter);

  var logoInnerGeometry = new THREE.CylinderGeometry(logoRadius * 0.75, logoRadius * 0.75, 0.26, 32);
  var logoInner = new THREE.Mesh(logoInnerGeometry, new THREE.MeshLambertMaterial({ color: 0xffffff }));
  logoInner.rotation.x = Math.PI / 2;
  logoInner.position.set(-10.5, groundFloorHeight + upperStoryHeight / 2, buildingWidth / 2 + 0.11);
  buildingGroup.add(logoInner);

  var logoCenterGeometry = new THREE.CylinderGeometry(logoRadius * 0.6, logoRadius * 0.6, 0.27, 32);
  var logoCenter = new THREE.Mesh(logoCenterGeometry, new THREE.MeshLambertMaterial({ color: 0x667eea }));
  logoCenter.rotation.x = Math.PI / 2;
  logoCenter.position.set(-10.5, groundFloorHeight + upperStoryHeight / 2, buildingWidth / 2 + 0.12);
  buildingGroup.add(logoCenter);

  var textCanvas = document.createElement('canvas');
  var textContext = textCanvas.getContext('2d');
  textCanvas.width = 2048;
  textCanvas.height = 512;
  textContext.clearRect(0, 0, textCanvas.width, textCanvas.height);
  textContext.strokeStyle = '#1E3A8A';
  textContext.lineWidth = 20;
  textContext.font = 'bold 180px Arial';
  textContext.textAlign = 'center';
  textContext.textBaseline = 'middle';
  textContext.strokeText('DAET PUBLIC MARKET', textCanvas.width / 2, textCanvas.height / 2);
  textContext.fillStyle = '#FFFFFF';
  textContext.fillText('DAET PUBLIC MARKET', textCanvas.width / 2, textCanvas.height / 2);

  var textTexture = new THREE.CanvasTexture(textCanvas);
  textTexture.needsUpdate = true;
  var textWidth = 16;
  var textHeight = 2.5;
  var textGeometry = new THREE.PlaneGeometry(textWidth, textHeight);
  var textMaterial = new THREE.MeshLambertMaterial({ map: textTexture, transparent: true });
  var textPlane = new THREE.Mesh(textGeometry, textMaterial);
  textPlane.position.set(-7.5 + textWidth / 2, groundFloorHeight + upperStoryHeight / 2, buildingWidth / 2 + 0.15);
  buildingGroup.add(textPlane);

  var shopWidth = buildingLength / 12;
  for (var i = 0; i < 12; i++) {
    var shopX = -buildingLength / 2 + shopWidth / 2 + i * shopWidth;
    var shopGeometry = new THREE.BoxGeometry(shopWidth - 0.3, groundFloorHeight, buildingWidth);
    var shop = new THREE.Mesh(shopGeometry, orangeMaterial);
    shop.position.set(shopX, groundFloorHeight / 2, 0);
    shop.castShadow = true;
    shop.receiveShadow = true;
    buildingGroup.add(shop);

    var entranceWidth = shopWidth - 0.8;
    var entranceHeight = groundFloorHeight - 0.5;
    var entranceDepth = 0.3;
    var entranceGeometry = new THREE.BoxGeometry(entranceWidth, entranceHeight, entranceDepth);
    var entranceMaterial = new THREE.MeshLambertMaterial({ color: 0x2c2c2c });
    var entrance = new THREE.Mesh(entranceGeometry, entranceMaterial);
    entrance.position.set(shopX, groundFloorHeight / 2 - 0.2, buildingWidth / 2 - entranceDepth / 2);
    buildingGroup.add(entrance);

    if (i === 3 || i === 6) {
      var doorGeometry = new THREE.BoxGeometry(entranceWidth * 0.4, entranceHeight * 0.9, 0.05);
      var doorMaterial = new THREE.MeshLambertMaterial({ color: 0x8b4513 });
      var door = new THREE.Mesh(doorGeometry, doorMaterial);
      door.position.set(shopX - entranceWidth * 0.3, groundFloorHeight / 2 - 0.2, buildingWidth / 2 - entranceDepth + 0.05);
      buildingGroup.add(door);
    }

    var awningColors = [0x1e90ff, 0xff69b4, 0xffffff];
    var awningColor = awningColors[i % 3];
    var awningGeometry = new THREE.BoxGeometry(shopWidth - 0.2, 0.2, buildingWidth + 0.5);
    var awning = new THREE.Mesh(awningGeometry, new THREE.MeshLambertMaterial({ color: awningColor }));
    awning.position.set(shopX, groundFloorHeight + 0.1, buildingWidth / 2 - 0.2);
    buildingGroup.add(awning);
  }

  var signPositions = [
    { x: -9, text: 'CARIÑOSA PAWNSHOP' },
    { x: -6, text: 'SHAWARMA SHACK' },
    { x: -3, text: 'CHUMS MEATSHOP' },
    { x: 3, text: 'MEATLAND' }
  ];
  signPositions.forEach(function (sign) {
    var signCanvas = document.createElement('canvas');
    var signContext = signCanvas.getContext('2d');
    signCanvas.width = 1024;
    signCanvas.height = 256;
    signContext.fillStyle = '#FFFFFF';
    signContext.fillRect(0, 0, signCanvas.width, signCanvas.height);
    signContext.fillStyle = '#000000';
    signContext.font = 'bold 50px Arial';
    signContext.textAlign = 'center';
    signContext.textBaseline = 'middle';
    signContext.fillText(sign.text, signCanvas.width / 2, signCanvas.height / 2);
    var signTexture = new THREE.CanvasTexture(signCanvas);
    signTexture.needsUpdate = true;
    var sw = sign.text.length > 10 ? 2.5 : 1.8;
    var signGeometry = new THREE.PlaneGeometry(sw, 0.8);
    var signMaterial = new THREE.MeshLambertMaterial({ map: signTexture });
    var signPlane = new THREE.Mesh(signGeometry, signMaterial);
    signPlane.position.set(sign.x, groundFloorHeight - 0.5, buildingWidth / 2 + 0.1);
    buildingGroup.add(signPlane);
  });

  var groundGeometry = new THREE.PlaneGeometry(60, 60);
  var groundMaterial = new THREE.MeshLambertMaterial({ color: 0x808080 });
  var ground = new THREE.Mesh(groundGeometry, groundMaterial);
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = 0;
  ground.receiveShadow = true;
  scene.add(ground);

  buildingGroup.scale.set(1.4, 1.4, 1.4);
  scene.add(buildingGroup);

  var isDragging = false;
  var previousMousePosition = { x: 0, y: 0 };

  canvas.addEventListener('mousedown', function (e) {
    isDragging = true;
    previousMousePosition = { x: e.clientX, y: e.clientY };
  });

  canvas.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    var deltaX = e.clientX - previousMousePosition.x;
    var deltaY = e.clientY - previousMousePosition.y;
    buildingGroup.rotation.y += deltaX * 0.01;
    buildingGroup.rotation.x += deltaY * 0.01;
    buildingGroup.rotation.x = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, buildingGroup.rotation.x));
    previousMousePosition = { x: e.clientX, y: e.clientY };
  });

  canvas.addEventListener('mouseup', function () {
    isDragging = false;
  });
  canvas.addEventListener('mouseleave', function () {
    isDragging = false;
  });

  canvas.addEventListener('touchstart', function (e) {
    isDragging = true;
    previousMousePosition = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  });

  canvas.addEventListener('touchmove', function (e) {
    if (!isDragging) return;
    var deltaX = e.touches[0].clientX - previousMousePosition.x;
    var deltaY = e.touches[0].clientY - previousMousePosition.y;
    buildingGroup.rotation.y += deltaX * 0.01;
    buildingGroup.rotation.x += deltaY * 0.01;
    buildingGroup.rotation.x = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, buildingGroup.rotation.x));
    previousMousePosition = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  });

  canvas.addEventListener('touchend', function () {
    isDragging = false;
  });

  function animate() {
    requestAnimationFrame(animate);
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', function () {
    var rw = canvas.clientWidth || canvas.offsetWidth || 800;
    var rh = canvas.clientHeight || canvas.offsetHeight || 600;
    if (rh < 2) rh = 600;
    if (rw < 2) rw = 800;
    camera.aspect = rw / rh;
    camera.updateProjectionMatrix();
    renderer.setSize(rw, rh);
  });
}

function tryInit3DBuilding() {
  try {
    init3DBuilding();
  } catch (err) {
    console.error('Error initializing 3D building:', err);
  }
}

var _building3dAttempts = 0;
function tryInit3DBuildingWhenSized() {
  var el = document.getElementById('building3dCanvas');
  if (!el) return;
  var h = el.clientHeight || el.offsetHeight;
  _building3dAttempts++;
  if (h < 48 && _building3dAttempts < 120) {
    requestAnimationFrame(tryInit3DBuildingWhenSized);
    return;
  }
  tryInit3DBuilding();
}

window.addEventListener('load', function () {
  setTimeout(tryInit3DBuildingWhenSized, 150);
});
