<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Caso;

class CasoController extends Controller
{
    public function create()
    {
        return view('registro-caso');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_trabajador' => 'required|string|max:255',
            'titulo_caso' => 'required|string|max:255',
            'relato' => 'required|string',
            'resultados_ia' => 'nullable|array',
        ]);

        $caso = Caso::create($validated);

        return response()->json([
            'message' => 'Caso registrado exitosamente',
            'caso' => $caso
        ]);
    }
}
