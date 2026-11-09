<?php

namespace App\Domain\Identidade\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Ator extends Authenticatable
{
    protected $table = 'atores';

    protected $fillable = ['nome', 'email', 'papel', 'senha'];

    protected $hidden = ['senha'];

    public function getAuthPassword(): string
    {
        return $this->senha;
    }
}
