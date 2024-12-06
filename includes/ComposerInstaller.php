<?php
/**
 * Composer Installer Class
 *
 * @package ClerkWPSync
 * @author Al Guertin
 * @copyright 2024 Systemsaholic
 */

namespace ClerkWPSync;

class ComposerInstaller {
    /**
     * Install Composer dependencies
     *
     * @return bool|string True on success, error message on failure
     */
    public static function install() {
        // Check if Composer is installed
        $composer_path = self::find_composer();
        if ( ! $composer_path ) {
            return 'Composer not found. Please install Composer first.';
        }

        // Get plugin directory
        $plugin_dir = dirname( dirname( __FILE__ ) );

        // Run composer install
        $command = sprintf(
            'cd %s && %s install --no-dev --no-interaction 2>&1',
            escapeshellarg( $plugin_dir ),
            escapeshellarg( $composer_path )
        );

        $output = array();
        $return_var = 0;
        exec( $command, $output, $return_var );

        if ( $return_var !== 0 ) {
            return 'Failed to install dependencies: ' . implode( "\n", $output );
        }

        return true;
    }

    /**
     * Find the Composer executable
     *
     * @return string|bool Path to composer or false if not found
     */
    private static function find_composer() {
        // Check if composer is in PATH
        $output = array();
        $return_var = 0;
        exec( 'which composer 2>&1', $output, $return_var );

        if ( $return_var === 0 && ! empty( $output[0] ) ) {
            return $output[0];
        }

        // Check common locations
        $locations = array(
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            getenv( 'HOME' ) . '/composer.phar',
        );

        foreach ( $locations as $location ) {
            if ( file_exists( $location ) ) {
                return $location;
            }
        }

        return false;
    }
} 