# Lógica Visual: Renderizado del Diagrama de Árbol de Causas

Para renderizar visualmente el diagrama de forma automática (sin que los nodos se superpongan o salgan desordenados), utilizamos un algoritmo basado en **Grafos Dirigidos Acíclicos (DAG)** implementado en el componente principal de React.

Aquí te detallo la lógica exacta que usamos (la función `prepararCanvas`) para que puedas replicarla en el nuevo proyecto.

## 1. Algoritmo de Cálculo de Profundidades (Eje Y)

El objetivo es que las **Causas Raíz** (que no son provocadas por nadie más) queden en la parte superior de la pantalla, y a medida que el árbol avanza, los efectos vayan bajando hasta llegar a la **Lesión** en la parte más baja.

```javascript
// 1. Inicializar profundidades en 0 (Nivel Superior / Causas Raíz)
const depths = {};
hechos.forEach(h => depths[h.id] = 0);

// 2. Calcular ruta más larga (Relajación de DAG)
// Pasamos por los enlaces múltiples veces (tantas como hechos haya) para asegurar 
// que las flechas empujen los nodos dependientes hacia abajo.
for (let i = 0; i < hechos.length; i++) {
   enlaces.forEach(link => {
      if (depths[link.origen_id] !== undefined && depths[link.destino_id] !== undefined) {
          if (depths[link.origen_id] + 1 > depths[link.destino_id]) {
              // El destino siempre debe estar un nivel por debajo del origen
              depths[link.destino_id] = depths[link.origen_id] + 1;
          }
      }
   });
}
```

## 2. Agrupación y Cálculo de Posiciones Horizontales (Eje X)

Una vez que sabemos la profundidad (fila) de cada nodo, calculamos su posición horizontal (columna) para centrarlos y separarlos equitativamente.

```javascript
// 3. Agrupar los nodos por su profundidad
const nodesByDepth = {};
hechos.forEach(h => {
   const d = depths[h.id];
   if (!nodesByDepth[d]) nodesByDepth[d] = [];
   nodesByDepth[d].push(h);
});

// 4. Asignar posiciones exactas (X, Y) a cada nodo
const width = 3000; // Lienzo virtual ultra ancho para scroll
const nodosConPos = hechos.map(n => {
  const d = depths[n.id];
  const levelNodes = nodesByDepth[d];
  
  // Ordenar de manera estable (por ID) para que no cambien de lugar al azar
  levelNodes.sort((a,b) => a.id - b.id); 
  const indexInLevel = levelNodes.findIndex(node => node.id === n.id);
  
  // Espaciado horizontal
  const spacing = 300; // Pixeles entre cada nodo en la misma fila
  const totalWidthLevel = (levelNodes.length - 1) * spacing;
  
  // Encontrar el punto inicial en X para que la fila quede centrada
  const startX = (width / 2) - (totalWidthLevel / 2) - 80; 
  const x = startX + (indexInLevel * spacing);
  
  // Espaciado Vertical (Eje Y)
  const verticalSpacing = 280;
  const y = 50 + (d * verticalSpacing);

  return { ...n, x, y };
});
```

## 3. Lógica de Trazado de Flechas (SVG)

Las flechas se dibujan utilizando `<svg><line/></svg>` superpuesto detrás de los nodos (CSS de posición absoluta). 
La magia aquí es que la línea siempre sale de la base del nodo "Origen" y apunta al techo del nodo "Destino".

```javascript
// Dentro del render o una función drawLinks()
return enlaces.map((link, index) => {
  const origen = nodesRender.find(n => n.id === link.origen_id);
  const destino = nodesRender.find(n => n.id === link.destino_id);

  // Obtener dimensiones según la clase CSS que tenga el nodo
  const dimOrigen = { w: 140, h: 140 }; // Modificar según CSS
  const dimDestino = { w: 140, h: 140 };

  // El origen es la causa (arriba). La flecha sale de su centro inferior:
  const x1 = origen.x + (dimOrigen.w / 2);
  const y1 = origen.y + dimOrigen.h; 
  
  // El destino es el efecto (abajo). La flecha entra a su centro superior:
  const x2 = destino.x + (dimDestino.w / 2);
  const y2 = destino.y - 12; // -12 compensa el tamaño de la punta de la flecha
  
  // Lógica de colores según el tipo de conjunción
  let color = "#94a3b8"; // Gris por defecto (cadena)
  if (link.tipo_relacion === "conjuncion") color = "#f59e0b"; // Naranja
  if (link.tipo_relacion === "disyuncion") color = "#8b5cf6"; // Morado

  return (
    <line 
      key={index}
      x1={x1} y1={y1} x2={x2} y2={y2}
      stroke={color} 
      strokeWidth="3"
      markerEnd="url(#arrow)"
    />
  );
});
```

## 4. Drag & Drop Visual (Eventos de Ratón)

Para que los nodos se puedan mover libremente después de renderizados:
- **`onMouseDown`**: Guardas el `id` del nodo en un estado `draggedNodeId`.
- **`onMouseMove` (En el contenedor padre `canvas`)**: Si hay un `draggedNodeId`, actualizas su posición `x` e `y` según el ratón (restando los márgenes del contenedor con `getBoundingClientRect()`). Como `x` e `y` están vinculados por React, el nodo y sus flechas se mueven mágicamente a la vez.
- **`onMouseUp`**: Pones el `draggedNodeId` en `null` para soltarlo.
