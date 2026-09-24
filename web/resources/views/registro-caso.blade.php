<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Registro de Caso - IA</title>
    <!-- Incluyendo Tailwind para estilos rápidos -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .canvas-container {
            position: relative;
            height: 700px;
            width: 100%;
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 20px 20px;
            border: 1px solid #e2e8f0;
            overflow: auto;
            border-radius: 0.75rem;
            box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
        }
        .canvas-content {
            position: relative;
            min-width: 100%;
            min-height: 100%;
            transform-origin: top left;
            transition: transform 0.15s ease-out;
        }
        .canvas-zoom-controls {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 30;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 10px rgb(15 23 42 / 0.12);
        }
        .canvas-zoom-controls button {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 6px;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
        }
        .canvas-zoom-controls button:hover {
            background: #dbeafe;
        }
        .canvas-zoom-level {
            min-width: 48px;
            text-align: center;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .diagram-loading {
            position: absolute;
            inset: 0;
            z-index: 25;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgb(248 250 252 / 0.88);
            backdrop-filter: blur(3px);
        }
        .diagram-loading.hidden {
            display: none;
        }
        .diagram-loading-panel {
            width: min(420px, 100%);
            padding: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 18px 40px rgb(15 23 42 / 0.16);
        }
        .diagram-loading-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .diagram-loading-title {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }
        .diagram-loading-time {
            color: #475569;
            font-variant-numeric: tabular-nums;
            font-size: 0.875rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .diagram-loading-track {
            height: 10px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }
        .diagram-loading-progress {
            width: 0%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2563eb, #06b6d4);
            transition: width 0.35s ease;
        }
        .diagram-loading-status {
            margin-top: 10px;
            color: #475569;
            font-size: 0.875rem;
        }
        .facts-panel {
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }
        .facts-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .facts-panel-title {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 700;
        }
        .facts-panel-count {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .hypotheses-panel {
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            background: #fffbeb;
        }
        .hypotheses-panel .facts-panel-header {
            border-bottom-color: #fde68a;
        }
        .hypotheses-panel .facts-list {
            background: #fffbeb;
        }
        .facts-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 8px;
            max-height: 260px;
            overflow-y: auto;
            padding: 12px 16px;
        }
        .fact-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            min-width: 0;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #ffffff;
            color: #334155;
            font-size: 0.82rem;
            line-height: 1.35;
        }
        .fact-item-number {
            flex: 0 0 auto;
            color: #64748b;
            font-weight: 800;
        }
        .fact-item-type {
            flex: 0 0 auto;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .fact-item-type.permanente { background: #d1fae5; color: #047857; }
        .fact-item-type.hecho { background: #dbeafe; color: #1d4ed8; }
        .fact-item-type.hipotesis { background: #fef3c7; color: #b45309; }
        .fact-item-type.lesion { background: #fee2e2; color: #b91c1c; }
        .nodo-wrapper {
            position: absolute;
            cursor: grab;
            user-select: none;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 180px;
            transition: transform 0.15s ease-out;
        }
        .nodo-wrapper:active {
            cursor: grabbing;
            transform: scale(1.05);
            z-index: 50;
        }
        .forma {
            width: 56px;
            height: 56px;
            background: #ffffff;
            border: 4px solid #3b82f6; 
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            color: #1e3a8a;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            transition: all 0.2s;
        }
        /* Formas premium según metodología INRS */
        .forma.hecho { 
            border-radius: 50%; /* Círculo */
            border-color: #3b82f6; 
            color: #1d4ed8; 
        }
        .forma.permanente { 
            border-radius: 4px; /* Cuadrado o Rectángulo */
            border-color: #10b981; 
            color: #047857; 
        }
        .forma.hipotesis {
            border-radius: 10px;
            border-style: dashed;
            border-color: #f59e0b;
            color: #b45309;
            background: #fffbeb;
        }
        .forma.lesion { 
            /* Triángulo mediante SVG como fondo */
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><polygon points="50,10 10,90 90,90" fill="%23fef2f2" stroke="%23ef4444" stroke-width="8" stroke-linejoin="round"/></svg>') no-repeat center center;
            background-size: 100% 100%;
            border: none;
            border-radius: 0;
            box-shadow: none;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
            color: #b91c1c; 
            width: 68px;
            height: 68px;
            padding-top: 14px; /* Empujar el texto hacia el centro de gravedad del triángulo */
        }
        .descripcion {
            margin-top: 12px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.4;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 12px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid #e2e8f0;
            backdrop-filter: blur(4px);
        }
        svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: visible; /* Asegura que no se recorten las flechas */
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="container mx-auto p-4 flex flex-col lg:flex-row gap-6">
    <!-- Formulario Izquierda (Scrollable) -->
    <div class="w-full lg:w-1/3 bg-white p-6 rounded-lg shadow-md max-h-[90vh] overflow-y-auto">
        <h2 class="text-2xl font-bold mb-4 text-gray-800 border-b pb-2">Registrar Caso</h2>
        
        <form id="formCaso">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Título del Caso</label>
                <input type="text" id="titulo_caso" class="w-full border rounded p-2" required placeholder="Ej: Caída en pasillo">
            </div>

            <!-- Sección 1: Antecedentes -->
            <div class="mb-6 bg-slate-50 p-4 rounded border">
                <h3 class="font-bold text-sm text-slate-700 mb-3 uppercase">1. Antecedentes del Funcionario</h3>
                
                <div class="mb-3">
                    <label class="block text-xs font-medium mb-1">Nombre Completo</label>
                    <input type="text" id="nombre_trabajador" class="w-full border rounded p-2 text-sm" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">RUT</label>
                        <input type="text" id="rut" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Edad</label>
                        <input type="number" id="edad" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Sexo</label>
                        <select id="sexo" class="w-full border rounded p-2 text-sm">
                            <option value="">Seleccionar...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="O">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Profesión/Oficio</label>
                        <input type="text" id="profesion" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Antigüedad (Años)</label>
                        <input type="text" id="antiguedad" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Establecimiento</label>
                        <input type="text" id="establecimiento" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Área</label>
                        <input type="text" id="area" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Jefatura Directa</label>
                        <input type="text" id="jefatura" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>
            </div>

            <!-- Sección 2: Datos del Accidente -->
            <div class="mb-6 bg-slate-50 p-4 rounded border">
                <h3 class="font-bold text-sm text-slate-700 mb-3 uppercase">2. Datos del Accidente</h3>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Fecha</label>
                        <input type="date" id="fecha_accidente" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Hora</label>
                        <input type="time" id="hora_accidente" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-medium mb-1">Lugar Específico</label>
                    <input type="text" id="lugar_especifico" class="w-full border rounded p-2 text-sm">
                </div>
                
                <div class="mb-3">
                    <label class="block text-xs font-medium mb-1">Actividad Realizada</label>
                    <input type="text" id="actividad_realizada" class="w-full border rounded p-2 text-sm">
                </div>
            </div>

            <!-- Sección 3: Relato -->
            <div class="mb-6">
                <label class="block text-sm font-bold mb-2 text-gray-800">Descripción del evento (Relato)</label>
                <p class="text-xs text-gray-500 mb-2">Explique de forma clara y objetiva qué ocurrió. Este texto será analizado por la IA.</p>
                <textarea id="relato" class="w-full border rounded p-2 h-32 text-sm" required></textarea>
            </div>

            <!-- Selección de Modelo IA -->
            <div class="mb-6 bg-blue-50 p-4 rounded border border-blue-200">
                <label class="block text-sm font-bold mb-2 text-blue-800">Modelo de Inteligencia Artificial</label>
                <select id="ia_model" class="w-full border rounded p-2 text-sm bg-white">
                    <option value="">Cargando modelos...</option>
                </select>
                <p class="text-xs text-blue-600 mt-2">Modelo recomendado para pruebas: <span class="font-semibold">qwen/qwen2.5-coder-7b-instruct</span></p>
            </div>

            <button type="button" id="btnAnalizar" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition">
                Analizar con ModeloLocal
            </button>
            <button type="button" id="btnAnalizarOpenAI" class="w-full bg-purple-600 text-white font-bold py-2 px-4 rounded hover:bg-purple-700 transition mt-2">
                Analizar con OpenAI (GPT-4o)
            </button>
            <button type="button" id="btnAnalizarNvidiaCloud" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition mt-2">
                Analizar con Nvidia Cloud (Nemotron 120B)
            </button>
            <button type="button" id="btnGuardar" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition mt-2 hidden">
                Guardar Caso en Base de Datos
            </button>
        </form>

        <div id="loading" class="hidden mt-4 text-center text-blue-600 font-bold">
            Procesando...
        </div>
    </div>

    <!-- Canvas Derecha -->
    <div class="w-full lg:w-2/3 bg-white p-6 rounded-lg shadow-md flex flex-col">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Árbol de Causas (IA)</h2>
        <section id="factsPanel" class="facts-panel hidden" aria-live="polite">
            <div class="facts-panel-header">
                <span class="facts-panel-title">Hechos identificados</span>
                <span id="factsPanelCount" class="facts-panel-count">0 hechos</span>
            </div>
            <div id="factsList" class="facts-list"></div>
        </section>
        <section id="hypothesesPanel" class="hypotheses-panel hidden" aria-live="polite">
            <div class="facts-panel-header">
                <span class="facts-panel-title">Hipótesis causales</span>
                <span id="hypothesesPanelCount" class="facts-panel-count">0 hipótesis</span>
            </div>
            <div id="hypothesesList" class="facts-list"></div>
        </section>
        <div id="canvas" class="canvas-container">
            <div id="diagramLoading" class="diagram-loading hidden" role="status" aria-live="polite">
                <div class="diagram-loading-panel">
                    <div class="diagram-loading-header">
                        <span class="diagram-loading-title">Analizando caso</span>
                        <span id="diagramLoadingTime" class="diagram-loading-time">00:00</span>
                    </div>
                    <div class="diagram-loading-track" aria-hidden="true">
                        <div id="diagramLoadingProgress" class="diagram-loading-progress"></div>
                    </div>
                    <div id="diagramLoadingPercent" class="mt-2 text-right text-sm font-bold text-blue-700">0%</div>
                    <div id="diagramLoadingStatus" class="diagram-loading-status">Preparando análisis...</div>
                </div>
            </div>
            <div class="canvas-zoom-controls" aria-label="Controles de zoom del diagrama">
                <button type="button" id="zoomOut" title="Alejar" aria-label="Alejar">−</button>
                <span id="zoomLevel" class="canvas-zoom-level">100%</span>
                <button type="button" id="zoomIn" title="Acercar" aria-label="Acercar">+</button>
                <button type="button" id="zoomReset" title="Restablecer zoom" aria-label="Restablecer zoom">⟳</button>
            </div>
            <div id="canvasContent" class="canvas-content">
                <svg id="svgLines">
                    <defs>
                        <marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" fill="#475569" />
                        </marker>
                    </defs>
                </svg>
                <!-- Nodos renderizados aquí por JS -->
            </div>
        </div>
        
        <!-- Medidas generadas -->
        <div id="medidasContainer" class="mt-4 hidden">
            <h3 class="text-xl font-bold text-green-700">Medidas Preventivas Sugeridas</h3>
            <ul id="listaMedidas" class="list-disc pl-5 mt-2 space-y-1"></ul>
        </div>
    </div>
</div>

<script src="{{ asset('js/canvas.js') }}"></script>
</body>
</html>
