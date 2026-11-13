<?php
namespace App\Survei;
class Target extends ModelSurvei {
    protected $table = 'survei_targets';
    public function periode() { return $this->belongsTo(Periode::class, 'periode_id', 'uuid'); }
    public function respons() { return $this->hasMany(Respons::class, 'target_id', 'uuid'); }
}
