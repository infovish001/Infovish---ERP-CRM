<?php

namespace App\Platform\ModuleRegistry;

use InvalidArgumentException;

final class ModuleRegistry
{
    /** @param list<string>|null $bundled */
    public function __construct(private ?string $manifestRoot = null, private ?array $bundled = null) {}

    /**
     * @param  list<string>|null  $allowlist
     * @return array<string, array<string, mixed>>
     */
    public function manifests(?string $root = null, ?array $allowlist = null): array
    {
        $root ??= $this->manifestRoot ?? app_path('Modules');
        $allowlist ??= $this->bundled ?? config('modules.bundled');
        $manifests = [];
        foreach ($allowlist as $name) {
            if (! is_string($name) || ! preg_match('/\A[A-Z][A-Za-z0-9]+\z/', $name)) {
                throw new InvalidArgumentException('Invalid module name.');
            }
            $data = json_decode(file_get_contents($root.'/'.$name.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
            if (($data['name'] ?? null) !== $name || ! is_string($data['version'] ?? null) || ! preg_match('/\A1\.\d+\.\d+\z/', $data['version']) || ($data['framework_major'] ?? null) !== config('modules.framework_major') || ! is_array($data['dependencies'] ?? null) || ! is_array($data['contracts'] ?? null) || ($data['migrations'] ?? null) !== 'Infrastructure/Migrations') {
                throw new InvalidArgumentException('Incompatible module manifest: '.$name);
            }
            foreach ($data['contracts'] as $contract) {
                if (! is_string($contract) || ! preg_match('/\A[A-Z][A-Za-z0-9]+\z/', $contract) || ! is_file($root.'/'.$name.'/Domain/Contracts/'.$contract.'.php')) {
                    throw new InvalidArgumentException('Invalid public contract declaration.');
                }
            }
            $manifests[$name] = $data;
        }
        $visiting = $visited = [];
        $visit = function (string $name) use (&$visit, &$visiting, &$visited, $manifests): void {
            if (isset($visiting[$name]) || ! isset($manifests[$name])) {
                throw new InvalidArgumentException('Invalid or cyclic module dependency.');
            }
            if (isset($visited[$name])) {
                return;
            }
            $visiting[$name] = true;
            foreach ($manifests[$name]['dependencies'] as $dependency => $version) {
                if ($version !== '^1.0' || ! isset($manifests[$dependency])) {
                    throw new InvalidArgumentException('Unsupported module dependency.');
                }
                $visit($dependency);
            }
            unset($visiting[$name]);
            $visited[$name] = true;
        };
        foreach (array_keys($manifests) as $name) {
            $visit($name);
        }

        return $manifests;
    }
}
