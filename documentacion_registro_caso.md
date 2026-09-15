# Documentación: Sistema de Registro de Caso (GIATEP)

Este documento detalla la estructura, lógica y dependencias del sistema "Registro de Accidente de Trabajo / Trayecto" para facilitar su migración o adaptación a otro proyecto Next.js (App Router).

## 1. Arquitectura General
El sistema es una aplicación full-stack construida sobre Next.js que utiliza IA generativa (Nvidia API) para asistir en el análisis de accidentes laborales mediante el método del "Árbol de Causas". 

Se divide en 3 capas principales:
1. **Frontend (UI + Lógica de Estado):** Un formulario de registro de casos y un visualizador/editor interactivo de árboles de causas con un lienzo (canvas) drag & drop.
2. **Backend (API Route):** Un endpoint que orquesta las llamadas al LLM de Nvidia, estructurando los prompts y forzando salidas en JSON.
3. **Base de Datos (Firebase):** Almacenamiento histórico y sistema de colas en tiempo real (Firestore) para procesar peticiones IA de manera asíncrona.

## 2. Archivos Clave a Migrar

Para mover este sistema al nuevo proyecto, debes copiar la siguiente estructura de archivos:

```text
/src
 ├── app
 │   ├── api
 │   │   └── nvidia
 │   │       └── route.js           <-- Endpoint de la IA (Llama 3)
 │   │
 │   └── registro-caso
 │       ├── page.js                <-- Componente UI Principal (Cliente)
 │       └── registro.css           <-- Estilos específicos del registro y canvas
 │
 └── lib
     └── firebase.js                <-- Configuración de tu base de datos (Si aplica)
```

## 3. Desglose de Componentes

### A. Frontend (`src/app/registro-caso/page.js`)
- **Directiva:** Usa `"use client"` porque maneja estados de React y eventos de ratón.
- **Estados (State):** 
  - Maneja los datos del formulario (`trabajador`, `caso`, `relato`).
  - Mantiene el estado de la IA (`resultadosIA`, `feedbacks`, `isLoading`).
  - Mantiene el estado del Canvas (`nodesRender`, `draggedNodeId`).
- **Flujo IA en 3 Pasos:**
  1. `Extraer Hechos`: Envía el relato al LLM y obtiene una lista secuencial de hechos.
  2. `Generar Diagrama`: Toma los hechos y pide al LLM que los relacione lógicamente.
  3. `Generar Medidas`: Toma las causas raíz resultantes y genera medidas preventivas.
- **Correcciones (Feedback):** Permite al usuario enviar correcciones al LLM si este se equivoca en algún paso.
- **Canvas Interactivo (Drag & Drop):**
  - Implementado con elementos `<div>` posicionados de forma absoluta (`left`, `top`).
  - Las flechas de conexión se dibujan mediante un elemento SVG superpuesto `<svg><line/></svg>`.
  - La lógica `prepararCanvas()` calcula las profundidades (Niveles del Árbol) para organizar automáticamente los nodos de arriba (causas raíz) hacia abajo (daño).

### B. Backend de IA (`src/app/api/nvidia/route.js`)
- **API Key:** Requiere una clave de Nvidia (`nvapi-...`). Deberías mover esto a variables de entorno (`.env.local`) en el nuevo proyecto (`process.env.NVIDIA_API_KEY`).
- **Modelo:** `meta/llama-3.2-90b-vision-instruct`.
- **Lógica:** 
  - Recibe un cuerpo JSON con el `relato`, el `step` actual (hechos, enlaces, medidas) y el contexto previo.
  - Inyecta el prompt adecuado desde el diccionario `prompts`.
  - Fuerza una temperatura baja (`0.1`) para garantizar resultados analíticos consistentes.
  - Extrae y limpia la respuesta del modelo usando expresiones regulares para devolver un JSON válido.

### C. Estilos (`src/app/registro-caso/registro.css`)
- Contiene un sistema de grillas CSS (`.form-grid`) para el diseño del formulario.
- Estilos para la barra lateral (`.sidebar`) y layout general.
- Contiene los estilos cruciales para los nodos del árbol (`.nodo`, `.nodo.hecho`, `.nodo.lesion`, `.nodo.dragging`) que definen cómo se ven y reaccionan al pasar el ratón por encima.

## 4. Guía de Migración Paso a Paso

1. **Instalar Dependencias:** Si el nuevo proyecto no tiene Firebase, instálalo:
   ```bash
   npm install firebase
   ```
2. **Copiar Carpetas:**
   - Copia la carpeta `registro-caso` dentro de `src/app/` de tu nuevo proyecto.
   - Copia la carpeta `api/nvidia` dentro de `src/app/api/`.
   - Asegúrate de tener la configuración de Firebase lista (típicamente en `src/lib/firebase.js`).
3. **Variables de Entorno (Recomendado):**
   - En lugar de dejar la API KEY harcodeada en `route.js`, crea un archivo `.env.local` en la raíz del nuevo proyecto:
     ```env
     NVIDIA_API_KEY=nvapi-tu-clave-aqui
     ```
   - Modifica `route.js` para usar: `const NVIDIA_API_KEY = process.env.NVIDIA_API_KEY;`
4. **Verificar Imports:**
   - En `page.js`, asegúrate de que la ruta a Firebase (`import { db } from "../../lib/firebase";`) apunte al lugar correcto en el nuevo proyecto.

## 5. Notas Importantes sobre el Funcionamiento Local
- El componente `page.js` tiene un selector de motor (`engine`): "Nube" o "Local".
- El motor en la nube funciona directamente llamando a `/api/nvidia`.
- El motor local asume que hay un worker local escuchando una colección de Firebase (`peticiones_ia`). Si no vas a usar un worker local (como LM Studio) en el nuevo proyecto, puedes limpiar esta lógica del código o mantener siempre seleccionado el modo "cloud".
