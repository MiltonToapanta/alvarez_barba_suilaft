<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'modulos', 'es_admin'];
    protected $casts = ['es_admin' => 'boolean'];
}
