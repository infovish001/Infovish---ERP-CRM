<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ModuleBoundaryTest extends TestCase
{
    public function test_declared_public_contract_is_allowed_and_private_import_is_rejected(): void
    {
        $root = sys_get_temp_dir().'/infovish-boundary-'.bin2hex(random_bytes(8));
        mkdir($root.'/Sales', 0777, true);
        mkdir($root.'/Catalog', 0777, true);
        file_put_contents($root.'/Sales/module.json', json_encode(['name' => 'Sales', 'dependencies' => ['Catalog']]));
        file_put_contents($root.'/Catalog/module.json', json_encode(['name' => 'Catalog', 'dependencies' => []]));
        try {
            file_put_contents($root.'/Sales/Consumer.php', '<?php use App\\Modules\\Catalog\\Domain\\Contracts\\Products;');
            $check = new Process([PHP_BINARY, dirname(__DIR__, 2).'/tools/check-boundaries.php', $root]);
            $check->run();
            $this->assertTrue($check->isSuccessful(), $check->getErrorOutput());
            file_put_contents($root.'/Sales/Consumer.php', '<?php use App\\Modules\\Catalog\\Infrastructure\\ProductModel;');
            $check->run();
            $this->assertSame(1, $check->getExitCode());
            $this->assertStringContainsString('forbidden import', $check->getErrorOutput());
            file_put_contents($root.'/Sales/Consumer.php', '<?php use App\\Modules\\Catalog\\Domain\\Contracts\\Products;');
            file_put_contents($root.'/Sales/module.json', json_encode(['name' => 'Sales', 'dependencies' => []]));
            $check->run();
            $this->assertSame(1, $check->getExitCode());
        } finally {
            unlink($root.'/Sales/Consumer.php');
            unlink($root.'/Sales/module.json');
            unlink($root.'/Catalog/module.json');
            rmdir($root.'/Sales');
            rmdir($root.'/Catalog');
            rmdir($root);
        }
    }
}
