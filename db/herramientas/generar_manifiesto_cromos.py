# -*- coding: utf-8 -*-
"""Manifiesto de imágenes cuadradas: se calcula UNA vez, no en cada página."""
from PIL import Image
import os

RAIZ = r'C:\xampp\htdocs\tcg_srf-mastero'
DIR = os.path.join(RAIZ, 'assets', 'img', 'Cromos')

todas, total = [], 0
for dp, _, fn in os.walk(DIR):
    for f in fn:
        if not f.lower().endswith(('.png', '.jpg', '.jpeg', '.webp')):
            continue
        p = os.path.join(dp, f)
        try:
            w, h = Image.open(p).size
        except Exception:
            continue
        total += 1
        rel = './' + os.path.relpath(p, RAIZ).replace(os.sep, '/')
        todas.append((rel, abs(w - h) <= max(w, h) * 0.02))

todas.sort()
cuadradas = [r for r, c in todas if c]

cab = [
    "<?php",
    "/**",
    " * QUÉ IMÁGENES DE CROMO SON CUADRADAS — o sea, cuáles llevan plantilla.",
    " *",
    " * ⚠️ GENERADO. No se edita a mano. Hay que volver a pasar el script cuando",
    " *    se añadan cromos nuevos; si una carta nueva sale sin marco, es esto.",
    " *",
    " * ⚠️ POR QUÉ UN FICHERO Y NO MIRARLO AL VUELO. `carta_usa_marco()` decide por",
    " *    la FORMA de la imagen, y averiguarla obliga a abrir el fichero.",
    " *    Medido en local: 44 ms la primera vez que una página pinta el catálogo",
    " *    entero, y en un hosting compartido con el disco en red eso se dispara —",
    " *    son ~150 aperturas de fichero por carga del álbum, todas para responder",
    " *    algo que no cambia nunca. Con el manifiesto, la página no toca el disco.",
    " *",
    " *    Si una ruta no está aquí, se cae a mirar el fichero: una carta subida",
    " *    desde el panel sale bien aunque nadie haya regenerado nada.",
    " *",
    " * Catálogo: %d imágenes, %d cuadradas. Están TODAS, cuadradas o no: una" % (total, len(cuadradas)),
    " * entrada a `false` ahorra igual la comprobación en disco.",
    " */",
    "",
    "return [",
]
cuerpo = ["    '%s' => %s," % (r.replace("\\", "\\\\").replace("'", "\\'"), 'true' if c else 'false')
          for r, c in todas]
texto = "\n".join(cab + cuerpo + ["];", ""])

destino = os.path.join(RAIZ, 'components', 'cromos_cuadrados.php')
open(destino, 'w', encoding='utf-8', newline='\n').write(texto)
print('manifiesto: %d de %d cuadradas · %.1f KB' % (len(cuadradas), total, len(texto) / 1024))
