<?php
/**
 * @license MIT
 */

namespace Cetera\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

class Installer extends LibraryInstaller
{
    // Constants
    // =========================================================================

    const PLUGINS_FILE = 'cetera-labs/cetera-cms-plugins.php';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return $packageType === 'cetera-cms-plugin';
    }

    /**
     * @inheritdoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Install the plugin in vendor/ like a normal Composer library
        parent::install($repo, $package);

        // Add the plugin info to plugins.php
        try {
            $this->addPlugin($package);
        } catch (InvalidPluginException $e) {
            // Rollback
            parent::uninstall($repo, $package);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        // Update the plugin in vendor/ like a normal Composer library
        parent::update($repo, $initial, $target);

        // Remove the old plugin info from plugins.php
        $initialPlugin = $this->removePlugin($initial);

        // Add the new plugin info to plugins.php
        try {
            $this->addPlugin($target);
        } catch (InvalidPluginException $e) {
            // Rollback
            parent::update($repo, $target, $initial);
            if ($initialPlugin !== null) {
                $this->registerPlugin($initial->getName(), $initialPlugin);
            }
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Uninstall the plugin from vendor/ like a normal Composer library
        parent::uninstall($repo, $package);

        // Remove the plugin info from plugins.php
        $this->removePlugin($package);
    }

    /**
     * @param PackageInterface $package
     * @throws InvalidPluginException() if there's an issue with the plugin
     */
    protected function addPlugin(PackageInterface $package)
    {
        $extra = $package->getExtra();
        $prettyName = $package->getPrettyName();

        $plugin = [];
        
        // name
        if (isset($extra['name'])) {
            $plugin['name'] = $extra['name'];
        } else {
            $plugin['name'] = str_replace('/', '.', $prettyName);
        }         
        
        // title
        if (isset($extra['title'])) {
            $plugin['title'] = $extra['title'];
        } else {
            $plugin['title'] = $prettyName;
        }        

        // version
        if (isset($extra['version'])) {
            $plugin['version'] = $extra['version'];
        } else {
            $plugin['version'] = $package->getPrettyVersion();
        }

        // description
        if (isset($extra['description'])) {
            $plugin['description'] = $extra['description'];
        } else if ($package instanceof CompletePackageInterface && ($description = $package->getDescription())) {
            $plugin['description'] = $description;
        }

        // author
        if (isset($extra['author'])) {
            $plugin['author'] = $extra['author'];
        } else if ($authorName = $this->getAuthorProperty($package, 'name')) {
            $plugin['author'] = $authorName;
        } else if ($vendor !== null) {
            $plugin['author'] = $vendor;
        }
        
        $schema = $this->getPackageBasePath($package).'/schema.xml';
        if (file_exists($schema)) {
            $plugin['schema'] = 'schema.xml';
        }

        $this->registerPlugin($package->getName(), $plugin);
    }

    /**
     * @param string $name   The plugin's package name
     * @param array  $plugin The plugin config
     */
    protected function registerPlugin($name, array $plugin)
    {
        $plugins = $this->loadPlugins();
        $plugins[$name] = $plugin;
        $this->savePlugins($plugins);
    }


    /**
     * @param PackageInterface $package
     * @param string           $property
     *
     * @return null
     */
    protected function getAuthorProperty(PackageInterface $package, $property)
    {
        if (!$package instanceof CompletePackageInterface) {
            return null;
        }

        $authors = $package->getAuthors();
        if (empty($authors)) {
            return null;
        }

        $firstAuthor = reset($authors);

        if (!isset($firstAuthor[$property])) {
            return null;
        }

        return $firstAuthor[$property];
    }

    /**
     * @param PackageInterface $package
     *
     * @return array|null The removed plugin info, or null if it wasn't there in the first place
     */
    protected function removePlugin(PackageInterface $package)
    {
        return $this->unregisterPlugin($package->getName());
    }

    /**
     * @param string $name The plugin's package name
     *
     * @return array|null The removed plugin info, or null if it wasn't there in the first place
     */
    protected function unregisterPlugin($name)
    {
        $plugins = $this->loadPlugins();

        if (!isset($plugins[$name])) {
            return null;
        }

        $plugin = $plugins[$name];
        unset($plugins[$name]);
        $this->savePlugins($plugins);
        return $plugin;
    }

    /**
     * @return array|mixed
     */
    protected function loadPlugins()
    {
        $file = $this->vendorDir.'/'.static::PLUGINS_FILE;

        if (!is_file($file)) {
            return array();
        }

        // Invalidate opcache of plugins.php if it exists
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }

        /** @var array $plugins */
        $plugins = require($file);

        // Swap absolute paths with <vendor-dir> tags
        $vendorDir = str_replace('\\', '/', $this->vendorDir);
        $n = strlen($vendorDir);

        return $plugins;
    }

    /**
     * @param array $plugins
     */
    protected function savePlugins(array $plugins)
    {
        $file = $this->vendorDir.'/'.static::PLUGINS_FILE;

        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        $array = str_replace("'<vendor-dir>", '$vendorDir . \'', var_export($plugins, true));
        file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");

        // Invalidate opcache of plugins.php if it exists
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}
