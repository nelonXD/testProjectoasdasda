# Metodología: Generación de Hechos y Árbol de Causas

Este documento explica la lógica paso a paso para analizar un accidente laboral utilizando el Método del Árbol de Causas, dividiendo el proceso en la extracción de hechos y la construcción del diagrama lógico.

---

## FASE 1: Extracción de Hechos (Lista de Hechos)

El objetivo de esta fase es desglosar el relato del accidente en piezas mínimas de información (nodos) completamente objetivas.

### Reglas de Extracción
1. **Hechos Objetivos:** Solo datos concretos, reales y verificables. **CERO juicios de valor**.
   - ❌ *Incorrecto:* "El trabajador actuó sin precaución."
   - ✅ *Correcto:* "El trabajador no utilizaba el arnés de seguridad."
2. **Brevedad Extrema:** Cada hecho debe responder al *¿Quién hizo qué, cómo y dónde?* en un máximo de 8 a 10 palabras.
3. **Prueba de Necesidad:** Para cada hecho que extraigas, pregúntate: *«Si este hecho no se hubiese producido, ¿habría ocurrido el accidente?»*. Si la respuesta es SÍ, ese hecho es irrelevante y debes descartarlo.
4. **Identificación de Variaciones:** Presta especial atención a lo que cambió respecto a las condiciones habituales de trabajo (ej: "Lluvia inusual", "Máquina sustituta").
5. **Tipos de Nodos:**
   - **Hecho:** Un suceso o variación puntual en el tiempo (ej: "Pie derecho resbala").
   - **Permanente:** Una condición preexistente o estable (ej: "Piso mojado", "Falta de capacitación").
   - **Lesión/Daño:** El resultado final del accidente (ej: "Fractura de tibia"). **Debe ser siempre el último hecho de la lista.**

### Ejemplo de Resultado (JSON)
```json
[
  { "id": 1, "nro_secuencia": 1, "descripcion": "Falta de señalización", "tipo_nodo": "permanente" },
  { "id": 2, "nro_secuencia": 2, "descripcion": "Piso de pasillo mojado", "tipo_nodo": "permanente" },
  { "id": 3, "nro_secuencia": 3, "descripcion": "Trabajador camina por pasillo", "tipo_nodo": "hecho" },
  { "id": 4, "nro_secuencia": 4, "descripcion": "Trabajador resbala y cae", "tipo_nodo": "hecho" },
  { "id": 5, "nro_secuencia": 5, "descripcion": "Contusión en la rodilla", "tipo_nodo": "lesion" }
]
```

---

## FASE 2: Construcción del Diagrama (Árbol de Causas)

El objetivo aquí es conectar los hechos extraídos en la Fase 1 mediante enlaces lógicos de causa y efecto.

### Reglas de Construcción (¡Crítico!)
1. **NO es una línea de tiempo:** No conectes hechos simplemente porque uno ocurrió antes que el otro (ej: H1 -> H2 -> H3). Se conectan **solo si hay una relación causal estricta**.
2. **Se construye de ATRÁS hacia ADELANTE:**
   - Empieza SIEMPRE por el nodo final (La Lesión/Daño).
   - Pregúntate: *¿Qué hechos fueron las causas INMEDIATAS y necesarias para esta lesión?*
   - Luego, toma esa causa inmediata y vuelve a preguntarte: *¿Por qué ocurrió esto?*
   - Repite el proceso hasta llegar a los hechos raíz (condiciones permanentes).
3. **Prueba Lógica de Necesidad (El filtro de oro):**
   - Antes de trazar una flecha entre una CAUSA y un EFECTO, haz esta prueba: *«Si la causa NO hubiese ocurrido, ¿habría tenido lugar el efecto?»*
   - La respuesta obligatoria para conectarlos debe ser **NO**.
4. **Tipos de Ramificaciones:**
   - **Cadena:** Un hecho tiene una sola causa directa (X causa Y).
   - **Conjunción:** Un hecho necesita de **dos o más causas** simultáneas para ocurrir. (Si falta una, el efecto no ocurre).

### Cómo leer o estructurar los enlaces (JSON)
Cada conexión es un vector con un "Origen" (Causa) y un "Destino" (Efecto). Nunca inventes IDs, usa solo los generados en la Fase 1.

```json
[
  { "origen_id": 3, "destino_id": 4, "tipo_relacion": "conjuncion" }, 
  { "origen_id": 2, "destino_id": 4, "tipo_relacion": "conjuncion" },
  { "origen_id": 1, "destino_id": 2, "tipo_relacion": "cadena" },
  { "origen_id": 4, "destino_id": 5, "tipo_relacion": "cadena" }
]
```
*(Explicación del ejemplo: El resbalón [4] ocurre por la conjunción de caminar por el pasillo [3] Y que el piso esté mojado [2]. A su vez, el piso está mojado sin que nadie sepa porque falta señalización [1]).*

---

### Resumen para su programación / Prompts de IA
Si estás adaptando esto para que una IA lo procese automáticamente, la clave es **dividir las peticiones**. No le pidas a la IA que haga todo de una vez.
- **Prompt 1:** Entrégale el relato y pídele exclusivamente la "Lista de Hechos".
- **Prompt 2:** Entrégale la "Lista de Hechos" generada y pídele exclusivamente generar los "Enlaces Lógicos" (matriz causa-efecto).
