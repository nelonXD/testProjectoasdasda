<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caso extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_trabajador',
        'titulo_caso',
        'rut',
        'edad',
        'sexo',
        'profesion',
        'antiguedad',
        'establecimiento',
        'area',
        'jefatura',
        'fecha_accidente',
        'hora_accidente',
        'lugar_especifico',
        'actividad_realizada',
        'relato',
        'resultados_ia'
    ];

    protected $casts = [
        'resultados_ia' => 'array',
    ];
}
