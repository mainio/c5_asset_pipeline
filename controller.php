<?php

namespace Concrete\Package\AssetPipeline;

use Concrete\Core\Foundation\ClassLoader;
use Concrete\Core\Package\Package;
use Concrete\Core\Support\Facade\Facade;
use Concrete\Package\AssetPipeline\Src\FilterProvider;
use Concrete\Package\AssetPipeline\Src\PackageServiceProvider;
use Illuminate\Filesystem\Filesystem;

class Controller extends Package
{

    protected $pkgHandle = 'asset_pipeline';
    protected $appVersionRequired = '8.0.0';
    protected $pkgVersion = '0.0.2';

    public function getPackageName()
    {
        return t("Asset Pipeline");
    }

    public function getPackageDescription()
    {
        return t("Provides easy to use way to manage and build assets in concrete5.");
    }

    protected $pkgAutoloaderRegistries = array(
        'src' => '\Concrete\Package\AssetPipeline\Src',
    );

    public function install()
    {
        $fs = new Filesystem();
        if (!$fs->exists(__DIR__ . '/vendor/autoload.php')) {
            throw new Exception(t("You need to install the composer packages for this add-on before installation!"));
        }

        $this->loadDependencies();
        $pkg = parent::install();
    }

    public function on_start()
    {
        $this->loadDependencies();

        $app = Facade::getFacadeApplication();

        $sp = new PackageServiceProvider($app);
        $sp->register();
        $sp->registerOverrides();
        $sp->registerConfigurations();
        $sp->registerEvents();

        $fp = new FilterProvider($app);
        $fp->register();
        $fp->registerFilters();
    }

    protected function loadDependencies()
    {
        // No other way of managing the composer dependencies currently.
        // See: https://github.com/concrete5/concrete5-5.7.0/issues/360
        $filesystem = new Filesystem();
        $filesystem->getRequire(__DIR__ . '/vendor/autoload.php');
    }

}
