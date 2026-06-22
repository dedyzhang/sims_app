<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\ClassroomAssignment;
use App\Models\ClassroomMaterial;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Menerbitkan Ruang Kelas / materi / tugas yang waktu jadwalnya sudah lewat.
 * Dijadwalkan tiap menit di routes/console.php.
 */
class ClassroomPublishScheduled extends Command
{
    protected $signature = 'classroom:publish-scheduled';
    protected $description = 'Terbitkan ruang kelas, materi, dan tugas terjadwal yang waktunya telah tiba';

    public function handle(): int
    {
        $now = now();

        $rooms = Classroom::where('status', 'scheduled')
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', $now)->get();
        foreach ($rooms as $room) {
            $room->update(['status' => 'published', 'published_at' => $now, 'scheduled_publish_at' => null]);
            Audit::log('classroom_publish_auto', $room);
        }

        $materials = ClassroomMaterial::where('is_published', false)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', $now)->get();
        foreach ($materials as $m) {
            $m->update(['is_published' => true, 'published_at' => $now, 'scheduled_publish_at' => null]);
        }

        $assignments = ClassroomAssignment::where('status', 'draft')
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', $now)->get();
        foreach ($assignments as $a) {
            $a->update(['status' => 'published', 'scheduled_publish_at' => null]);
        }

        $this->info("Terbit: {$rooms->count()} ruang kelas, {$materials->count()} materi, {$assignments->count()} tugas.");
        return self::SUCCESS;
    }
}
