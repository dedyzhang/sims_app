<?php

namespace Tests\Unit;

use App\Models\Mission;
use App\Models\MissionStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionIsPlayableTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_playable_mengikuti_with_count(): void
    {
        $empty = Mission::factory()->recallQuiz()->create();
        $withStep = Mission::factory()->recallQuiz()->create([
            'slug' => 'misi-berlangkah-'.uniqid(),
        ]);
        MissionStep::factory()->narrative()->create(['mission_id' => $withStep->uuid]);

        $emptyCounted = Mission::query()->withCount('steps')->findOrFail($empty->uuid);
        $readyCounted = Mission::query()->withCount('steps')->findOrFail($withStep->uuid);

        $this->assertFalse($emptyCounted->isPlayable());
        $this->assertTrue($readyCounted->isPlayable());
    }

    public function test_is_playable_mengikuti_relasi_steps_yang_sudah_dimuat(): void
    {
        $mission = Mission::factory()->recallQuiz()->create();
        $mission->setRelation('steps', collect());
        $this->assertFalse($mission->isPlayable());

        MissionStep::factory()->narrative()->create(['mission_id' => $mission->uuid]);
        $mission->unsetRelation('steps');
        $mission->load('steps');
        $this->assertTrue($mission->isPlayable());
    }

    public function test_is_playable_fallback_exists_tanpa_count(): void
    {
        $mission = Mission::factory()->recallQuiz()->create();
        $this->assertFalse($mission->isPlayable());

        MissionStep::factory()->narrative()->create(['mission_id' => $mission->uuid]);
        $this->assertTrue($mission->fresh()->isPlayable());
    }
}
