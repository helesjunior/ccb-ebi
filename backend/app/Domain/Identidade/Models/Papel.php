<?php

namespace App\Domain\Identidade\Models;

use App\Domain\Identidade\Database\Factories\PapelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Papel extends Model
{
    /** @use HasFactory<PapelFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'papeis';

    protected $fillable = ['codigo', 'descricao'];

    protected static function newFactory(): PapelFactory
    {
        return PapelFactory::new();
    }
}
