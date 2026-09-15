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
        'relato',
        'resultados_ia',
    ];

    protected $casts = [
        'resultados_ia' => 'array',
    ];
}
