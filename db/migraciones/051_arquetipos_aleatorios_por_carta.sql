-- ============================================================================
-- 051 · EL ARQUETIPO YA NO SE DEDUCE DE POSICION x AFINIDAD
-- ============================================================================
--
-- Lo pidio Alejandro tal cual: "no siempre que cree un defensa de bosque me lo
-- asigne a brecha, quiero que sea aleatorio". La regla vieja era
-- (linea_del_puesto - linea_de_la_afinidad) mod 4: un cuadrado latino
-- perfecto, o sea DETERMINISTA por construccion. Las 16 celdas de posicion x
-- afinidad tenian un UNICO arquetipo cada una (los 33 defensas de Bosque eran
-- los 33 Brecha). Vease branding/CLAUDE.md, changelog v8.1.
--
-- Esta migracion NO es una formula reproducible en SQL: el reparto nuevo sale
-- de Tcg::derivarRasgosConfiguracion(), que sortea con
-- md5("compo:" . id_cromo) en PHP. Este fichero es la FOTOGRAFIA de ese
-- sorteo ya resuelto en local, expresada como filas concretas -- exactamente
-- lo que produce ejecutar esa funcion sobre el catalogo real, no una formula
-- alternativa. Asi no hace falta subir codigo PHP para aplicar esto: basta
-- este .sql, y produccion queda igual que si se hubiera llamado a la funcion
-- alli mismo.
--
-- QUE TOCA: unicamente cromo_rasgos, y solo las filas con manual = 0 de
-- rasgos.tipo = "configuracion". Nada mas de la base cambia. Los id_rasgo se
-- resuelven por subconsulta (SELECT ... WHERE clave = ...), no por numero:
-- si el id numerico de "brecha" en produccion no coincide con el de local, la
-- migracion sigue siendo correcta igualmente.
--
-- RESPETA "manual = 1": la clausula WHERE de cada DELETE lo excluye, igual
-- que Tcg::derivarRasgosConfiguracion(). Si en produccion alguna de estas
-- cartas tiene el rasgo curado a mano desde el panel, esta migracion NO la
-- toca -- se queda como esta, que es la regla en toda la app.
--
-- LAS CARTAS SIN AFINIDAD REAL ("no-afi") NO LLEVAN ARQUETIPO -- decision de
-- Alejandro, no una carencia del calculo. Son 11 jugables que
-- arrastraban un rasgo puesto por otro camino (la formula vieja lo necesitaba
-- como operando; el sorteo por id no). Se les borra el que tengan, sin volver
-- a ponerles ninguno.
--
-- Idempotente: se puede correr dos veces sin efecto añadido -- el DELETE de un
-- ID que ya coincide con el reparto de este fichero simplemente rehace la
-- misma fila.
-- ============================================================================

START TRANSACTION;

-- --- 1) Fuera todo lo AUTOMATICO de las 531 cartas jugables que toca esta migracion.
--        (las 520 con afinidad real, que reciben un arquetipo nuevo abajo,
--         y las 11 sin afinidad, que se quedan sin ninguno) ---------------------
DELETE FROM `cromo_rasgos`
WHERE `manual` = 0
  AND `id_rasgo` IN (SELECT `id_rasgo` FROM `rasgos` WHERE `tipo` = "configuracion")
  AND `id_cromo` IN (3,4,5,6,7,8,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,164,165,166,167,168,169,170,171,172,173,174,175,176,177,178,179,180,181,182,183,187,188,189,190,191,192,193,194,195,196,197,198,199,200,201,202,203,204,205,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,226,230,231,232,233,234,235,236,237,238,239,240,241,242,243,244,245,246,247,248,252,253,254,255,256,257,258,259,260,261,262,263,264,265,266,267,268,269,270,271,275,276,277,278,279,280,281,282,283,284,285,286,287,288,289,290,291,292,293,294,298,299,300,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,321,322,323,324,325,326,327,328,329,330,331,332,333,334,335,336,337,338,339,343,344,345,346,347,348,349,350,351,352,353,354,355,356,357,358,359,360,361,362,366,367,368,369,370,371,372,373,374,375,376,377,378,379,380,381,382,383,384,385,389,390,391,392,393,394,395,396,397,398,399,400,401,402,403,404,405,406,407,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,429,430,434,435,436,437,438,439,440,441,442,443,444,445,446,447,448,449,450,451,452,453,457,458,459,460,461,462,463,464,465,466,467,468,469,470,471,472,473,474,478,479,480,481,482,483,484,485,486,487,488,489,490,491,492,493,494,495,496,497,501,502,503,504,505,506,507,508,510,511,514,515,516,517,518,519,520,521,522,523,524,525,526,527,528,529,530,531,532,533,534,535,536,537,538,539,540,541,542,543,544,545,546,547,548,549,550,551,552,553,554,555,556,557,558,559,560,561,562,563,564,565,566,567,568,569,570,571,572,573,574,575,576,577,578,579,580,581,582,583,584,585,586,587,588,589,590,591,592,593,594,595,596,597,598,599,600,601,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,627,628,629,630,631,632,633,634,635,636);

-- --- 2) El reparto nuevo, carta a carta, para las 520 con afinidad real ---------
INSERT INTO `cromo_rasgos` (`id_cromo`, `id_rasgo`, `manual`)
SELECT v.id_cromo, r.id_rasgo, 0
FROM (
  SELECT 3 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 4 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 5 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 6 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 7 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 8 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 53 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 54 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 55 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 56 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 57 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 58 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 59 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 60 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 61 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 62 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 63 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 64 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 65 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 66 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 67 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 68 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 69 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 70 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 71 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 72 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 76 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 77 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 78 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 79 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 80 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 81 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 82 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 83 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 84 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 85 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 86 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 87 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 88 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 89 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 90 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 91 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 92 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 93 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 94 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 95 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 99 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 100 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 101 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 102 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 103 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 104 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 105 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 106 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 107 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 108 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 109 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 110 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 111 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 112 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 113 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 114 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 115 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 116 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 117 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 118 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 120 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 121 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 122 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 123 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 124 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 125 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 126 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 127 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 128 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 129 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 130 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 131 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 132 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 133 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 134 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 135 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 136 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 137 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 141 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 142 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 143 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 144 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 145 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 146 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 147 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 148 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 149 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 150 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 151 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 152 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 153 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 154 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 155 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 156 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 157 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 158 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 159 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 160 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 164 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 165 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 166 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 167 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 168 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 169 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 170 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 171 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 172 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 173 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 174 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 175 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 176 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 177 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 178 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 179 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 180 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 181 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 182 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 183 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 187 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 188 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 189 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 190 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 191 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 192 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 193 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 194 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 195 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 196 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 197 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 198 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 199 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 200 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 201 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 202 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 203 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 204 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 205 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 207 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 208 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 209 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 210 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 211 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 212 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 213 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 214 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 215 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 216 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 217 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 218 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 219 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 220 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 221 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 222 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 223 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 224 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 225 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 226 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 230 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 231 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 232 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 233 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 234 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 235 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 236 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 237 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 238 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 239 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 240 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 241 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 242 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 243 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 244 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 245 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 246 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 247 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 248 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 252 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 253 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 254 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 255 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 256 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 257 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 258 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 259 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 260 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 261 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 262 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 263 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 264 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 265 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 266 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 267 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 268 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 269 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 270 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 271 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 275 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 276 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 277 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 278 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 279 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 280 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 281 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 282 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 283 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 284 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 285 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 286 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 287 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 288 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 289 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 290 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 291 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 292 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 293 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 294 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 298 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 299 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 300 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 301 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 302 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 303 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 304 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 305 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 306 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 307 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 308 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 309 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 310 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 311 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 312 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 313 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 314 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 315 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 316 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 317 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 321 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 322 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 323 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 324 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 325 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 326 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 327 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 328 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 329 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 330 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 331 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 332 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 333 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 334 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 335 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 336 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 337 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 338 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 339 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 343 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 344 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 345 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 346 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 347 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 348 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 349 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 350 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 351 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 352 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 353 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 354 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 355 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 356 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 357 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 358 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 359 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 360 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 361 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 362 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 366 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 367 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 368 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 369 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 370 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 371 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 372 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 373 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 374 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 375 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 376 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 377 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 378 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 379 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 380 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 381 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 382 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 383 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 384 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 385 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 389 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 390 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 391 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 392 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 393 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 394 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 395 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 396 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 397 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 398 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 399 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 400 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 401 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 402 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 403 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 404 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 405 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 406 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 407 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 411 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 412 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 413 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 414 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 415 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 416 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 417 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 418 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 419 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 420 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 421 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 422 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 423 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 424 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 425 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 426 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 427 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 428 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 429 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 430 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 434 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 435 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 436 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 437 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 438 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 439 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 440 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 441 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 442 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 443 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 444 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 445 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 446 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 447 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 448 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 449 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 450 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 451 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 452 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 453 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 457 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 458 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 459 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 460 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 461 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 462 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 463 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 464 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 465 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 466 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 467 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 468 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 469 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 470 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 471 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 472 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 473 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 474 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 478 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 479 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 480 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 481 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 482 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 483 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 484 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 485 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 486 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 487 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 488 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 489 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 490 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 491 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 492 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 493 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 494 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 495 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 496 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 497 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 501 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 502 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 503 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 504 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 505 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 506 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 507 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 508 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 510 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 511 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 514 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 515 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 516 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 517 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 518 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 519 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 520 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 521 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 522 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 523 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 524 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 525 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 526 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 527 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 528 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 529 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 530 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 531 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 532 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 533 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 534 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 535 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 536 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 537 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 538 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 539 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 540 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 541 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 542 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 543 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 544 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 545 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 546 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 547 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 548 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 549 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 550 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 562 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 563 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 564 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 565 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 566 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 567 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 568 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 569 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 570 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 571 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 572 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 573 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 574 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 575 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 576 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 577 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 578 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 579 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 580 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 581 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 582 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 583 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 584 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 585 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 586 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 587 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 588 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 589 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 590 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 591 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 592 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 593 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 594 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 595 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 596 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 597 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 598 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 599 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 600 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 601 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 602 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 603 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 604 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 605 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 606 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 607 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 608 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 609 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 610 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 611 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 612 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 613 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 614 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 615 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 616 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 617 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 618 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 619 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 620 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 621 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 622 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 623 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 624 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 625 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 626 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 627 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 628 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 629 AS id_cromo, "contraataque" AS clave
  UNION ALL
  SELECT 630 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 631 AS id_cromo, "brecha" AS clave
  UNION ALL
  SELECT 632 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 633 AS id_cromo, "justicia" AS clave
  UNION ALL
  SELECT 634 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 635 AS id_cromo, "vinculo" AS clave
  UNION ALL
  SELECT 636 AS id_cromo, "vinculo" AS clave
) v
INNER JOIN `rasgos` r ON r.clave = v.clave AND r.tipo = "configuracion";

-- (las 11 sin afinidad real no llevan ningun INSERT: se quedaron
--  limpias en el paso 1 y ahi se quedan)

COMMIT;

-- --- comprobacion ------------------------------------------------------------
-- Debe salir 520.
SELECT COUNT(*) AS con_arquetipo FROM cromo_rasgos cr
JOIN rasgos r ON r.id_rasgo = cr.id_rasgo
WHERE r.tipo = "configuracion" AND cr.id_cromo IN (3,4,5,6,7,8,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,137,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,164,165,166,167,168,169,170,171,172,173,174,175,176,177,178,179,180,181,182,183,187,188,189,190,191,192,193,194,195,196,197,198,199,200,201,202,203,204,205,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,226,230,231,232,233,234,235,236,237,238,239,240,241,242,243,244,245,246,247,248,252,253,254,255,256,257,258,259,260,261,262,263,264,265,266,267,268,269,270,271,275,276,277,278,279,280,281,282,283,284,285,286,287,288,289,290,291,292,293,294,298,299,300,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,321,322,323,324,325,326,327,328,329,330,331,332,333,334,335,336,337,338,339,343,344,345,346,347,348,349,350,351,352,353,354,355,356,357,358,359,360,361,362,366,367,368,369,370,371,372,373,374,375,376,377,378,379,380,381,382,383,384,385,389,390,391,392,393,394,395,396,397,398,399,400,401,402,403,404,405,406,407,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426,427,428,429,430,434,435,436,437,438,439,440,441,442,443,444,445,446,447,448,449,450,451,452,453,457,458,459,460,461,462,463,464,465,466,467,468,469,470,471,472,473,474,478,479,480,481,482,483,484,485,486,487,488,489,490,491,492,493,494,495,496,497,501,502,503,504,505,506,507,508,510,511,514,515,516,517,518,519,520,521,522,523,524,525,526,527,528,529,530,531,532,533,534,535,536,537,538,539,540,541,542,543,544,545,546,547,548,549,550,551,552,553,554,555,556,557,558,559,560,561,562,563,564,565,566,567,568,569,570,571,572,573,574,575,576,577,578,579,580,581,582,583,584,585,586,587,588,589,590,591,592,593,594,595,596,597,598,599,600,601,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,627,628,629,630,631,632,633,634,635,636);

-- Debe salir 0: ninguna de las 11 sin afinidad con arquetipo.
SELECT COUNT(*) AS no_afi_con_arquetipo FROM cromo_rasgos cr
JOIN rasgos r ON r.id_rasgo = cr.id_rasgo
WHERE r.tipo = "configuracion" AND cr.id_cromo IN (551,552,553,554,555,556,557,558,559,560,561);
