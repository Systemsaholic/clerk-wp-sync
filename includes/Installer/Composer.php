<?php
namespace ClerkWPSync\Installer;

class Composer {
    public static function install(): bool|string {
        $composer_path = self::find_composer();
        if (!$composer_path) {
            return 'Composer not found. Please install Composer first.';
        }

        $plugin_dir = dirname(dirname(dirname(__FILE__)));
        $command = sprintf(
            'cd %s && %s install --no-dev --no-interaction 2>&1',
            escapeshellarg($plugin_dir),
            escapeshellarg($composer_path)
        );

        $output = array();
        $return_var = 0;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return 'Failed to install dependencies: ' . implode("\n", $output);
        }

        return true;
    }

    private static function find_composer(): string|false {
        $output = array();
        $return_var = 0;
        exec('which composer 2>&1', $output, $return_var);

        if ($return_var === 0 && !empty($output[0])) {
            return $output[0];
        }

        return false;
    }
} 