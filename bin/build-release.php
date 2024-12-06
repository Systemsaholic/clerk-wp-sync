#!/usr/bin/env php
<?php
// Set working directory to plugin root
chdir(dirname(__DIR__));

// Create release directory
$release_dir = 'release';
if (!file_exists($release_dir)) {
    mkdir($release_dir);
}

// Clean up old release if it exists
$release_zip = $release_dir . '/clerk-wp-sync.zip';
if (file_exists($release_zip)) {
    unlink($release_zip);
}

// Run composer install
exec('composer install --no-dev --optimize-autoloader');

// Create zip file
$zip = new ZipArchive();
$zip->open($release_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Add all files except those we want to exclude
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('.'),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$exclude = [
    '.git',
    '.gitignore',
    'release',
    'bin',
    '.DS_Store',
    'composer.json',
    'composer.lock',
    'README.md'
];

foreach ($files as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen(getcwd()) + 1);

    // Check if file should be excluded
    $skip = false;
    foreach ($exclude as $excludePath) {
        if (strpos($relativePath, $excludePath) === 0) {
            $skip = true;
            break;
        }
    }

    if (!$skip) {
        $zip->addFile($filePath, 'clerk-wp-sync/' . $relativePath);
    }
}

$zip->close();

echo "Release created at: $release_zip\n"; 