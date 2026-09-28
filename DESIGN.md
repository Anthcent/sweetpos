---
name: Sweet POS
description: Sistema operativo dulce, preciso y sereno para heladerías y cafeterías.
colors:
  primary: "#B94F7B"
  primary-soft: "#FCE8F0"
  primary-deep: "#9D3E68"
  lavender-soft: "#F0ECFA"
  mint-soft: "#E8F5EF"
  canvas: "#FBFAFC"
  surface: "#FFFFFF"
  ink: "#293241"
  muted: "#64748B"
  border: "#E9E4EC"
typography:
  headline:
    fontFamily: "Outfit, Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.55
  label:
    fontFamily: "Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.3
rounded:
  sm: "10px"
  md: "16px"
  lg: "24px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  button-accent:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "10px 14px"
---

# Design System: Sweet POS

## 1. Overview

**Creative North Star: "Dulce precisión"**

Sweet POS mezcla orden profesional, calma de pastelería y encanto de boutique. Base casi blanca, superficies nítidas y acentos pastel crean calidez sin distraer del flujo operativo.

Sistema compacto, táctil y consistente. Personalidad aparece en curvas moderadas, color escaso y pequeños detalles de marca. Nunca debe sentirse infantil ni recargado.

**Key Characteristics:**
- Pasteles controlados sobre fondo neutro.
- Jerarquía tipográfica amable y firme.
- Profundidad tonal, no sombras pesadas.
- Interacciones rápidas y visibles.
- Responsive estructural para terminal, tableta y móvil.

## 2. Colors

Paleta de confitería contemporánea: rosa frambuesa suave como voz principal, lavanda y menta como acompañamiento semántico.

### Primary
- **Rosa frambuesa:** acción destacada, selección actual y señales de marca.
- **Rosa leche:** fondos seleccionados y zonas de énfasis suave.
- **Frambuesa profunda:** texto sobre fondos rosa claro y estados activos.

### Secondary
- **Lavanda nube:** variedad ambiental y superficies secundarias.
- **Menta crema:** estados positivos y apoyo visual.

### Neutral
- **Porcelana:** lienzo general.
- **Nata blanca:** controles y superficies de trabajo.
- **Tinta arándano:** texto principal y acciones de alta prioridad.
- **Pizarra suave:** texto secundario con contraste AA.
- **Borde malva:** separación estructural discreta.

**The Sweet Accent Rule.** Rosa ocupa menos del 15% de cada pantalla; identifica marca, selección y acción, nunca rellena grandes áreas sin función.

## 3. Typography

**Display Font:** Nunito Sans (con fallback de sistema)
**Body Font:** Nunito Sans (con fallback de sistema)

**Character:** Sans redondeada y humana, amable sin parecer infantil. Pesos altos dan precisión a títulos y cifras.

### Hierarchy
- **Headline** (800, 1.5rem, 1.2): título principal de cada módulo.
- **Title** (700, 1.125rem, 1.3): paneles, modales y agrupaciones.
- **Body** (500, 1rem, 1.55): contenido operativo.
- **Label** (700, 0.75rem, 0.04em máximo): etiquetas breves y estados.

**The Calm Type Rule.** Mayúsculas y tracking amplio quedan reservados para etiquetas de dos o tres palabras; nunca para instrucciones completas.

## 4. Elevation

Profundidad principalmente tonal. Superficies descansan sobre canvas ligeramente teñido; sombras compactas aparecen solo en navegación, modales y estados interactivos.

### Shadow Vocabulary
- **Ambient low** (`0 2px 8px rgba(91, 68, 87, 0.06)`): navegación y superficies interactivas.
- **Overlay** (`0 8px 24px rgba(61, 43, 57, 0.14)`): modales únicamente.

**The Flat-by-Default Rule.** Tarjetas estáticas usan borde o contraste tonal. Nunca combinan borde con sombra amplia decorativa.

## 5. Components

### Buttons
- **Shape:** curvas suaves y compactas (12px).
- **Primary:** tinta arándano con texto blanco para acciones operativas principales.
- **Hover / Focus:** cambio tonal breve y anillo rosa de 3px claramente visible.
- **Accent:** rosa frambuesa para cobros, confirmaciones y selección destacada.

### Chips
- **Style:** fondos pastel claros con texto oscuro del mismo matiz.
- **State:** seleccionado usa mayor contraste; no seleccionado permanece neutro.

### Cards / Containers
- **Corner Style:** 16px para paneles principales, 12px para elementos internos.
- **Background:** nata blanca o pastel muy tenue.
- **Shadow Strategy:** plana por defecto; elevación compacta al interactuar.
- **Border:** malva tenue de 1px cuando separación sea necesaria.
- **Internal Padding:** 16px a 24px.

### Inputs / Fields
- **Style:** fondo blanco, borde malva, radio de 12px.
- **Focus:** borde frambuesa y anillo rosa translúcido.
- **Error / Disabled:** rojo suave con texto oscuro; gris tonal para deshabilitado.

### Navigation
- Barra lateral compacta en escritorio y barra inferior desplazable en móvil. Estado activo combina rosa leche, frambuesa profunda e indicador corto.

### Product Card
- Color del producto aparece como muestra controlada, no como superficie saturada. Hover eleva 2px y conserva estabilidad táctil.

## 6. Do's and Don'ts

### Do:
- **Do** usar rosa para marca, selección y acción principal.
- **Do** mantener contraste WCAG AA en texto, placeholders y foco.
- **Do** conservar áreas táctiles mínimas de 44px cuando espacio lo permita.
- **Do** usar transiciones de estado entre 150ms y 220ms.

### Don't:
- **Don't** hacer que Sweet POS parezca infantil ni recargado.
- **Don't** usar colores chillones, gradientes decorativos o exceso de sombras.
- **Don't** agregar animaciones juguetonas o movimiento sin función.
- **Don't** convertir la interfaz en software corporativo frío y genérico.
- **Don't** usar tarjetas con radios superiores a 16px salvo modal de acceso existente.
