<?php

namespace Concrete\Package\AssetPipeline\Src\Service;

use Assetic\Asset\AssetCollection;
use Concrete\Core\Application\Application;
use Concrete\Core\Foundation\Environment;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Theme\Theme;
use Concrete\Core\Support\Facade\Facade;
use Exception;
use Illuminate\Filesystem\Filesystem;

class Assets
{

    protected $app;
    protected $context;
    protected $themeBasePath;
    protected $stylesheetVariables = array();

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->setThemeContext();
    }

    public function setThemeContext($theme = null)
    {
        if ($theme === null) {
            $c = Page::getCurrentPage();
            if (is_object($c)) {
                $theme = $c->getCollectionThemeObject();
            } else {
                $theme = Theme::getSiteTheme();
            }
        }
        if (!is_object($theme)) {
            // TODO: Check whether this can happen or not...
            throw new Exception(t("No theme available for the page!"));
        }
        $env = Environment::get();
        $r = $env->getRecord(
            DIRNAME_THEMES . '/' . $theme->getThemeHandle(),
            $theme->getPackageHandle()
        );
        $this->themeBasePath = $r->file;
    }

    public function setStylesheetVariables($context, array $variables)
    {
        $this->stylesheetVariables[$context] = $variables;
    }

    public function getStyleSheetVariables($context = null)
    {
        if ($context === null) {
            $combined = array();
            foreach ($this->stylesheetVariables as $variables) {
                $combined = array_merge($combined, $variables);
            }
            return $combined;
        } elseif (isset($this->stylesheetVariables[$context])) {
            return $this->stylesheetVariables[$context];
        }
    }

    public function getAssetBasePath()
    {
        return $this->assetBasePath;
    }

    public function css($assets, array $options = null)
    {
        $path = $this->cssPath($assets, $options);

        // TODO: Add the combinedAssetSourceFiles to the asset object before
        //       printing it out
        $html = $this->app->make('helper/html');
        return $html->css($path) . PHP_EOL;
    }

    public function cssPath($assets, array $options = null)
    {
        return $this->compileCssToCache($assets, $options);
    }

    /**
     * Alias for the method javascript
     *
     * @param array $assets
     * @param array $options
     */
    public function js($assets, array $options = null){
        return $this->javascript($assets, $options);
    }

    public function javascript($assets, array $options = null)
    {
        $path = $this->javascriptPath($assets, $options);

        // TODO: Add the combinedAssetSourceFiles to the asset object before
        //       printing it out
        $html = $this->app->make('helper/html');
        return $html->javascript($path) . PHP_EOL;
    }

    public function javascriptPath($assets, array $options = null)
    {
        return $this->compileJavascriptToCache($assets, $assets);
    }

    public function compileCss(array $assetPaths, array $options = null)
    {
        return $this->compileAssets('css', $assetPaths, $options);
    }

    public function compileCssToCache(array $assetPaths, array $options = null)
    {
        return $this->compileAssetsToCache('css', DIRNAME_CSS, $assetPaths, $options);
    }

    public function compileJavascript(array $assetPaths, array $options = null)
    {
        return $this->compileAssets('js', $assetPaths, $options);
    }

    public function compileJavascriptToCache(array $assetPaths, array $options = null)
    {
        return $this->compileAssetsToCache('js', DIRNAME_JAVASCRIPT, $assetPaths, $options);
    }

    public function compileAssets($extension, array $assetPaths, array $options = null)
    {
        if (count($assetPaths) < 1) {
            throw new Exception(t("Cannot compile asset without any target files."));
        }
        // Modify the asset paths to full paths
        foreach ($assetPaths as $k => $path) {
            $dirName = null;
            if ($extension == 'css') {
                $dirName = DIRNAME_CSS;
            } elseif ($extension == 'js') {
                $dirName = DIRNAME_JAVASCRIPT;
            }
            $path = $this->getFullPath($path, $dirName);
            $assetPaths[$k] = $path;
        }

        $assets = $this->getAssetCollection($assetPaths);
        return $assets->dump();
    }

    public function compileAssetsToCache($extension, $cacheDir, array $assetPaths, array $options = null)
    {
        $options = (array) $options;

        $config = $this->app->make('config');

        $cachePath = $config->get('concrete.cache.directory');
        $cachePathRelative = REL_DIR_FILES_CACHE;

        $outputPath = $cachePath . '/' . $cacheDir;
        $relativePath = $cachePathRelative . '/' . $cacheDir;

        $name = isset($options['name']) ? $options['name'] : $this->getDefaultAssetNameFor($extension);
        $useDigest = isset($options['skipDigest']) ? !$options['skipDigest'] : true;

        $outputFileName = $name . '.' . $extension;
        if ($config->get('concrete.cache.theme_css') && file_exists($outputPath . '/' . $outputFileName)) {
            if ($useDigest) {
                $digest = hash_file('md5', $outputPath . '/' . $outputFileName);
                return $relativePath . '/' . $name . '-' . $digest . '.' . $extension;
            } else {
                return $relativePath . '/' . $outputFileName;
            }
        }

        // Save and cache
        $contents = $this->compileAssets($extension, $assetPaths, $options);

        $fs = new Filesystem();
        if (!file_exists($outputPath)) {
            $fs->makeDirectory($outputPath, $config->get('concrete.filesystem.permissions.directory'), true, true);
        }
        $fs->put($outputPath . '/' . $outputFileName, $contents);

        $digest = hash_file('md5', $outputPath . '/' . $outputFileName);
        $digestFileName = $name . '-' . $digest . '.' . $extension;
        $fs->put($outputPath . '/' . $digestFileName, $contents);

        return $useDigest ? $relativePath . '/' . $digestFileName : $relativePath . '/' . $outputFileName;
    }

    public function getAssetCollection(array $assetPaths)
    {
        $app = Facade::getFacadeApplication();

        $factory = $app->make('Assetic\Factory\AssetFactory');
        $fsr = $this->app->make('Concrete\Package\AssetPipeline\Src\Asset\Filter\SettingsRepositoryInterface');

        $fm = $factory->getFilterManager();
        $assets = new AssetCollection();

        // Set the filters to the filter manager
        foreach ($fsr->getAllFilterSettings() as $key => $flt) {
            if (!$this->app->bound('assets/filter/' . $key)) {
                throw new Exception(t("Filter not available for key: %s", $key));
            }
            $filter = $this->app->make('assets/filter/' . $key, array('assets' => $this));
            $fm->set($key, $filter);
        }

        // Create the asset and push it into the AssetCollection
        // with the filter keys that should be applied to that
        // asset
        $plainAssets = array();
        foreach ($assetPaths as $k => $path) {
            $appliedFilters = array();
            foreach ($fsr->getAllFilterSettings() as $key => $flt) {
                if (!isset($flt['applyTo']) || !$flt['applyTo'] || !is_string($flt['applyTo'])) {
                    continue;
                }
                if (preg_match('#' . str_replace('#', '\#', $flt['applyTo']) . '#', $path)) {
                    $appliedFilters[] = $key;
                }
            }
            if (count($appliedFilters) > 0) {
                $assets->add($factory->createAsset($path, $appliedFilters));
            } else {
                $plainAssets[] = $path;
            }
        }

        // Add assets that did not go through any filters
        if (count($plainAssets) > 0) {
            $assets->add($factory->createAsset($plainAssets));
        }

        return $assets;
    }

    public function getFullPath($path, $dirName = null)
    {
        if ($path[0] == '@') {
            if (($pos = strpos($path, '/')) !== false) {
                $location = substr($path, 1, $pos);
                $subpath = substr($path, $pos + 1);

                $locationPath = '';
                if ($location == 'core') {
                    $locationPath = DIR_BASE_CORE;
                } elseif ($location == 'app') {
                    $locationPath = DIR_APPLICATION;
                } elseif ($location == 'package') {
                    if (($pos = strpos($subpath, '/')) !== false) {
                        $pkgHandle = substr($subpath, 0, $pos);
                        $subpath = substr($subpath, $pos + 1);
                        $locationPath = DIR_PACKAGES . '/' . $pkgHandle;
                    } else {
                        throw new Exception(t("Invalid path: %s. Package not defined.", $path));
                    }
                } elseif ($location == 'theme') {
                    if (($pos = strpos($subpath, '/')) !== false) {
                        $themeHandle = substr($subpath, 0, $pos);
                        $subpath = substr($subpath, $pos + 1);
                        if (is_object($th = Theme::getByHandle($themeHandle))) {
                            $env = Environment::get();
                            $locationPath = $env->getPath(DIRNAME_THEMES . '/' . $themeHandle, $th->getPackageHandle());
                        } else {
                            throw new Exception(t("Invalid theme in path: %s. Theme '%s' does not exist."));
                        }
                    } else {
                        throw new Exception(t("Invalid path: %s. Theme not defined.", $path));
                    }
                } else {
                    throw new Exception(t("Invalid path: %s. Unknown location: %s.", $path, $location));
                }

                if (!empty($locationPath)) {
                    return $locationPath . '/' . $dirName . '/' . $subpath;
                }
            } else {
                // This is an assetic alias, e.g. "@jquery".
                return $path;
            }
        } elseif ($path[0] == '/' || preg_match('#[a-z]:[/\\\]#i', $path)) {
            return $path;
        }

        // Theme specific CSS (default)
        return $this->themeBasePath . '/' . $dirName . '/' . $path;
    }

    protected function getDefaultAssetNameFor($extension)
    {
        switch ($extension) {
            case 'css':
                return 'style';
            case 'js':
                return 'script';
            default:
                return $extension;
        }
    }

    /**
     * Generates an asset digest by combining the digests of the asset's source
     * files and generating a hash from the combined string. This is used for
     * asset file versioning to make sure we output the correct file and also
     * to make it easier to deal with browser caching.
     *
     * This is run for the original assets because
     * we need to know the file digest BEFORE we run the asset compilation and
     * know the actual file conents.
     */
    protected function generateAssetDigest(array $assetPaths)
    {
        $digest = '';
        foreach ($assetPaths as $asset) {
            $digest .= $asset . '#' . $lastModified;
        }
        return md5($digest);
    }

}
