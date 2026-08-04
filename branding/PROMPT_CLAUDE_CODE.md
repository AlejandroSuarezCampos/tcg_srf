# Prompt para Claude Code — Rebrand completo de Superliga Frontier TCG

> Copia y pega todo el bloque de abajo como primer mensaje en Claude Code, dentro del repositorio `tcg_srf`.

---

Vas a rediseñar y reconstruir por completo la interfaz de **Superliga Frontier TCG**, un TCG coleccionable fan-made basado en Inazuma Eleven: Victory Road. El backend (PHP + MariaDB) se mantiene; lo que cambia de raíz es todo el sistema de diseño y la interfaz. Tienes permiso explícito para eliminar y reescribir estilos existentes — no hay que preservar el CSS actual por compatibilidad, solo su lógica de fondo donde se indique.

## 0. Antes de tocar código

Lee primero `branding/Brand-Identity-Briefing-Superliga-Frontier-TCG.docx` (documento maestro de marca completo, 38 secciones) para tener el contexto entero. Este prompt es el resumen ejecutable de ese documento; si algo no queda claro aquí, la respuesta está ahí.

Después, audita el estado actual del repo (`landing.php`, `navbar.php`, `assets/css/style.css`, `db/tcg.sql`, `panel/`) y confírmame en un mensaje corto tu plan de fases antes de empezar a escribir código, para que pueda dar el visto bueno.

## 1. Qué es el proyecto

- Producto: extensión coleccionable de la liga fan-made **Superliga Frontier** (Inazuma Eleven: Victory Road). No es un TCG de ficción genérico: las cartas representan jugadores, presidentes, entrenadores y escudos **reales** de una comunidad activa.
- Ya existe una v1 funcional en PHP/MariaDB: landing, login/registro, apertura de sobres (`sobres.php`), colección (`coleccion.php`), álbum de progreso (`album.php`), mercado P2P (`mercado.php`), perfil (`perfil.php`), códigos canjeables, y panel de administración (`panel/`: cromos, expansiones, sobres, usuarios).
- Próxima etapa de producto (aún no implementada, hay que construirla): **deck building** al estilo Adrenalyn XL/Panini, **misiones**, **minijuegos**, y **duelos 1v1** entre usuarios apostando monedas (ambos apuestan la misma cantidad, gana quien gane el duelo) o cartas (ambos deben apostar una carta de la misma rareza, elegida por quien crea la sala).
- 100% gratuito, sin monetización real, exclusivo para participantes de la Superliga Frontier — no se abre a público general.
- Ejecutado en solitario, sin fecha de lanzamiento fija: prioriza fases completas y estables sobre todo a la vez.
- Legal: proyecto fan-made sin ánimo de lucro; Inazuma Eleven es propiedad de Level-5; no afiliado. Este aviso debe quedar visible de forma consistente en todo el sitio (hoy solo está, de forma inconsistente, en el footer de la landing — corrígelo).

## 2. Sistema de marca ya decidido (no lo reinventes, impleméntalo)

**Color** — mantener el ámbar como ancla de continuidad, reinterpretado más sobrio:
```
--void:        #0B0C10   fondo principal
--panel:       #16181D   tarjetas / superficies elevadas
--line:        rgba(255,255,255,.08)
--frost:       #EDEEF1   texto principal
--frost-dim:   #93959F   texto secundario
--amber:       #E8752A   acento primario / acciones / celebración
--amber-light: #FFB168   hover / resplandores
--amber-ink:   #2B1204   texto sobre fondo ámbar
--success:     #3DDC9B   (tarjeta amarilla/roja de fútbol → aviso/peligro, ya es una decisión acertada, consérvala)
--warning:     #F2B134
--danger:      #F0554A
--info:        #5B96F2
```
Rarezas (6 niveles, cada uno con marca redundante no cromática — icono/patrón, no solo color, por accesibilidad y por equidad en los duelos con apuesta):
Común (gris, 60%, sin adorno) · Poco común (verde, 25%, 1 marca) · Raro (azul, 10%, 2 marcas) · Épico (violeta, 3.5%, 3 marcas) · Legendario (degradado ámbar-fuego + borde metálico, 1%, corona) · **SRF** (holográfico animado multi-tono + borde blanco, 0.5%, la única rareza con animación por defecto — es el momento más espectacular del producto, consérvalo y mejóralo).

**Tipografía** — migrar a **Geist Sans** (UI/body) + **Geist Mono** (monedas, estadísticas, contadores, timestamps — nunca texto editorial). Fallback a Inter si Geist no carga. Escala: display 56–96px/700 (solo hero y revelado SRF), H1 40–48px/700, H2 28–32px/700, H3 20–22px/600, body 15–16px/400–500, caption 12–13px/600 mayúsculas tracking+8%.

**Iconografía** — mantener **Phosphor Icons** (ya integrado). Regular para navegación/neutro, Bold/Fill solo en estados de celebración (duelo ganado, logro, carta SRF).

**Arte de cartas** — el arte (mezcla de IA + edición manual del propio Alejandro sobre material oficial de Level-5; escudos generados aparte por un agente de IA especializado) **no lo generas tú**, ya existe o se genera fuera de este flujo. Lo que sí construyes es el marco que lo contiene: **cada imagen se muestra siempre completa** (`object-fit: contain`, nunca `cover`/crop), sobre una placa de fondo con halo de color según rareza, para absorber que las imágenes de origen no tengan tamaños ni proporciones uniformes. Esta es la decisión más importante del sistema de cartas.

**Espaciado**: escala 4·8·12·16·24·32·48·64px. **Radios**: 8/12/16/22px (mayor en tarjeta de carta y modales, menor en controles). **Sombras**: 3 niveles sutiles, reservar la más marcada para elementos flotantes (modal, hover de carta, celebración de sobre).

**Grid / breakpoints** (mobile-first): <480px 1 columna · 480–1024px 8 columnas · 1024–1440px 12 columnas, contenido max 1280px · 1440–1920px contenido max 1440px · >1920px contenido centrado max 1600–1680px, sin estirar componentes.

## 3. Tono y voz — límite estricto, no lo amplíes por tu cuenta

Personalidad: prestigiosa, precisa, cálida por dentro, confiable, con humor **estrictamente acotado a dos guiños**, ninguno más:
1. El apodo "**Superruina Frontier**" — solo en contextos secundarios/informales (loading states, redes, easter eggs), nunca en el lockup principal del logo.
2. La frase "**a ese Gonzalo le gano fácil**" — tratada como cita histórica de folclore de liga, reservada a espacios no críticos (footer, estados vacíos, 404).

Fuera de esos dos puntos, el tono es editorial y serio (como una ficha oficial de competición), sin argot gamer genérico, sin bromear con nombres de jugadores reales, sin superlativos vacíos ("¡increíble!"). Si generas microcopy nuevo (botones, vacíos, errores, onboarding), contrástalo contra esta regla antes de darlo por bueno.

## 4. Principios de diseño (aplícalos en cada decisión de implementación)

1. El arte manda — la interfaz sirve a la ilustración, nunca compite con ella.
2. Un acento, cero ruido — el ámbar solo en lo que importa de verdad.
3. La rareza se lee sin color — redundancia no cromática siempre.
4. Ceremonia con propósito — el motion se reserva para sobre y duelo; el resto de microinteracciones son sobrias.
5. Denso pero nunca abarrotado — mucha info (mercado, stats, duelos), jerarquía clara.
6. Un solo chiste, dos veces — ver sección 3.
7. Se adapta, no se recorta — ver arte de cartas, sección 2.

## 5. Accesibilidad — no negociable, WCAG 2.2

- Contraste: texto normal ≥4.5:1, texto grande/encabezados ≥3:1.
- Foco visible consistente en todo elemento interactivo (`:focus-visible`), nunca tapado por nav fija ni toasts.
- Objetivo táctil mínimo 24×24px, estándar interno 44×44px en acciones primarias.
- Todo operable por teclado: filtros, modales (foco atrapado, cierre con Esc), revelado de sobres (saltable), salas de duelo.
- Si el deck builder usa drag-and-drop, debe tener alternativa por tap/clic desde el primer lanzamiento (WCAG 2.2 SC 2.5.7).
- `prefers-reduced-motion` debe cubrir también las nuevas ceremonias de sobre y duelo, no solo lo que ya existe.
- Alt text en todo arte de carta; regiones `aria-live` para resultados de sobre/duelo.

## 6. Fases de trabajo — sigue este orden, no las mezcles

**Fase 0 — Fundamentos.** Antes de tocar una sola pantalla: crea el fichero de tokens de diseño (variables CSS: color, tipografía, espaciado, radio, sombra, motion, breakpoints) y construye el componente de tarjeta de carta definitivo en aislamiento (una página de prueba/estilo-guía sirve). No sigas a la Fase 1 sin este componente aprobado.

**Fase 1 — Reconstrucción del núcleo actual.** Migra por completo al nuevo sistema: `landing.php`, `navbar.php`, `sobres.php` (con la nueva ceremonia de apertura escalada por rareza), `coleccion.php`, `album.php`, `mercado.php`, `perfil.php`, `login.php`/`registro.php`. Ninguna pantalla se queda a medio migrar — termina una antes de empezar la siguiente. Añade el aviso legal de forma consistente en todas las páginas, no solo en el footer de landing.

**Fase 2 — Nuevos sistemas de producto** (requieren añadir tablas nuevas a `db/tcg.sql`, hoy no contempladas: duelos/salas de apuesta, misiones, progreso de minijuegos, mazos de deck builder):
- Deck Builder estilo Adrenalyn XL.
- Duelos: sala con apuesta de monedas (misma cantidad ambos) o de carta (misma rareza obligatoria, elegida por quien crea la sala), con ceremonia de cuenta atrás + revelado + resultado. Evita estética de casino (nada de tragaperras ni ruleta visual) — trátalo como reto deportivo.
- Misiones y minijuegos.
Cada sistema nuevo reutiliza los componentes ya construidos en la Fase 1 (tarjeta de carta, botones, modales, etc.), no crea variantes propias.

**Fase 3 — Pulido y escala.** Rediseña `panel/` (admin) con el mismo sistema de componentes. Refina el motion final de sobre y duelo. Documenta cómo añadir una expansión nueva de temporada dentro del mismo sistema.

## 7. Reglas de trabajo para ti (Claude Code)

- Ve fase por fase. Al terminar cada fase, resume qué cambiaste y por qué antes de continuar a la siguiente.
- No inventes contenido de marca nuevo (nombres, eslóganes, chistes) fuera de lo definido en la sección 3 — si necesitas microcopy nuevo, redáctalo tú mismo siguiendo esas reglas, no preguntes por cada frase suelta.
- Los cambios de esquema de base de datos (Fase 2) deben ser migraciones aditivas y explícitas — no modifiques ni borres datos existentes de `usuarios`, `cromos`, `coleccion`, `mercado` sin decirlo claramente.
- Si una decisión de este prompt entra en conflicto con algo que encuentres en el código (por ejemplo, nombres de tablas, convenciones PHP ya usadas), respeta la convención técnica existente y adapta la implementación — el briefing manda en diseño/marca, el código existente manda en convenciones técnicas.
- Cuando termines una fase, deja el sitio en un estado desplegable y coherente, nunca a medio construir.
