# -*- coding: utf-8 -*-
"""
Genera el juego de iconos PROPIO del sitio a partir de Phosphor.

El problema: `partials/head.php` enlazaba TRES hojas de estilo de unpkg.com y
cada una arrastra su fuente de iconos. Medido: 250 KB de CSS + 429 KB de
fuentes = 679 KB en cada carga, desde un tercero y con la CSS bloqueando el
primer pintado. Y el sitio usa 86 iconos: 82 normales, 10 rellenos y UNO en
negrita.

Lo que hace este script:
  1. Busca en el código todos los `ph-loquesea` que se usan de verdad.
  2. Baja de unpkg la hoja y la fuente de cada peso.
  3. Se queda con las reglas `.ph-x:before` de los iconos usados y lee de ahí
     el punto de código de cada glifo.
  4. Subsetea la fuente a esos glifos con fontTools.
  5. Escribe `assets/css/iconos.css` y `assets/fonts/*.woff2` propios.

Se vuelve a ejecutar cuando se añada un icono nuevo al sitio.
"""
import re, os, glob, collections, urllib.request, io

RAIZ = r'C:\xampp\htdocs\tcg_srf-mastero'
VERSION = '2.1.1'
BASE_CDN = 'https://unpkg.com/@phosphor-icons/web@%s/src' % VERSION

# peso -> (carpeta en el CDN, fichero de fuente, familia CSS, clase)
PESOS = {
    'regular': ('regular', 'Phosphor.woff2',      'Phosphor',       'ph'),
    'bold':    ('bold',    'Phosphor-Bold.woff2', 'Phosphor-Bold',  'ph-bold'),
    'fill':    ('fill',    'Phosphor-Fill.woff2', 'Phosphor-Fill',  'ph-fill'),
}


def iconos_usados():
    """{peso: {nombre, ...}} leyendo el código del sitio."""
    usados = collections.defaultdict(set)
    patrones = ['**/*.php', '**/*.js', '**/*.css']
    for pat in patrones:
        for p in glob.glob(os.path.join(RAIZ, pat), recursive=True):
            rel = os.path.relpath(p, RAIZ)
            if rel.startswith('ionos' + os.sep) or rel.startswith('_legacy'):
                continue
            t = open(p, encoding='utf-8', errors='ignore').read()
            for m in re.finditer(r'\bph(-fill|-bold|-duotone|-thin|-light)?\s+ph-([a-z0-9-]+)', t):
                usados[(m.group(1) or '-regular')[1:]].add(m.group(2))
            # los que se construyen en JavaScript: 'ph-treasure-chest'
            for m in re.finditer(r"'ph-([a-z0-9-]+)'", t):
                usados['regular'].add(m.group(1))

    # ------------------------------------------------------------------
    # TODOS LOS NOMBRES, EN TODOS LOS PESOS.
    #
    # Escanear el par «peso + nombre» no basta y por eso no se veían los
    # iconos de Defensa y Técnica. El marcado que los pinta es:
    #
    #     <i class="ph-fill <?= $s['icono'] ?>">
    #
    # El nombre llega en una variable, así que el primer patrón —que busca
    # `ph-fill` seguido de `ph-algo` LITERAL— no casa nunca, y el segundo
    # recoge 'ph-shield' de la tabla de PHP pero lo apunta a `regular`. El
    # resultado: `.ph-fill.ph-sword` sí se declaraba (aparece literal en otro
    # sitio) y `.ph-fill.ph-shield` no, con la fuente `fill` reducida a diez
    # glifos. Escudo y rayo salían en blanco.
    #
    # No se puede saber estáticamente con qué peso se va a pintar un nombre
    # que vive en una variable, así que se deja de intentar: cada peso que el
    # sitio usa se lleva la lista COMPLETA de nombres. Cuesta unos kilobytes
    # —el subconjunto entero sigue estando quince veces por debajo de las tres
    # hojas de unpkg— y a cambio no puede volver a pasar.
    # ------------------------------------------------------------------
    todos = set()
    for nombres in usados.values():
        todos |= nombres
    for peso in usados:
        usados[peso] = set(todos)

    return usados


def bajar(url):
    with urllib.request.urlopen(url, timeout=60) as r:
        return r.read()


def main():
    from fontTools import subset
    from fontTools.ttLib import TTFont

    usados = iconos_usados()
    partes_css = [
        '/* ==========================================================================',
        '   ICONOS — Phosphor %s, recortado a lo que este sitio usa de verdad.' % VERSION,
        '',
        '   ⚠️ GENERADO. No se edita a mano: lo escribe el script de iconos a partir',
        '      de los `ph-loquesea` que aparecen en el código. Si añades un icono',
        '      nuevo y no se ve, hay que volver a generarlo.',
        '',
        '   Sustituye a las tres hojas de unpkg.com que había en head.php. Aquellas',
        '   pesaban 250 KB de CSS y arrastraban 429 KB de fuentes —679 KB por carga,',
        '   desde un tercero y bloqueando el primer pintado— para usar 86 iconos.',
        '   ========================================================================== */',
        '',
    ]

    for peso, (carpeta, fichero, familia, clase) in PESOS.items():
        nombres = usados.get(peso, set())
        if not nombres:
            continue

        css = bajar('%s/%s/style.css' % (BASE_CDN, carpeta)).decode('utf-8')

        # .ph.ph-acorn:before { content: "\e000" }  ->  nombre -> punto de código
        mapa = {}
        for m in re.finditer(r'\.' + re.escape(clase) + r'\.ph-([a-z0-9-]+):before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"', css):
            mapa[m.group(1)] = int(m.group(2), 16)
        if not mapa:   # algunas versiones no repiten la clase del peso
            for m in re.finditer(r'\.ph-([a-z0-9-]+):before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"', css):
                mapa.setdefault(m.group(1), int(m.group(2), 16))

        encontrados = {n: mapa[n] for n in sorted(nombres) if n in mapa}
        faltan = sorted(set(nombres) - set(encontrados))

        # Subsetear la fuente a esos puntos de código
        fuente = bajar('%s/%s/%s' % (BASE_CDN, carpeta, fichero))
        antes = len(fuente)
        tf = TTFont(io.BytesIO(fuente))
        opciones = subset.Options()
        opciones.flavor = 'woff2'
        opciones.desubroutinize = True
        opciones.layout_features = []
        opciones.notdef_outline = False
        subsetter = subset.Subsetter(options=opciones)
        subsetter.populate(unicodes=list(encontrados.values()))
        subsetter.subset(tf)
        salida = io.BytesIO()
        tf.flavor = 'woff2'
        tf.save(salida)
        datos = salida.getvalue()

        destino = os.path.join(RAIZ, 'assets', 'fonts', 'iconos-%s.woff2' % peso)
        open(destino, 'wb').write(datos)
        sello = int(os.path.getmtime(destino))
        print('%-8s %3d/%d iconos · fuente %6.1f KB -> %5.1f KB%s' % (
            peso, len(encontrados), len(nombres), antes / 1024, len(datos) / 1024,
            ('  ⚠ no encontrados: ' + ', '.join(faltan)) if faltan else ''))

        partes_css += [
            '@font-face {',
            '  font-family: "%s";' % familia,
            # ⚠️ CON VERSIÓN. El `.htaccess` cachea los woff2 un año con
            # `immutable`, así que sin `?v=` una copia mal descargada se
            # quedaría clavada un año en el navegador de esa persona y vería la
            # web sin iconos sin forma de arreglarlo.
            '  src: url("../fonts/iconos-%s.woff2?v=%d") format("woff2");' % (peso, sello),
            '  font-weight: normal; font-style: normal;',
            '  /* `block`: un icono es un glifo, no texto. Con `swap` se ve un cuadro',
            '     vacío mientras carga y luego salta; con `block` no se ve nada unos',
            '     milisegundos y aparece ya bien. */',
            '  font-display: block;',
            '}',
            '.%s {' % clase.replace('-', '\\-') if False else '.%s {' % clase,
            '  font-family: "%s" !important;' % familia,
            '  speak: never; font-style: normal; font-weight: normal; font-variant: normal;',
            '  text-transform: none; line-height: 1; display: inline-block;',
            '  -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;',
            '}',
        ]
        for n, cp in sorted(encontrados.items()):
            partes_css.append('.%s.ph-%s:before { content: "\\%x"; }' % (clase, n, cp))
        partes_css.append('')

    destino_css = os.path.join(RAIZ, 'assets', 'css', 'iconos.css')
    open(destino_css, 'w', encoding='utf-8', newline='\n').write('\n'.join(partes_css))
    print('CSS escrito: %.1f KB' % (os.path.getsize(destino_css) / 1024))


if __name__ == '__main__':
    main()
