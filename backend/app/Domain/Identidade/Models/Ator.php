<?php

namespace App\Domain\Identidade\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Ator extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'atores';

    protected $fillable = ['nome', 'email', 'papel', 'senha'];

    protected $hidden = ['senha'];

    public function getAuthPassword(): string
    {
        return $this->senha;
    }
}
