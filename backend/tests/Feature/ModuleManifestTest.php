<?php

namespace Tests\Feature;

use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Platform\ModuleRegistry\ModuleRegistry;
use Tests\TestCase;

class ModuleManifestTest extends TestCase
{
    public function test_bundled_manifests_and_contract_bindings_are_valid(): void
    {
        $this->assertSame(['Parties', 'Catalog', 'CRM', 'Sales'], array_keys(app(ModuleRegistry::class)->manifests()));
        $this->assertInstanceOf(Contacts::class, app(Contacts::class));
        $this->assertInstanceOf(Products::class, app(Products::class));
    }

    public function test_dependency_cycles_missing_versions_and_incompatible_framework_are_rejected(): void
    {
        $root = sys_get_temp_dir().'/infovish-manifests-'.bin2hex(random_bytes(8));
        mkdir($root.'/Alpha', 0777, true);
        mkdir($root.'/Bravo', 0777, true);
        $base = ['version' => '1.0.0', 'framework_major' => 1, 'dependencies' => [], 'contracts' => [], 'migrations' => 'Infrastructure/Migrations'];
        try {
            $cases = [
                [['Bravo' => '^1.0'], ['Alpha' => '^1.0'], 1],
                [['Missing' => '^1.0'], [], 1],
                [['Bravo' => '^9.0'], [], 1],
                [[], [], 99],
            ];
            foreach ($cases as [$alpha,$bravo,$framework]) {
                file_put_contents($root.'/Alpha/module.json', json_encode(['name' => 'Alpha', 'dependencies' => $alpha, 'framework_major' => $framework] + $base));
                file_put_contents($root.'/Bravo/module.json', json_encode(['name' => 'Bravo', 'dependencies' => $bravo] + $base));
                try {
                    app(ModuleRegistry::class)->manifests($root, ['Alpha', 'Bravo']);
                    $this->fail('Invalid graph accepted.');
                } catch (\InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        } finally {
            unlink($root.'/Alpha/module.json');
            unlink($root.'/Bravo/module.json');
            rmdir($root.'/Alpha');
            rmdir($root.'/Bravo');
            rmdir($root);
        }
    }
}
