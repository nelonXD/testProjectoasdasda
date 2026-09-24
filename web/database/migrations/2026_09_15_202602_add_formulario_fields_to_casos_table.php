<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->string('rut')->nullable()->after('titulo_caso');
            $table->integer('edad')->nullable()->after('rut');
            $table->string('sexo')->nullable()->after('edad');
            $table->string('profesion')->nullable()->after('sexo');
            $table->string('antiguedad')->nullable()->after('profesion');
            $table->string('establecimiento')->nullable()->after('antiguedad');
            $table->string('area')->nullable()->after('establecimiento');
            $table->string('jefatura')->nullable()->after('area');
            $table->date('fecha_accidente')->nullable()->after('jefatura');
            $table->time('hora_accidente')->nullable()->after('fecha_accidente');
            $table->string('lugar_especifico')->nullable()->after('hora_accidente');
            $table->string('actividad_realizada')->nullable()->after('lugar_especifico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn([
                'rut', 'edad', 'sexo', 'profesion', 'antiguedad', 
                'establecimiento', 'area', 'jefatura', 
                'fecha_accidente', 'hora_accidente', 'lugar_especifico', 'actividad_realizada'
            ]);
        });
    }
};
