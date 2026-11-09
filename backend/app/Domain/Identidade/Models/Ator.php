<?php

namespace App\Domain\Identidade\Models;

use Database\Factories\AtorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Ator extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<AtorFactory> */
    use HasFactory;

    protected $table = 'atores';

    protected $fillable = ['nome', 'email', 'papel', 'senha'];

    protected $hidden = ['senha'];

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    protected static function newFactory(): AtorFactory
    {
        return AtorFactory::new();
    }
}
