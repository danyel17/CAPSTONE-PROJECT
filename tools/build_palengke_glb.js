// Builds soda/models/palengke-building.glb from code.
// A long two-story orange market. The facade keeps DAET PUBLIC MARKET.
// The rooftop board reads MARKET CLEARANCE SYSTEM. No front gate bars.

const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, '..', 'soda', 'models');
const facadePng = fs.readFileSync(path.join(dir, '_facade.png'));
const signPng = fs.readFileSync(path.join(dir, '_sign.png'));
const walkPng = fs.readFileSync(path.join(dir, '_sidewalk.png'));

// ---- materials ----
const materials = [];
function mat(name, obj) { materials.push(Object.assign({ name, doubleSided: true }, obj)); return materials.length - 1; }

const M_wall = mat('wall_orange', { pbrMetallicRoughness: { baseColorFactor: [0.93, 0.42, 0.12, 1], metallicFactor: 0, roughnessFactor: 0.85 } });
const M_facade = mat('facade', { pbrMetallicRoughness: { baseColorTexture: { index: 0 }, metallicFactor: 0, roughnessFactor: 0.9 } });
const M_cream = mat('trim_cream', { pbrMetallicRoughness: { baseColorFactor: [0.96, 0.92, 0.86, 1], metallicFactor: 0, roughnessFactor: 0.7 } });
const M_roof = mat('roof', { pbrMetallicRoughness: { baseColorFactor: [0.24, 0.25, 0.28, 1], metallicFactor: 0, roughnessFactor: 0.8 } });
const M_sign = mat('sign', { pbrMetallicRoughness: { baseColorTexture: { index: 1 }, metallicFactor: 0, roughnessFactor: 0.45 } });
const M_signTrim = mat('sign_trim', { pbrMetallicRoughness: { baseColorFactor: [0.08, 0.22, 0.55, 1], metallicFactor: 0, roughnessFactor: 0.55 } });
const M_walkEdge = mat('sidewalk_edge', { pbrMetallicRoughness: { baseColorFactor: [0.72, 0.68, 0.62, 1], metallicFactor: 0, roughnessFactor: 0.9 } });
const M_walk = mat('sidewalk', { pbrMetallicRoughness: { baseColorTexture: { index: 2 }, metallicFactor: 0, roughnessFactor: 0.9 } });

// ---- geometry accumulation (per material) ----
const prims = {};
function P(m) { return prims[m] || (prims[m] = { pos: [], nrm: [], uv: [], idx: [] }); }
function quad(m, a, b, c, d, uvs) {
    const p = P(m);
    const base = p.pos.length / 3;
    const ux = [b[0] - a[0], b[1] - a[1], b[2] - a[2]];
    const vx = [c[0] - a[0], c[1] - a[1], c[2] - a[2]];
    let n = [ux[1] * vx[2] - ux[2] * vx[1], ux[2] * vx[0] - ux[0] * vx[2], ux[0] * vx[1] - ux[1] * vx[0]];
    const L = Math.hypot(n[0], n[1], n[2]) || 1; n = [n[0] / L, n[1] / L, n[2] / L];
    const pts = [a, b, c, d];
    const uv = uvs || [[0, 1], [1, 1], [1, 0], [0, 0]];
    for (let i = 0; i < 4; i++) {
        p.pos.push(pts[i][0], pts[i][1], pts[i][2]);
        p.nrm.push(n[0], n[1], n[2]);
        p.uv.push(uv[i][0], uv[i][1]);
    }
    p.idx.push(base, base + 1, base + 2, base, base + 2, base + 3);
}
function box(m, x0, x1, y0, y1, z0, z1) {
    quad(m, [x0, y0, z1], [x1, y0, z1], [x1, y1, z1], [x0, y1, z1]); // +Z
    quad(m, [x1, y0, z0], [x0, y0, z0], [x0, y1, z0], [x1, y1, z0]); // -Z
    quad(m, [x0, y0, z0], [x0, y0, z1], [x0, y1, z1], [x0, y1, z0]); // -X
    quad(m, [x1, y0, z1], [x1, y0, z0], [x1, y1, z0], [x1, y1, z1]); // +X
    quad(m, [x0, y1, z1], [x1, y1, z1], [x1, y1, z0], [x0, y1, z0]); // +Y
    quad(m, [x0, y0, z0], [x1, y0, z0], [x1, y0, z1], [x0, y0, z1]); // -Y
}

// ---- building body ----
const X0 = -5, X1 = 5, Y0 = -1.5, Y1 = 1.5, Z0 = -1.4, Z1 = 1.4;
const facadeUV = [[0, 1], [1, 1], [1, 0], [0, 0]];
quad(M_facade, [X0, Y0, Z1], [X1, Y0, Z1], [X1, Y1, Z1], [X0, Y1, Z1], facadeUV); // front
quad(M_wall, [X1, Y0, Z0], [X0, Y0, Z0], [X0, Y1, Z0], [X1, Y1, Z0]); // back
quad(M_wall, [X0, Y0, Z0], [X0, Y0, Z1], [X0, Y1, Z1], [X0, Y1, Z0]); // left
quad(M_wall, [X1, Y0, Z1], [X1, Y0, Z0], [X1, Y1, Z0], [X1, Y1, Z1]); // right
quad(M_wall, [X0, Y1, Z1], [X1, Y1, Z1], [X1, Y1, Z0], [X0, Y1, Z0]); // top
quad(M_wall, [X0, Y0, Z0], [X1, Y0, Z0], [X1, Y0, Z1], [X0, Y0, Z1]); // bottom

// cream base and a single mid cornice — no vertical bars on the front
box(M_cream, -5, 5, -1.5, -1.28, 1.38, 1.48);
box(M_cream, -5, 5, 0.16, 0.28, 1.4, 1.48);

// roof slab (slight overhang)
box(M_roof, -5.2, 5.2, 1.5, 1.68, -1.6, 1.6);

// rooftop board, same shape as before: MARKET CLEARANCE SYSTEM
box(M_signTrim, -3.35, -3.1, 1.68, 1.95, 0.08, 0.24);
box(M_signTrim, 3.1, 3.35, 1.68, 1.95, 0.08, 0.24);
const sx0 = -3.7, sx1 = 3.7, sy0 = 1.9, sy1 = 2.62, sz0 = 0.06, sz1 = 0.22;
quad(M_sign, [sx0, sy0, sz1], [sx1, sy0, sz1], [sx1, sy1, sz1], [sx0, sy1, sz1], facadeUV);
quad(M_sign, [sx1, sy0, sz0], [sx0, sy0, sz0], [sx0, sy1, sz0], [sx1, sy1, sz0], facadeUV);
quad(M_signTrim, [sx0, sy0, sz0], [sx0, sy0, sz1], [sx0, sy1, sz1], [sx0, sy1, sz0]);
quad(M_signTrim, [sx1, sy0, sz1], [sx1, sy0, sz0], [sx1, sy1, sz0], [sx1, sy1, sz1]);
quad(M_signTrim, [sx0, sy1, sz1], [sx1, sy1, sz1], [sx1, sy1, sz0], [sx0, sy1, sz0]);
quad(M_signTrim, [sx0, sy0, sz0], [sx1, sy0, sz0], [sx1, sy0, sz1], [sx0, sy0, sz1]);

// tiled sidewalk only — a shallow band in front of the shops, no road
const wx0 = -5.25, wx1 = 5.25, wy0 = -1.62, wy1 = -1.46, wz0 = 1.42, wz1 = 2.22;
box(M_walkEdge, wx0, wx1, wy0, wy1 - 0.01, wz0, wz1);
const walkUV = [[0, 1], [1, 1], [1, 0], [0, 0]];
quad(M_walk, [wx0, wy1, wz1], [wx1, wy1, wz1], [wx1, wy1, wz0], [wx0, wy1, wz0], walkUV);

// ---- pack to GLB ----
const views = [];
const accessors = [];
function addFloat(arr, numComp, target, minmax) {
    const buf = Buffer.alloc(arr.length * 4);
    for (let i = 0; i < arr.length; i++) buf.writeFloatLE(arr[i], i * 4);
    const vi = views.length; views.push({ buf, target });
    const count = arr.length / numComp;
    const type = numComp === 3 ? 'VEC3' : numComp === 2 ? 'VEC2' : 'SCALAR';
    const acc = { bufferView: vi, componentType: 5126, count, type };
    if (minmax) {
        const mn = new Array(numComp).fill(Infinity), mx = new Array(numComp).fill(-Infinity);
        for (let i = 0; i < count; i++) for (let c = 0; c < numComp; c++) {
            const v = arr[i * numComp + c]; if (v < mn[c]) mn[c] = v; if (v > mx[c]) mx[c] = v;
        }
        acc.min = mn; acc.max = mx;
    }
    accessors.push(acc); return accessors.length - 1;
}
function addIdx(arr) {
    const buf = Buffer.alloc(arr.length * 2);
    for (let i = 0; i < arr.length; i++) buf.writeUInt16LE(arr[i], i * 2);
    const vi = views.length; views.push({ buf, target: 34963 });
    accessors.push({ bufferView: vi, componentType: 5123, count: arr.length, type: 'SCALAR' });
    return accessors.length - 1;
}

const meshPrims = [];
for (const m of Object.keys(prims)) {
    const p = prims[m];
    const pos = addFloat(p.pos, 3, 34962, true);
    const nrm = addFloat(p.nrm, 3, 34962, false);
    const uv = addFloat(p.uv, 2, 34962, false);
    const idx = addIdx(p.idx);
    meshPrims.push({ attributes: { POSITION: pos, NORMAL: nrm, TEXCOORD_0: uv }, indices: idx, material: Number(m) });
}

const imgFacadeView = views.length; views.push({ buf: facadePng, target: null });
const imgSignView = views.length; views.push({ buf: signPng, target: null });
const imgWalkView = views.length; views.push({ buf: walkPng, target: null });
const images = [
    { bufferView: imgFacadeView, mimeType: 'image/png' },
    { bufferView: imgSignView, mimeType: 'image/png' },
    { bufferView: imgWalkView, mimeType: 'image/png' }
];
const samplers = [{ magFilter: 9729, minFilter: 9987, wrapS: 33071, wrapT: 33071 }];
const textures = [{ source: 0, sampler: 0 }, { source: 1, sampler: 0 }, { source: 2, sampler: 0 }];

let offset = 0; const parts = []; const bufferViews = [];
for (const v of views) {
    const pad = (4 - (offset % 4)) % 4; if (pad) { parts.push(Buffer.alloc(pad)); offset += pad; }
    const bv = { buffer: 0, byteOffset: offset, byteLength: v.buf.length };
    if (v.target) bv.target = v.target;
    bufferViews.push(bv);
    parts.push(v.buf); offset += v.buf.length;
}
let bin = Buffer.concat(parts);
const binPad = (4 - (bin.length % 4)) % 4; if (binPad) bin = Buffer.concat([bin, Buffer.alloc(binPad)]);

const gltf = {
    asset: { version: '2.0', generator: 'daet-palengke-builder' },
    scene: 0,
    scenes: [{ nodes: [0] }],
    nodes: [{ name: 'PalengkeBuilding', mesh: 0 }],
    meshes: [{ name: 'Palengke', primitives: meshPrims }],
    materials, accessors, bufferViews,
    buffers: [{ byteLength: bin.length }],
    images, samplers, textures
};

const jsonBuf = Buffer.from(JSON.stringify(gltf), 'utf8');
const jsonPad = (4 - (jsonBuf.length % 4)) % 4;
const jsonChunk = Buffer.concat([jsonBuf, Buffer.alloc(jsonPad, 0x20)]);
const header = Buffer.alloc(12);
header.writeUInt32LE(0x46546C67, 0);
header.writeUInt32LE(2, 4);
header.writeUInt32LE(12 + 8 + jsonChunk.length + 8 + bin.length, 8);
const jsonHead = Buffer.alloc(8); jsonHead.writeUInt32LE(jsonChunk.length, 0); jsonHead.writeUInt32LE(0x4E4F534A, 4);
const binHead = Buffer.alloc(8); binHead.writeUInt32LE(bin.length, 0); binHead.writeUInt32LE(0x004E4942, 4);
const glb = Buffer.concat([header, jsonHead, jsonChunk, binHead, bin]);

const out = path.join(dir, 'palengke-building.glb');
fs.writeFileSync(out, glb);
console.log('WROTE', out, glb.length, 'bytes; prims', meshPrims.length);
