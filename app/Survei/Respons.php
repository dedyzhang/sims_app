<?php
namespace App\Survei;
class Respons extends ModelSurvei {
    protected $table = 'survei_respons';
    protected function casts(): array { return ['jawaban'=>'array','komentar'=>'array']; }
}
