<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioPermiso extends Model
{
    protected $table = 'usuario_permisos';
    public $timestamps = false;
    protected $fillable = ['usuario_id', 'modulo', 'nivel'];
}
