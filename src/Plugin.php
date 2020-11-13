<?php
/**
 * @license MIT
 */

namespace Cetera\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Register the plugin installer
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
    
    public function deactivate(Composer $composer, IOInterface $io)
    {}
    
    public function uninstall(Composer $composer, IOInterface $io)
    {}
}
