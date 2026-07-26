<?php

namespace Tests\Unit;

use App\Services\TeacherMaterialException;
use PHPUnit\Framework\TestCase;

class TeacherMaterialExceptionTest extends TestCase
{
    public function test_extract_failed_memuat_petunjuk_foto_buku(): void
    {
        $exception = TeacherMaterialException::extractFailed();
        $payload = $exception->toArray();

        $this->assertSame(TeacherMaterialException::CODE_EXTRACT_FAILED, $payload['error_code']);
        $this->assertTrue($payload['suggest_camera']);
        $this->assertNotEmpty($payload['hint']);
        $this->assertStringContainsString('Foto buku', $payload['hint']);
    }
}
