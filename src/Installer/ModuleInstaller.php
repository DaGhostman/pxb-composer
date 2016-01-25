<?php
namespace PXB\Installer;


use Composer\Composer;
use Composer\Factory;
use Composer\Installer\InstallerInterface;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use InvalidArgumentException;

class ModuleInstaller extends LibraryInstaller implements InstallerInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var string
     */
    protected $root;

    /**
     * @var \Composer\Config
     */
    protected $config;

    public function __construct(Composer $composer, IOInterface $io, $type = 'library', Filesystem $filesystem = null)
    {
        $this->composer = $composer;
        $this->config = $composer->getConfig();
        $this->io = $io;
        $this->root = realpath(dirname(Factory::getComposerFile()));

        if (!file_exists($this->root . '/config/modules.php')) {
            touch($this->root . '/config/modules.php');
            chmod($this->root . '/config/modules.php', 0775);
        }

        parent::__construct($io, $composer, $type, $filesystem);
    }

    /**
     * Installs specific package.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $extra = $package->getExtra();

        if (array_key_exists('pxb-config', $extra)) {
            $this->loadModule($package, $extra['pxb-config']);
        }

        parent::install($repo, $package);
    }

    /**
     * Updates specific package.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $initial already installed package version
     * @param PackageInterface $target updated version
     *
     * @throws InvalidArgumentException if $initial package is not installed
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $this->io->write('<info> ** Checking for changes in configuration file entry</info>');
        $oldExtra = $initial->getExtra();
        $targetExtra = $target->getExtra();

        parent::update($repo, $initial, $target);

        if (array_key_exists('pxb-config', $targetExtra) && array_key_exists('pxb-config', $oldExtra)) {
            if ($oldExtra['pxb-config'] === $targetExtra['pxb-config']) {
                $this->io->write('<info> ** No changes</info>');
            } else {
                $this->io->write('<info> Attempting to upgrade...</info>');
                $this->updateModule($target, $oldExtra['pxb-config'], $targetExtra['pxb-config']);
            }
        }
    }

    /**
     * Uninstalls specific package.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (array_key_exists('pxb-config', $extra)) {
            $this->unloadModule($package, $extra['pxb-config']);
        }
        parent::uninstall($repo, $package);
    }

    private function loadModule(PackageInterface $package, $moduleClass)
    {
        $modulesList = include $this->root . '/config/modules.php';
        if (!is_array($modulesList)) {
            $this->io->writeError(
                '<warning> ** Bad config. "' . $this->root . '/config/modules.php" does not return an array.</warning>'
            );
        }

        if (in_array($moduleClass, $modulesList)) {
            $this->io->write(
                '<info> ** Module ' . $package->getPrettyName() . ' is already loaded - Nothing to do.</info>'
            );
        } else {
            $modulesList[] = $moduleClass;
            file_put_contents(
                $this->root . '/config/modules.php',
                '<?php return ' . var_export($modulesList, true) . ';'
            );
        }
    }

    private function unloadModule(PackageInterface $package, $moduleClass)
    {
        $modulesList = include $this->root . '/config/modules.php';
        if (!is_array($modulesList)) {
            $this->io->writeError(
                '<warning> ** Bad config. "' . $this->root . '/config/modules.php" does not return an array.</warning>'
            );
        }

        if (!in_array($moduleClass, $modulesList)) {
            $this->io->write(
                '<info> ** Module ' . $package->getPrettyName() . ' is already unloaded - Nothing to do.</info>'
            );
        } else {
            $index = array_search($moduleClass, $modulesList);
            unset($modulesList[$index]);
            file_put_contents(
                $this->root . '/config/modules.php',
                '<?php return ' . var_export($modulesList, true) . ';'
            );
        }
    }

    private function updateModule(PackageInterface $package, $old, $new)
    {
        $this->unloadModule($package, $old);
        $this->loadModule($package, $new);
    }
}
