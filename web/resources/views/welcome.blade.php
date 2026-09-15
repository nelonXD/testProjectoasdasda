<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Project - Pipeline Activo</title>
    <!-- Modern Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-1: #3b82f6;
            --accent-2: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated background blobs for dynamic feel */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
            z-index: -1;
        }

        .blob-1 {
            width: 40vw;
            height: 40vw;
            max-width: 500px;
            max-height: 500px;
            background: var(--accent-1);
            top: -10%;
            left: -10%;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 30vw;
            height: 30vw;
            max-width: 400px;
            max-height: 400px;
            background: var(--accent-2);
            bottom: -5%;
            right: -5%;
            animation-delay: -5s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        /* Glassmorphism Card styling */
        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            /* Initial state for intro animation */
            transform: translateY(30px);
            opacity: 0;
            animation: slideUp 0.8s ease-out forwards;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            /* Gradient Text */
            background: linear-gradient(135deg, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            letter-spacing: -0.05em;
        }

        p {
            font-size: 1.125rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
            font-weight: 300;
        }

        .features {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .feature-badge {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            padding: 0.5rem 1.2rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #e2e8f0;
            transition: all 0.3s ease;
            cursor: default;
        }

        /* Hover Micro-animations */
        .feature-badge:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            color: white;
            text-decoration: none;
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
        }
        
        .btn:active {
            transform: translateY(0) scale(0.98);
        }
    </style>
</head>
<body>
    <!-- Background dynamic colors -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    
    <!-- Main content container -->
    <div class="card">
        <h1>¡Entorno Preparado!</h1>
        <p>Tu proyecto Laravel está funcionando perfectamente de forma aislada en la carpeta <strong>web/</strong>. La infraestructura y pipelines están listos.</p>
        
        <div class="features">
            <span class="feature-badge">⚡ Laravel Moderno</span>
            <span class="feature-badge">🐳 Estructura Docker</span>
            <span class="feature-badge">🐙 CI/CD Actions</span>
        </div>
        
        <button class="btn" onclick="alert('¡Excelente! Las animaciones, el Glassmorphism y la arquitectura están listos. ¡Es hora de codear!')">
            Comenzar Desarrollo
        </button>
    </div>
</body>
</html>
