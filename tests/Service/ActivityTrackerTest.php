<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ActivityTracker;
use PHPUnit\Framework\TestCase;

final class ActivityTrackerTest extends TestCase
{
    private string $filePath;
    private ActivityTracker $tracker;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/activity-tracker-test-' . uniqid() . '.txt';
        $this->tracker = new ActivityTracker($this->filePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);
        foreach (glob($this->filePath . '.tmp.*') ?: [] as $tmp) {
            @unlink($tmp);
        }
    }

    public function testGetLastActivityAtReturnsNullWhenFileAbsent(): void
    {
        self::assertNull($this->tracker->getLastActivityAt());
    }

    public function testRecordActivityCreatesFileWithCurrentTimestamp(): void
    {
        $before = time();
        $this->tracker->recordActivity();
        $after = time();

        self::assertFileExists($this->filePath);

        $recorded = $this->tracker->getLastActivityAt();
        self::assertNotNull($recorded);
        self::assertGreaterThanOrEqual($before, $recorded->getTimestamp());
        self::assertLessThanOrEqual($after, $recorded->getTimestamp());
    }

    public function testRecordActivityDoesNotRewriteWithinAmortizationWindow(): void
    {
        $earlierTimestamp = time() - 60; // 1 min avant, largement sous les 5 min d'amortissement
        file_put_contents($this->filePath, (string) $earlierTimestamp);

        $this->tracker->recordActivity();

        $recorded = $this->tracker->getLastActivityAt();
        self::assertSame($earlierTimestamp, $recorded->getTimestamp());
    }

    public function testRecordActivityRewritesAfterAmortizationWindow(): void
    {
        $oldTimestamp = time() - 400; // > 5 min, hors fenêtre d'amortissement
        file_put_contents($this->filePath, (string) $oldTimestamp);

        $before = time();
        $this->tracker->recordActivity();

        $recorded = $this->tracker->getLastActivityAt();
        self::assertGreaterThanOrEqual($before, $recorded->getTimestamp());
    }

    public function testGetLastActivityAtReturnsNullOnCorruptedContent(): void
    {
        file_put_contents($this->filePath, 'not-a-timestamp');

        self::assertNull($this->tracker->getLastActivityAt());
    }

    public function testGetLastActivityAtReturnsNullOnEmptyFile(): void
    {
        file_put_contents($this->filePath, '');

        self::assertNull($this->tracker->getLastActivityAt());
    }

    public function testRecordActivityLeavesNoResidualTmpFile(): void
    {
        $this->tracker->recordActivity();

        $residuals = glob($this->filePath . '.tmp.*') ?: [];
        self::assertCount(0, $residuals);
    }

    public function testRecordActivityDoesNotThrowWhenDirectoryIsUnwritable(): void
    {
        $unwritablePath = '/nonexistent-dir-' . uniqid() . '/activity.txt';
        $tracker = new ActivityTracker($unwritablePath);

        $tracker->recordActivity();

        self::assertNull($tracker->getLastActivityAt());
    }
}
