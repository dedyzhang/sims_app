<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nis extends Model
{
    use HasFactory;

    protected $table = 'nis';
    protected $fillable = ['kode'];
}
