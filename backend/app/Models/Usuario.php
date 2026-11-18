<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Tabla `usuarios` heredada de SUIAUTO (clave = hash bcrypt $2y$). */
class Usuario extends Authenticatable
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuarios';
    public $timestamps = false;

    protected $fillable = [
        'cedula', 'usuario', 'clave', 'estado', 'cambio_clave', 'nombres',
        'direccion', 'celular', 'telefono', 'email', 'rol', 'rol_id',
    ];

    protected $hidden = ['clave'];

    public function getAuthPasswordName(): string
    {
        return 'clave';
    }

    /** La tabla no tiene remember_token: "recordarme" queda deshabilitado. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function activo(): bool
    {
        return $this->estado === 'A';
    }

    public function rolRel(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function segundoFactor(): HasOne
    {
        return $this->hasOne(UsuarioSegundoFactor::class, 'usuario_id', 'id_usuarios');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(UsuarioPermiso::class, 'usuario_id', 'id_usuarios');
    }

    /**
     * Si el usuario tiene permisos propios, mandan sobre el rol; si no, hereda
     * los módulos del rol a nivel 'editar' (mismo criterio que SUIAUTO).
     *
     * @return array<string,string> modulo => nivel
     */
    public function modulosNivel(): array
    {
        $propios = $this->permisos()->pluck('nivel', 'modulo')->all();
        if ($propios) {
            return $propios;
        }
        $mods = array_filter(explode(',', (string) $this->rolRel?->modulos));

        return array_fill_keys($mods, 'editar');
    }

    public function resumen(): array
    {
        $niveles = $this->modulosNivel();

        return [
            'id' => $this->id_usuarios,
            'usuario' => $this->usuario,
            'nombres' => $this->nombres,
            'email' => $this->email,
            'rol' => $this->rolRel?->nombre ?? $this->rol,
            'es_admin' => (bool) $this->rolRel?->es_admin,
            'modulos' => array_keys($niveles),
            'modulos_nivel' => $niveles,
        ];
    }
}
