<?php

namespace Tests\Feature;

use App\Support\TickerStats;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TickerStatsMemoTest extends TestCase
{
    public function test_repeated_reads_use_one_cache_lookup_per_request(): void
    {
        Cache::shouldReceive('remember')->once()->with(TickerStats::CACHE_KEY, TickerStats::TTL, \Mockery::type('Closure'))->andReturn(['siswa' => 7]);
        $this->assertSame(['siswa' => 7], TickerStats::raw());
        $this->assertSame(['siswa' => 7], TickerStats::raw());
    }

    public function test_clear_invalidates_both_shared_cache_and_request_memo(): void
    {
        Cache::shouldReceive('remember')->twice()->andReturn(['siswa' => 7], ['siswa' => 8]);
        Cache::shouldReceive('forget')->once()->with(TickerStats::CACHE_KEY);
        $this->assertSame(7, TickerStats::raw()['siswa']);
        TickerStats::clear();
        $this->assertSame(8, TickerStats::raw()['siswa']);
    }
}
