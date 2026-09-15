document.addEventListener('DOMContentLoaded', () => {
    const btnAnalizar = document.getElementById('btnAnalizar');
    const btnGuardar = document.getElementById('btnGuardar');
    const formCaso = document.getElementById('formCaso');
    const canvas = document.getElementById('canvas');
    const loading = document.getElementById('loading');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let nodesData = [];
    let linksData = [];
    let resultadosGlobales = {};

    // Variables for drag
    let draggedNode = null;
    let offsetX = 0;
    let offsetY = 0;

    btnAnalizar.addEventListener('click', async () => {
        const relato = document.getElementById('relato').value;
        if (!relato) return alert('Debes ingresar un relato');

        loading.classList.remove('hidden');
        btnAnalizar.disabled = true;
        canvas.innerHTML = '<svg id="svgLines"></svg>'; // Reset canvas
        document.getElementById('medidasContainer').classList.add('hidden');
        document.getElementById('listaMedidas').innerHTML = '';

        try {
            // Paso 1: Extraer Hechos
            loading.innerText = "Procesando 1/3: Extrayendo Hechos...";
            const hechosRes = await callNvidiaApi('extract_facts', relato);
            nodesData = hechosRes;
            resultadosGlobales.hechos = nodesData;
            renderNodes(nodesData);

            // Paso 2: Generar Diagrama
            loading.innerText = "Procesando 2/3: Diagramando Árbol de Causas...";
            const diagramaRes = await callNvidiaApi('generate_diagram', relato, JSON.stringify(nodesData));
            linksData = diagramaRes.enlaces || [];
            resultadosGlobales.enlaces = linksData;
            drawLines();

            // Paso 3: Generar Medidas
            loading.innerText = "Procesando 3/3: Generando Medidas Preventivas...";
            const medidasRes = await callNvidiaApi('generate_measures', relato, JSON.stringify(diagramaRes));
            resultadosGlobales.medidas = medidasRes;
            renderMedidas(medidasRes);

            btnGuardar.classList.remove('hidden');
        } catch (error) {
            console.error(error);
            alert("Error en el análisis IA.");
        } finally {
            loading.classList.add('hidden');
            btnAnalizar.disabled = false;
        }
    });

    btnGuardar.addEventListener('click', async () => {
        const nombre = document.getElementById('nombre_trabajador').value;
        const titulo = document.getElementById('titulo_caso').value;
        const relato = document.getElementById('relato').value;

        try {
            const response = await fetch('/registro-caso', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    nombre_trabajador: nombre,
                    titulo_caso: titulo,
                    relato: relato,
                    resultados_ia: resultadosGlobales
                })
            });
            const data = await response.json();
            alert(data.message);
        } catch (error) {
            console.error(error);
            alert("Error al guardar el caso.");
        }
    });

    async function callNvidiaApi(step, relato, context = "") {
        const res = await fetch('/api/nvidia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ step, relato, context })
        });
        return await res.json();
    }

    function renderNodes(nodes) {
        // Basic layout engine
        let startX = 50;
        let startY = 50;
        nodes.forEach((node, i) => {
            const div = document.createElement('div');
            div.className = 'nodo';
            div.id = 'node-' + node.id;
            div.innerText = node.descripcion;
            
            // Layout logic
            div.style.left = startX + 'px';
            div.style.top = startY + 'px';
            startY += 80;
            if (startY > 500) {
                startY = 50;
                startX += 200;
            }

            // Drag events
            div.addEventListener('mousedown', startDrag);
            canvas.appendChild(div);
        });
    }

    function renderMedidas(medidas) {
        document.getElementById('medidasContainer').classList.remove('hidden');
        const lista = document.getElementById('listaMedidas');
        medidas.forEach(m => {
            const li = document.createElement('li');
            li.innerText = m.medida;
            lista.appendChild(li);
        });
    }

    // --- DRAG AND DROP ---
    function startDrag(e) {
        draggedNode = e.target;
        offsetX = e.clientX - draggedNode.offsetLeft;
        offsetY = e.clientY - draggedNode.offsetTop;
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    }

    function onDrag(e) {
        if (!draggedNode) return;
        draggedNode.style.left = (e.clientX - offsetX) + 'px';
        draggedNode.style.top = (e.clientY - offsetY) + 'px';
        drawLines();
    }

    function stopDrag() {
        draggedNode = null;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
    }

    function drawLines() {
        const svg = document.getElementById('svgLines');
        if(!svg) return;
        svg.innerHTML = ''; // clear

        linksData.forEach(link => {
            const originNode = document.getElementById('node-' + link.origen);
            const targetNode = document.getElementById('node-' + link.destino);

            if (originNode && targetNode) {
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                // Calculate center points
                const x1 = originNode.offsetLeft + (originNode.offsetWidth / 2);
                const y1 = originNode.offsetTop + (originNode.offsetHeight / 2);
                const x2 = targetNode.offsetLeft + (targetNode.offsetWidth / 2);
                const y2 = targetNode.offsetTop + (targetNode.offsetHeight / 2);

                line.setAttribute('x1', x1);
                line.setAttribute('y1', y1);
                line.setAttribute('x2', x2);
                line.setAttribute('y2', y2);
                line.setAttribute('stroke', '#94a3b8');
                line.setAttribute('stroke-width', '2');
                svg.appendChild(line);
            }
        });
    }
});
