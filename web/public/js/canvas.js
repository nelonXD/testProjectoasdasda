document.addEventListener('DOMContentLoaded', () => {
    const btnAnalizar = document.getElementById('btnAnalizar');
    const btnGuardar = document.getElementById('btnGuardar');
    const iaModelSelect = document.getElementById('ia_model');
    
    // Cargar modelos de IA disponibles
    async function cargarModelosIA() {
        try {
            const res = await fetch('/api/models');
            const data = await res.json();
            
            if (data.data && Array.isArray(data.data)) {
                iaModelSelect.innerHTML = '';
                const recommendedModel = 'qwen/qwen2.5-coder-7b-instruct';
                let matched = false;

                data.data.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.id;
                    option.text = model.id;

                    if (!matched && model.id && model.id.toLowerCase().includes('qwen2.5-coder-7b-instruct')) {
                        option.selected = true;
                        matched = true;
                    }

                    iaModelSelect.appendChild(option);
                });

                if (!matched) {
                    const fallback = document.createElement('option');
                    fallback.value = recommendedModel;
                    fallback.text = recommendedModel;
                    fallback.selected = true;
                    iaModelSelect.insertBefore(fallback, iaModelSelect.firstChild);
                }
            } else {
                iaModelSelect.innerHTML = '<option value="qwen/qwen2.5-coder-7b-instruct">qwen/qwen2.5-coder-7b-instruct</option>';
            }
        } catch (error) {
            console.error("Error cargando modelos:", error);
            iaModelSelect.innerHTML = '<option value="">Servidor local no disponible</option>';
        }
    }

    // Inicializar
    cargarModelosIA();

    const formCaso = document.getElementById('formCaso');
    const canvas = document.getElementById('canvas');
    const loading = document.getElementById('loading');
    const diagramLoading = document.getElementById('diagramLoading');
    const diagramLoadingProgress = document.getElementById('diagramLoadingProgress');
    const diagramLoadingPercent = document.getElementById('diagramLoadingPercent');
    const diagramLoadingStatus = document.getElementById('diagramLoadingStatus');
    const diagramLoadingTime = document.getElementById('diagramLoadingTime');
    const factsPanel = document.getElementById('factsPanel');
    const factsPanelCount = document.getElementById('factsPanelCount');
    const factsList = document.getElementById('factsList');
    const hypothesesPanel = document.getElementById('hypothesesPanel');
    const hypothesesPanelCount = document.getElementById('hypothesesPanelCount');
    const hypothesesList = document.getElementById('hypothesesList');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let nodesData = [];
    let linksData = [];
    let resultadosGlobales = {};
    let loadingStartedAt = 0;
    let loadingTimer = null;
    let loadingHideTimer = null;

    // Variables for drag
    let draggedNode = null;
    let offsetX = 0;
    let offsetY = 0;
    let zoomLevel = 1;

    function formatElapsedTime(milliseconds) {
        const totalSeconds = Math.floor(milliseconds / 1000);
        const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
        const seconds = (totalSeconds % 60).toString().padStart(2, '0');

        return `${minutes}:${seconds}`;
    }

    function updateLoadingProgress(percent, status) {
        const safePercent = Math.max(0, Math.min(100, percent));
        diagramLoadingProgress.style.width = `${safePercent}%`;
        diagramLoadingPercent.innerText = `${safePercent}%`;
        diagramLoadingStatus.innerText = status;
    }

    function startDiagramLoading() {
        if (loadingTimer !== null) {
            window.clearInterval(loadingTimer);
            loadingTimer = null;
        }
        if (loadingHideTimer !== null) {
            window.clearTimeout(loadingHideTimer);
            loadingHideTimer = null;
        }
        loadingStartedAt = Date.now();
        diagramLoading.classList.remove('hidden');
        updateLoadingProgress(0, 'Preparando análisis...');
        diagramLoadingTime.innerText = '00:00';
        loadingTimer = window.setInterval(() => {
            diagramLoadingTime.innerText = formatElapsedTime(Date.now() - loadingStartedAt);
        }, 250);
    }

    function finishDiagramLoading() {
        updateLoadingProgress(100, 'Análisis completado.');
        diagramLoadingTime.innerText = formatElapsedTime(Date.now() - loadingStartedAt);
        if (loadingTimer !== null) {
            window.clearInterval(loadingTimer);
            loadingTimer = null;
        }
        loadingHideTimer = window.setTimeout(() => {
            diagramLoading.classList.add('hidden');
            loadingHideTimer = null;
        }, 450);
    }

    function renderFacts(facts) {
        const confirmedFacts = facts.filter(fact => fact.tipo_nodo !== 'hipotesis');
        const hypotheses = facts.filter(fact => fact.tipo_nodo === 'hipotesis');

        factsList.innerHTML = '';
        hypothesesList.innerHTML = '';
        factsPanelCount.innerText = `${confirmedFacts.length} ${confirmedFacts.length === 1 ? 'hecho' : 'hechos'}`;
        factsPanel.classList.remove('hidden');

        confirmedFacts.forEach((fact, index) => {
            appendFactItem(factsList, fact, index);
        });

        hypothesesPanel.classList.toggle('hidden', hypotheses.length === 0);
        hypothesesPanelCount.innerText = `${hypotheses.length} ${hypotheses.length === 1 ? 'hipótesis' : 'hipótesis'}`;
        hypotheses.forEach((fact, index) => {
            appendFactItem(hypothesesList, fact, index);
        });
    }

    function appendFactItem(list, fact, index) {
            const item = document.createElement('div');
            item.className = 'fact-item';

            const number = document.createElement('span');
            number.className = 'fact-item-number';
            number.innerText = `${index + 1}.`;

            const type = document.createElement('span');
            type.className = `fact-item-type ${fact.tipo_nodo || 'hecho'}`;
            type.innerText = fact.tipo_nodo || 'hecho';

            const description = document.createElement('span');
            description.innerText = fact.descripcion || 'Sin descripción';

            item.append(number, type, description);
            list.appendChild(item);
    }

    const zoomMin = 0.5;
    const zoomMax = 2;
    const zoomStep = 0.1;
    const canvasContent = document.getElementById('canvasContent');
    const canvasZoomLevel = document.getElementById('zoomLevel');

    function updateZoom(nextZoom, focusPoint = null) {
        const canvasContainer = document.getElementById('canvas');
        const previousZoom = zoomLevel;
        zoomLevel = Math.min(zoomMax, Math.max(zoomMin, nextZoom));
        canvasContent.style.transform = `scale(${zoomLevel})`;
        canvasZoomLevel.innerText = `${Math.round(zoomLevel * 100)}%`;

        if (focusPoint && previousZoom !== zoomLevel) {
            const ratio = zoomLevel / previousZoom;
            canvasContainer.scrollLeft = ((canvasContainer.scrollLeft + focusPoint.x) * ratio) - focusPoint.x;
            canvasContainer.scrollTop = ((canvasContainer.scrollTop + focusPoint.y) * ratio) - focusPoint.y;
        }
    }

    document.getElementById('zoomIn').addEventListener('click', () => updateZoom(zoomLevel + zoomStep));
    document.getElementById('zoomOut').addEventListener('click', () => updateZoom(zoomLevel - zoomStep));
    document.getElementById('zoomReset').addEventListener('click', () => updateZoom(1));
    document.getElementById('canvas').addEventListener('wheel', (event) => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        const rect = event.currentTarget.getBoundingClientRect();
        updateZoom(zoomLevel + (event.deltaY < 0 ? zoomStep : -zoomStep), {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top
        });
    }, { passive: false });

    const btnAnalizarOpenAI = document.getElementById('btnAnalizarOpenAI');
    const btnAnalizarNvidiaCloud = document.getElementById('btnAnalizarNvidiaCloud');

    async function procesarCaso(apiEndpoint, btnElement) {
        const relatoText = document.getElementById('relato').value;
        if (!relatoText) return alert('Debes ingresar un relato');

        // Construir el objeto de contexto estructurado
        const contextoEstructurado = {
            titulo: document.getElementById('titulo_caso').value,
            trabajador: {
                nombre: document.getElementById('nombre_trabajador').value,
                edad: document.getElementById('edad').value,
                sexo: document.getElementById('sexo').value,
                profesion: document.getElementById('profesion').value,
                area: document.getElementById('area').value,
                antiguedad: document.getElementById('antiguedad').value
            },
            accidente: {
                fecha: document.getElementById('fecha_accidente').value,
                hora: document.getElementById('hora_accidente').value,
                lugar: document.getElementById('lugar_especifico').value,
                actividad: document.getElementById('actividad_realizada').value,
                relato: relatoText
            }
        };

        // Convertir a string para enviarlo a la IA
        const relato = JSON.stringify(contextoEstructurado, null, 2);

        loading.classList.remove('hidden');
        startDiagramLoading();
        btnAnalizar.disabled = true;
        if (btnAnalizarOpenAI) btnAnalizarOpenAI.disabled = true;
        if (btnAnalizarNvidiaCloud) btnAnalizarNvidiaCloud.disabled = true;

        // Reset canvas (quitar nodos viejos)
        document.querySelectorAll('.nodo-wrapper').forEach(n => n.remove());
        const svg = document.getElementById('svgLines');
        if (svg) {
            svg.querySelectorAll('line, path.causal-link').forEach(element => element.remove());
        }
        canvas.scrollLeft = 0;
        canvas.scrollTop = 0;
        const previousDebug = document.getElementById('debug-enlaces');
        if (previousDebug) previousDebug.remove();
        nodesData = [];
        linksData = [];
        resultadosGlobales = {};
        document.getElementById('medidasContainer').classList.add('hidden');
        document.getElementById('listaMedidas').innerHTML = '';
        factsPanel.classList.add('hidden');
        hypothesesPanel.classList.add('hidden');
        factsList.innerHTML = '';
        hypothesesList.innerHTML = '';
        factsPanelCount.innerText = '0 hechos';
        hypothesesPanelCount.innerText = '0 hipótesis';
        btnGuardar.classList.add('hidden');

        try {
            // Paso 1: Extraer Hechos
            loading.innerText = "Procesando 1/3: Extrayendo Hechos...";
            updateLoadingProgress(33, 'Paso 1 de 3: extrayendo hechos...');
            const hechosRes = await callApi(apiEndpoint, 'extract_facts', relato);
            if (hechosRes.error) {
                throw new Error("Paso 1 falló: " + (hechosRes.details || hechosRes.error) + (hechosRes.raw ? "\n\nRespuesta: " + hechosRes.raw : ""));
            }
            if (!Array.isArray(hechosRes)) {
                throw new Error("El modelo no devolvió un array válido en el Paso 1.");
            }
            nodesData = hechosRes;
            resultadosGlobales.hechos = nodesData;
            renderFacts(nodesData);
            renderNodes(nodesData);

            // Paso 2: Generar Diagrama
            loading.innerText = "Procesando 2/3: Diagramando Árbol de Causas...";
            updateLoadingProgress(66, 'Paso 2 de 3: construyendo el árbol de causas...');
            const diagramaRes = await callApi(apiEndpoint, 'generate_diagram', relato, JSON.stringify(nodesData));
            
            if (diagramaRes.error) {
                console.error("Error en Enlaces:", diagramaRes);
                throw new Error("Paso 2 falló: " + (diagramaRes.details || diagramaRes.error) + (diagramaRes.raw ? "\n\nRespuesta: " + diagramaRes.raw : ""));
            }
            
            // Forzar mostrar lo que respondió la IA en la consola (como un elemento oculto)
            const previousDebug = document.getElementById('debug-enlaces');
            if (previousDebug) previousDebug.remove();
            const debugDiv = document.createElement('div');
            debugDiv.style.display = 'none';
            debugDiv.id = 'debug-enlaces';
            debugDiv.innerText = JSON.stringify(diagramaRes);
            document.body.appendChild(debugDiv);

            linksData = Array.isArray(diagramaRes) ? diagramaRes : (diagramaRes.enlaces || []);
            resultadosGlobales.enlaces = linksData;
            
            // Recalcular posiciones usando el algoritmo DAG ahora que tenemos los enlaces
            recalculateLayout();
            
            drawLines();

            // Paso 3: Generar Medidas
            loading.innerText = "Procesando 3/3: Generando Medidas Preventivas...";
            updateLoadingProgress(85, 'Paso 3 de 3: generando medidas preventivas...');
            
            // ¡CRÍTICO! Debemos enviarle a la IA tanto los enlaces como los TEXTOS de los nodos.
            // Si solo le enviamos diagramaRes (que solo tiene IDs), la IA alucinará medidas genéricas.
            const contextoMedidas = JSON.stringify({
                nodos: resultadosGlobales.hechos,
                enlaces: diagramaRes
            });
            
            const medidasRes = await callApi(apiEndpoint, 'generate_measures', relato, contextoMedidas);
            resultadosGlobales.medidas = medidasRes;
            renderMedidas(medidasRes);

            btnGuardar.classList.remove('hidden');
            finishDiagramLoading();
        } catch (error) {
            console.error(error);
            updateLoadingProgress(100, 'El análisis terminó con un error.');
            diagramLoadingTime.innerText = formatElapsedTime(Date.now() - loadingStartedAt);
            diagramLoading.classList.add('hidden');
            if (loadingHideTimer !== null) {
                window.clearTimeout(loadingHideTimer);
                loadingHideTimer = null;
            }
            alert("Error en el análisis IA:\n\n" + error.message);
        } finally {
            if (loadingTimer !== null) {
                window.clearInterval(loadingTimer);
                loadingTimer = null;
            }
            loading.classList.add('hidden');
            btnAnalizar.disabled = false;
            if (btnAnalizarOpenAI) btnAnalizarOpenAI.disabled = false;
            if (btnAnalizarNvidiaCloud) btnAnalizarNvidiaCloud.disabled = false;
        }
    }

    btnAnalizar.addEventListener('click', () => procesarCaso('/api/modelo-local', btnAnalizar));
    if (btnAnalizarOpenAI) {
        btnAnalizarOpenAI.addEventListener('click', () => procesarCaso('/api/openai', btnAnalizarOpenAI));
    }
    if (btnAnalizarNvidiaCloud) {
        btnAnalizarNvidiaCloud.addEventListener('click', () => procesarCaso('/api/nvidiacloud', btnAnalizarNvidiaCloud));
    }

    btnGuardar.addEventListener('click', async () => {
        const payload = {
            nombre_trabajador: document.getElementById('nombre_trabajador').value,
            titulo_caso: document.getElementById('titulo_caso').value,
            rut: document.getElementById('rut').value || null,
            edad: document.getElementById('edad').value || null,
            sexo: document.getElementById('sexo').value || null,
            profesion: document.getElementById('profesion').value || null,
            antiguedad: document.getElementById('antiguedad').value || null,
            establecimiento: document.getElementById('establecimiento').value || null,
            area: document.getElementById('area').value || null,
            jefatura: document.getElementById('jefatura').value || null,
            fecha_accidente: document.getElementById('fecha_accidente').value || null,
            hora_accidente: document.getElementById('hora_accidente').value || null,
            lugar_especifico: document.getElementById('lugar_especifico').value || null,
            actividad_realizada: document.getElementById('actividad_realizada').value || null,
            relato: document.getElementById('relato').value,
            resultados_ia: resultadosGlobales
        };

        try {
            const response = await fetch('/registro-caso', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            alert(data.message);
        } catch (error) {
            console.error(error);
            alert("Error al guardar el caso.");
        }
    });

    async function callApi(apiEndpoint, step, relato, context = "") {
        const selectedModel = document.getElementById('ia_model').value;
        const res = await fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ step, relato, context, model: selectedModel })
        });
        
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Error parseando JSON. Raw response:", text);
            throw new Error("El servidor devolvió texto no válido (posible error PHP):\n\n" + text.substring(0, 800));
        }
    }

    function renderNodes(nodes) {
        let startX = 50;
        let startY = 50;
        nodes.forEach((node, i) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'nodo-wrapper';
            wrapper.id = 'node-' + node.id;
            
            const forma = document.createElement('div');
            forma.className = 'forma ' + (node.tipo_nodo || '');
            forma.innerText = node.nro_secuencia || node.id;
            wrapper.appendChild(forma);
            
            const desc = document.createElement('div');
            desc.className = 'descripcion';
            desc.innerText = node.descripcion;
            wrapper.appendChild(desc);

            wrapper.style.left = startX + 'px';
            wrapper.style.top = startY + 'px';
            startY += 100;
            if (startY > 500) {
                startY = 50;
                startX += 200;
            }

            wrapper.addEventListener('mousedown', startDrag);
            document.getElementById('canvasContent').appendChild(wrapper);
        });
    }

    function recalculateLayout() {
        const depths = {};
        nodesData.forEach(h => depths[h.id] = 0);

        for (let i = 0; i < nodesData.length; i++) {
            linksData.forEach(link => {
                const oId = link.origen_id || link.origen;
                const dId = link.destino_id || link.destino;
                if (depths[oId] !== undefined && depths[dId] !== undefined) {
                    if (depths[oId] + 1 > depths[dId]) {
                        depths[dId] = depths[oId] + 1;
                    }
                }
            });
        }

        const nodesByDepth = {};
        nodesData.forEach(h => {
            const d = depths[h.id];
            if (!nodesByDepth[d]) nodesByDepth[d] = [];
            nodesByDepth[d].push(h);
        });

        const width = 2600;
        let maxDepth = 0;
        Object.values(depths).forEach(d => { if (d > maxDepth) maxDepth = d; });
        const height = Math.max(2000, (maxDepth + 1) * 280 + 180);
        const canvasContent = document.getElementById('canvasContent');
        canvasContent.style.width = width + 'px';
        canvasContent.style.height = height + 'px';

        const nodePositions = {};
        const spacing = 250;
        const verticalSpacing = 280;

        const levels = Object.keys(nodesByDepth).map(Number).sort((a, b) => a - b);
        levels.forEach(d => nodesByDepth[d].sort((a, b) => a.id - b.id));

        // Ordenar cada nivel por el centro de sus padres y luego por el de sus hijos.
        // Esto reduce los cruces cuando el árbol tiene convergencias y bifurcaciones.
        for (let pass = 0; pass < 4; pass++) {
            levels.forEach(d => {
                if (d === 0) return;
                nodesByDepth[d].sort((a, b) => {
                    const score = node => {
                        const parents = linksData
                            .filter(link => (link.destino_id || link.destino) == node.id)
                            .map(link => nodePositions[link.origen_id || link.origen]?.x)
                            .filter(x => x !== undefined);
                        return parents.length ? parents.reduce((sum, x) => sum + x, 0) / parents.length : node.id;
                    };
                    return score(a) - score(b);
                });
            });

            for (let index = levels.length - 2; index >= 0; index--) {
                const d = levels[index];
                nodesByDepth[d].sort((a, b) => {
                    const score = node => {
                        const children = linksData
                            .filter(link => (link.origen_id || link.origen) == node.id)
                            .map(link => nodePositions[link.destino_id || link.destino]?.x)
                            .filter(x => x !== undefined);
                        return children.length ? children.reduce((sum, x) => sum + x, 0) / children.length : node.id;
                    };
                    return score(a) - score(b);
                });
            }

            levels.forEach(d => {
                const levelNodes = nodesByDepth[d];
                const startX = (width / 2) - ((levelNodes.length - 1) * spacing / 2) - 90;
                levelNodes.forEach((node, indexInLevel) => {
                    nodePositions[node.id] = {
                        x: startX + indexInLevel * spacing,
                        y: 50 + d * verticalSpacing
                    };
                });
            });
        }

        levels.forEach(d => {
            nodesByDepth[d].forEach(node => {
                const position = nodePositions[node.id];
                const div = document.getElementById('node-' + node.id);
                if (div && position) {
                    div.style.left = position.x + 'px';
                    div.style.top = position.y + 'px';
                }
            });
        });
        
        // Centrar el scroll automáticamente
        setTimeout(() => {
            const canvasContainer = document.getElementById('canvas');
            canvasContainer.scrollLeft = (width / 2) - (canvasContainer.offsetWidth / 2);
        }, 100);
    }

    function renderMedidas(medidas) {
        document.getElementById('medidasContainer').classList.remove('hidden');
        const lista = document.getElementById('listaMedidas');
        lista.innerHTML = ''; // Limpiar medidas anteriores
        
        let arr = Array.isArray(medidas) ? medidas : (medidas.medidas || Object.values(medidas));
        if (!Array.isArray(arr)) arr = [JSON.stringify(medidas)];

        arr.forEach(m => {
            const li = document.createElement('li');
            li.className = "mb-2 text-sm text-gray-700";
            
            let texto = "";
            if (typeof m === 'string') {
                texto = m;
            } else {
                texto = m.medida || m.descripcion || m.medida_preventiva || JSON.stringify(m);
            }
            
            li.innerText = texto;
            lista.appendChild(li);
        });
    }

    // --- DRAG AND DROP ---
    function startDrag(e) {
        draggedNode = e.target.closest('.nodo-wrapper');
        if (!draggedNode) return;
        
        const rect = draggedNode.getBoundingClientRect();
        offsetX = (e.clientX - rect.left) / zoomLevel;
        offsetY = (e.clientY - rect.top) / zoomLevel;
        
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    }

    function onDrag(e) {
        if (!draggedNode) return;
        
        const contentRect = canvasContent.getBoundingClientRect();
        
        const x = (e.clientX - contentRect.left) / zoomLevel - offsetX;
        const y = (e.clientY - contentRect.top) / zoomLevel - offsetY;
        
        draggedNode.style.left = x + 'px';
        draggedNode.style.top = y + 'px';
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
        svg.querySelectorAll('line, path.causal-link').forEach(element => element.remove());

        linksData.forEach(link => {
            const oId = link.origen_id || link.origen;
            const dId = link.destino_id || link.destino;
            
            const originNode = document.getElementById('node-' + oId);
            const targetNode = document.getElementById('node-' + dId);

            if (originNode && targetNode) {
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                
                // .forma tiene width 56px y height 56px y está centrada en un wrapper de 180px (mitad es 90)
                // Origen: sale de la parte inferior de la .forma
                const x1 = originNode.offsetLeft + 90;
                const y1 = originNode.offsetTop + 56; 
                
                // Destino: entra por la parte superior de la .forma
                const x2 = targetNode.offsetLeft + 90;
                const y2 = targetNode.offsetTop - 8; // offset por la cabeza de la flecha

                const color = link.tipo_relacion === 'conjuncion' ? '#d97706' : '#475569';
                const midY = y1 + ((y2 - y1) * 0.5);
                path.setAttribute('class', 'causal-link');
                path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', color);
                path.setAttribute('stroke-width', link.tipo_relacion === 'conjuncion' ? '3.5' : '3');
                path.setAttribute('marker-end', 'url(#arrow)');
                svg.appendChild(path);
            }
        });
    }
});
