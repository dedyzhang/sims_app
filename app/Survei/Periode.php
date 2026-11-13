<?php
namespace App\Survei;
class Periode extends ModelSurvei {
    protected $table = 'survei_periodes';
    protected function casts(): array { return ['mulai'=>'datetime','selesai'=>'datetime','ditutup'=>'boolean','pertanyaan'=>'array']; }
    public function targets() { return $this->hasMany(Target::class, 'periode_id', 'uuid'); }
    public function terbuka(): bool { return ! $this->ditutup && now()->betweenIncluded($this->mulai, $this->selesai); }
}
