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
            'rut' => 'nullable|string|max:50',
            'edad' => 'nullable|integer',
            'sexo' => 'nullable|string|max:20',
            'profesion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|string|max:255',
            'establecimiento' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'jefatura' => 'nullable|string|max:255',
            'fecha_accidente' => 'nullable|date',
            'hora_accidente' => 'nullable|date_format:H:i',
            'lugar_especifico' => 'nullable|string|max:255',
            'actividad_realizada' => 'nullable|string|max:255',
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
