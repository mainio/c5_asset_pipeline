<?php
namespace Concrete\Package\AssetPipeline;

use Concrete\Package\AssetPipeline\Src\FilterProvider;
use Concrete\Package\AssetPipeline\Src\PackageServiceProvider;
use Config;
use Core;
use Package;

defined('C5_EXECUTE') or die("Access Denied.");

class Controller extends Package
{

    protected $pkgHandle = 'asset_pipeline';
    protected $appVersionRequired = '5.7.1';
    protected $pkgVersion = '0.0.1';

    public function getPackageName()
    {
        return t("Asset Pipeline");
    }

    public function getPackageDescription()
    {
        return t("Provides easy to use way to manage and build assets in concrete5.");
    }

    public function install()
    {
        $fs = new \Illuminate\Filesystem\Filesystem();
        if ($fs->exists(__DIR__ . '/vendor/autoload.php')) {
            throw new Exception(t("You need to install the composer packages for this add-on before installation!"));
        }

        $pkg = parent::install();
    }

    public function on_start()
    {
        $this->loadDependencies();

        $app = Core::getFacadeApplication();
        $sp = new PackageServiceProvider($app);
        $sp->register();
        $sp->registerOverrides();
        $sp->registerEvents();

        // The filter definitions can be overridden by the site configs, so
        // first check whether they are set or not.
        if (!Config::has('assets.filters')) {
            Config::set('assets.filters', array(
                'less' => array(
                    'applyTo' => '\.less$',
                    'customizableStyles' => true,
                ),
                'scss' => array(
                    'applyTo' => '\.scss$',
                    'customizableStyles' => true,
                ),
                'js' => array(
                    'applyTo' => '\.js$',
                ),
            ));
        }

        $fp = new FilterProvider($app);
        $fp->register();
    }

    protected function loadDependencies()
    {
        // No other way of managing the composer dependencies currently.
        // See: https://github.com/concrete5/concrete5-5.7.0/issues/360
        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $filesystem->getRequire(__DIR__ . '/vendor/autoload.php');
    }

}