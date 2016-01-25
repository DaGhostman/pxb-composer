<?php
namespace PXB\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()
            ->addInstaller(new ModuleInstaller($composer, $io));
    }
}
