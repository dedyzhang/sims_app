<?php 
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); 
$soals = App\Models\UjianSoal::latest()->take(15)->get();
foreach($soals as $s) { echo "=== ID: " . $s->id . " ===\n" . $s->teks_soal . "\n\n"; }
