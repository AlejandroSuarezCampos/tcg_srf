/**
 * MOTOR DE MOVIMIENTO — comprobación de la tabla de resolución de nivel.
 *
 * El motor vive INLINE en partials/head.php (tiene que correr antes del primer
 * pintado). Esta prueba lee ese fichero, saca el bloque <script> de verdad y lo
 * ejecuta contra un navegador de mentira: así comprueba el código que se
 * despliega, no una copia que se quede vieja.
 *
 * Lo que se protege: que nadie pierda su preferencia guardada al desplegar
 * —el sistema anterior guardaba 'si'/'no' y el nuevo guarda
 * 'full'/'lite'/'reduce'— y que la elección del jugador mande siempre sobre la
 * detección del aparato.
 *
 *   node db/pruebas/probar_motor_movimiento.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import assert from 'node:assert/strict';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const head = readFileSync(join(raiz, 'partials', 'head.php'), 'utf8');

// El motor es el primer <script> del <head>. Si algún día deja de serlo, esto
// falla ruidosamente en vez de probar el bloque equivocado.
const bloque = head.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(bloque, 'no se encontró el bloque <script> del motor en partials/head.php');
assert.match(bloque[1], /nivelMovimiento/, 'el primer <script> de head.php ya no es el motor de movimiento');

/** Monta un navegador de mentira, ejecuta el motor y devuelve su API. */
function arrancar({ guardado = null, nucleos = 8, memoria = 8, reduceSistema = false } = {}) {
  const almacen = new Map();
  if (guardado !== null) almacen.set('srf-animaciones', guardado);

  const html = { dataset: {} };
  const win = {
    localStorage: {
      getItem: (k) => (almacen.has(k) ? almacen.get(k) : null),
      setItem: (k, v) => almacen.set(k, String(v)),
      removeItem: (k) => almacen.delete(k),
    },
    navigator: { hardwareConcurrency: nucleos, deviceMemory: memoria },
    matchMedia: (q) => ({
      matches: q.includes('prefers-reduced-motion') ? reduceSistema : false,
      addEventListener() {},
    }),
    document: { documentElement: html, querySelector: () => null },
  };

  // El motor usa `window`, `document`, `navigator` y `localStorage` como
  // globales sueltas, igual que en un navegador de verdad.
  new Function('window', 'document', 'navigator', 'localStorage', bloque[1])(
    win, win.document, win.navigator, win.localStorage
  );

  return { SRF: win.SRF, html, almacen };
}

let fallos = 0;
function comprobar(titulo, fn) {
  try { fn(); console.log('  ok  ' + titulo); }
  catch (e) { fallos++; console.error('FALLO  ' + titulo + '\n       ' + e.message); }
}

console.log('\nMotor de movimiento — resolución de nivel\n');

comprobar('equipo capaz y sin preferencia → completo', () => {
  const { SRF, html } = arrancar({ nucleos: 8, memoria: 8 });
  assert.equal(SRF.nivelMovimiento(), 'full');
  assert.equal(html.dataset.motion, 'full', 'debe escribir data-motion al arrancar');
});

comprobar('pocos núcleos → ligero', () => {
  assert.equal(arrancar({ nucleos: 4, memoria: 8 }).SRF.nivelMovimiento(), 'lite');
});

comprobar('poca memoria → ligero', () => {
  assert.equal(arrancar({ nucleos: 8, memoria: 4 }).SRF.nivelMovimiento(), 'lite');
});

comprobar('el sistema pide reducir → mínimo', () => {
  assert.equal(arrancar({ reduceSistema: true }).SRF.nivelMovimiento(), 'reduce');
});

comprobar('sin datos del aparato no se penaliza (Safari/Firefox) → completo', () => {
  const { SRF } = arrancar({ nucleos: undefined, memoria: undefined });
  assert.equal(SRF.nivelMovimiento(), 'full');
});

comprobar('«si» del sistema anterior se traduce a completo', () => {
  // y además manda sobre un aparato flojo: la elección del jugador gana
  const { SRF } = arrancar({ guardado: 'si', nucleos: 2, memoria: 2 });
  assert.equal(SRF.nivelMovimiento(), 'full');
});

comprobar('«no» del sistema anterior se traduce a mínimo', () => {
  const { SRF } = arrancar({ guardado: 'no', nucleos: 16, memoria: 16 });
  assert.equal(SRF.nivelMovimiento(), 'reduce');
});

comprobar('la elección manda sobre la detección', () => {
  const { SRF } = arrancar({ guardado: 'full', nucleos: 2, memoria: 2 });
  assert.equal(SRF.nivelMovimiento(), 'full');
});

comprobar('un valor corrupto cae a la detección, no rompe', () => {
  const { SRF } = arrancar({ guardado: 'ñaña', nucleos: 2, memoria: 2 });
  assert.equal(SRF.nivelMovimiento(), 'lite');
  assert.equal(SRF.preferenciaMovimiento(), null);
});

comprobar('fijar un nivel lo guarda y repinta data-motion', () => {
  const { SRF, html, almacen } = arrancar();
  SRF.fijarNivelMovimiento('lite');
  assert.equal(almacen.get('srf-animaciones'), 'lite');
  assert.equal(html.dataset.motion, 'lite');
});

comprobar('fijar null vuelve a automático y borra lo guardado', () => {
  const { SRF, html, almacen } = arrancar({ guardado: 'reduce', nucleos: 8, memoria: 8 });
  assert.equal(html.dataset.motion, 'reduce');
  SRF.fijarNivelMovimiento(null);
  assert.equal(almacen.has('srf-animaciones'), false);
  assert.equal(html.dataset.motion, 'full');
});

comprobar('compatibilidad: movimientoReducido() sigue respondiendo', () => {
  assert.equal(arrancar({ guardado: 'reduce' }).SRF.movimientoReducido(), true);
  assert.equal(arrancar({ guardado: 'lite' }).SRF.movimientoReducido(), false);
  assert.equal(arrancar({ guardado: 'full' }).SRF.movimientoReducido(), false);
});

comprobar('compatibilidad: fijarPreferenciaMovimiento("si"/"no") sigue valiendo', () => {
  // lo llaman ceremonia.js y ceremonia_cofre.js desde el aviso de la ceremonia
  const a = arrancar();
  a.SRF.fijarPreferenciaMovimiento('si');
  assert.equal(a.html.dataset.motion, 'full');
  assert.equal(a.almacen.get('srf-animaciones'), 'full');

  const b = arrancar();
  b.SRF.fijarPreferenciaMovimiento('no');
  assert.equal(b.html.dataset.motion, 'reduce');
});

comprobar('compatibilidad: preferenciaMovimiento() distingue elegido de automático', () => {
  // ceremonia.js hace !!SRF.preferenciaMovimiento() para no repreguntar
  assert.equal(arrancar().SRF.preferenciaMovimiento(), null);
  assert.equal(arrancar({ guardado: 'si' }).SRF.preferenciaMovimiento(), 'full');
  assert.equal(arrancar({ guardado: 'lite' }).SRF.preferenciaMovimiento(), 'lite');
});

comprobar('modo privado (localStorage que lanza) no tumba la página', () => {
  const bomba = () => { throw new Error('SecurityError'); };
  const html = { dataset: {} };
  const win = {
    localStorage: { getItem: bomba, setItem: bomba, removeItem: bomba },
    navigator: { hardwareConcurrency: 8, deviceMemory: 8 },
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    document: { documentElement: html, querySelector: () => null },
  };
  new Function('window', 'document', 'navigator', 'localStorage', bloque[1])(
    win, win.document, win.navigator, win.localStorage
  );
  assert.equal(html.dataset.motion, 'full');
  win.SRF.fijarNivelMovimiento('lite');       // no debe lanzar
  assert.equal(html.dataset.motion, 'lite');
});

console.log(fallos === 0 ? '\nTodo correcto.\n' : `\n${fallos} fallo(s).\n`);
process.exit(fallos === 0 ? 0 : 1);
