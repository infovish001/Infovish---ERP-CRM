<?php

// This is a static import/manifest check, not proof of runtime data isolation.
$root = $argv[1] ?? dirname(__DIR__).'/app/Modules';
$errors = [];
$modules = array_filter(glob($root.'/*'), 'is_dir');
$names = array_map('basename', $modules);
foreach ($modules as $module) {
    $owner = basename($module);
    $manifest = json_decode(@file_get_contents($module.'/module.json') ?: '{}', true);
    if (($manifest['name'] ?? null) !== $owner || ! is_array($manifest['dependencies'] ?? null)) {
        $errors[] = "$owner: missing or invalid module.json";

        continue;
    }
    $dependencies = array_is_list($manifest['dependencies']) ? $manifest['dependencies'] : array_keys($manifest['dependencies']);
    foreach ($dependencies as $dependency) {
        if ($dependency === $owner || ! in_array($dependency, $names, true)) {
            $errors[] = "$owner: invalid dependency $dependency";
        }
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        preg_match_all('/App\\\\Modules\\\\(\w+)\\\\([A-Za-z0-9_\\\\]+)/', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] === $owner) {
                continue;
            }
            $target = json_decode(file_get_contents($root.'/'.$match[1].'/module.json') ?: '{}', true);
            $publicName = substr($match[2], strlen('Domain\\Contracts\\'));
            if (! in_array($match[1], $dependencies, true) || ! str_starts_with($match[2], 'Domain\\Contracts\\') || (isset($target['contracts']) && ! in_array($publicName, $target['contracts'], true))) {
                $errors[] = "$owner: forbidden import {$match[0]}";
            }
        }
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo 'PASS: module manifests and static imports ('.count($modules).' business modules).'.PHP_EOL;
