<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\Ujian::with('soal.opsi')->first();
if ($u) {
    echo "Length: " . strlen(view('ujian.edit', ['ujian' => $u])->withErrors(new \Illuminate\Support\MessageBag())->render());
} else {
    echo "No ujian";
}
