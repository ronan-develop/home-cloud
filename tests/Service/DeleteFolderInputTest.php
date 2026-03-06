<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Dto\DeleteFolderInput;

final class DeleteFolderInputTest extends TestCase
{
    public function testDefaults(): void
    {
        $input = new DeleteFolderInput();
        $this->assertTrue($input->deleteContents);
        $this->assertSame('suffix', $input->conflictStrategy);
        $this->assertNull($input->targetFolder);
    }
}
