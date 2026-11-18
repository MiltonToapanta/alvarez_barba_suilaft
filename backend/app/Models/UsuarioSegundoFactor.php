<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioSegundoFactor extends Model
{
    protected $table = 'usuario_segundo_factor';
    protected $primaryKey = 'usuario_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'secreto_cifrado', 'activo', 'ultimo_paso_usado', 'activado_en'];
    protected $hidden = ['secreto_cifrado'];
    protected $casts = ['activo' => 'boolean'];
}
