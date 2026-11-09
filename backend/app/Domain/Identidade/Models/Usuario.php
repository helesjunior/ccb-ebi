<?php

namespace App\Domain\Identidade\Models;

use App\Domain\Identidade\Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome',
        'email_normalizado',
        'email_verificado_em',
        'senha',
        'identidade_federada',
        'status',
    ];

    protected $hidden = ['senha'];

    protected $casts = [
        'email_verificado_em' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public static function emailNormalizado(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @return HasMany<VinculoAcesso, $this> */
    public function vinculosVigentes(): HasMany
    {
        return $this->hasMany(VinculoAcesso::class, 'usuario_id')->whereNull('fim');
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}
