<?php

namespace Tests\Unit;

use App\Support\ArenaSoloShuffle;
use PHPUnit\Framework\TestCase;

class ArenaSoloShuffleTest extends TestCase
{
    public function test_same_seed_is_stable(): void
    {
        $items = collect(['a', 'b', 'c', 'd', 'e', 'f']);

        $one = ArenaSoloShuffle::shuffle($items, 'attempt-aaa')->all();
        $two = ArenaSoloShuffle::shuffle($items, 'attempt-aaa')->all();

        $this->assertSame($one, $two);
    }

    public function test_different_seeds_usually_differ(): void
    {
        $items = collect(range(1, 12));

        $a = ArenaSoloShuffle::shuffle($items, 'siswa-A')->all();
        $b = ArenaSoloShuffle::shuffle($items, 'siswa-B')->all();

        $this->assertNotSame($a, $b);
        $this->assertEqualsCanonicalizing($items->all(), $a);
        $this->assertEqualsCanonicalizing($items->all(), $b);
    }
}
