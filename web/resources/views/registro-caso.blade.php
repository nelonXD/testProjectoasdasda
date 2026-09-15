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
            width: 100%;
            height: 600px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            border-radius: 0.5rem;
        }
        .nodo {
            position: absolute;
            padding: 10px;
            background: white;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            cursor: grab;
            user-select: none;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            min-width: 150px;
            text-align: center;
            font-size: 0.875rem;
            z-index: 10;
        }
        .nodo:active {
            cursor: grabbing;
        }
        .nodo.lesion {
            border-color: #ef4444;
            background-color: #fee2e2;
        }
        svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 5;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="container mx-auto p-4 flex flex-col lg:flex-row gap-6">
    <!-- Formulario Izquierda -->
    <div class="w-full lg:w-1/3 bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Registrar Caso</h2>
        
        <form id="formCaso">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Nombre Trabajador</label>
                <input type="text" id="nombre_trabajador" class="w-full border rounded p-2" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Título del Caso</label>
                <input type="text" id="titulo_caso" class="w-full border rounded p-2" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Relato del Accidente</label>
                <textarea id="relato" class="w-full border rounded p-2 h-32" required></textarea>
            </div>

            <button type="button" id="btnAnalizar" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition">
                Analizar con IA (Nvidia)
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
        <h2 class="text-2xl font-bold mb-4">Árbol de Causas (IA)</h2>
        <div id="canvas" class="canvas-container">
            <svg id="svgLines"></svg>
            <!-- Nodos renderizados aquí por JS -->
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
