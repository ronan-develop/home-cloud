<?php
// debug-fixtures.php
require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\Common\DataFixtures\AbstractFixture;

$fixture = new class extends AbstractFixture {
    public function test()
    {
        var_dump(get_class_methods($this));
    }
};
$fixture->test();
