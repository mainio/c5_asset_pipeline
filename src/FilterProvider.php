<?php

namespace Concrete\Package\AssetPipeline\Src;

use Assetic\Filter\CssMinFilter;
use Assetic\Filter\ScssphpFilter;
use Concrete\Core\Foundation\Service\Provider as ServiceProvider;
use Concrete\Package\AssetPipeline\Src\Asset\Filter\Assetic\JShrinkFilter;
use Concrete\Package\AssetPipeline\Src\Asset\Filter\Assetic\LessphpFilter;
use Concrete\Package\AssetPipeline\Src\Asset\Filter\Less\FunctionProvider as LessFunctionProvider;
use Concrete\Package\AssetPipeline\Src\Asset\Filter\Scss\FunctionProvider as ScssFunctionProvider;
use Concrete\Package\AssetPipeline\Src\StyleCustomizer\Style\Value\Extractor\Less as LessStyleValueExtractor;
use Concrete\Package\AssetPipeline\Src\StyleCustomizer\Style\Value\Extractor\Scss as ScssStyleValueExtractor;

class FilterProvider extends ServiceProvider
{

    public function register()
    {
        $config = $this->app->make('config');

        // Register Less filter & variable value extractor
        $this->app->bind('assets/filter/less', function ($app, $options) use ($config) {
            $lessf = new LessphpFilter(
                array(
                    'cache_dir' => $config->get('concrete.cache.directory'),
                    'compress' => !!$config->get('concrete.theme.compress_preprocessor_output'),
                    'sourceMap' => !$config->get('concrete.theme.compress_preprocessor_output') && !!$config->get('concrete.theme.generate_less_sourcemap'),
                )
            );
            if ($config->get('app.asset_filter_options.less.legacy_url_support', false)) {
                $lessf->setBasePath('/' . ltrim($app['app_relative_path'], '/'));
                $lessf->setRelativeUrlPaths(true);
            }

            $assets = $options['assets'];

            $variableList = $assets->getStyleSheetVariables();
            if (is_array($variableList)) {
                $lessf->setLessVariables($variableList);
            }

            $fp = new LessFunctionProvider();
            $fp->registerFor($lessf);

            return $lessf;
        });
        $this->app->bind('assets/value/extractor/less', function ($app, $args) {
            list($file, $urlroot) = array_pad((array) $args, 2, false);
            return new LessStyleValueExtractor($file, $urlroot);
        });

        // Register SCSS filter & variable value extractor
        $this->app->bind('assets/filter/scss', function ($app, $options) use ($config) {

            $assets = $options['assets'];

            // There does not seem to be a way to get the source maps to the
            // ScssPhp at the moment:
            // https://github.com/leafo/scssphp/issues/135
            $scssf = new ScssphpFilter();
            if ($config->get('concrete.theme.compress_preprocessor_output')) {
                $scssf->setFormatter('Leafo\ScssPhp\Formatter\Compressed');
            } else {
                $scssf->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
            }

            $fp = new ScssFunctionProvider();
            $fp->registerFor($scssf);

            $variableList = $assets->getStyleSheetVariables();
            if (is_array($variableList)) {
                $scssf->setVariables($variableList);
            }

            return $scssf;
        });
        $this->app->bind('assets/value/extractor/scss', function ($app, $args) {
            list($file, $urlroot) = array_pad((array) $args, 2, false);
            return new ScssStyleValueExtractor($file, $urlroot);
        });

        // Register JShrink filter
        $this->app->bind('assets/filter/jshrink', function ($app, $options) {

            $assets = $options['assets'];

            $jsf = new JShrinkFilter();
            return $jsf;
        });

        // Register CssMin filter
        $this->app->bind('assets/filter/cssmin', function ($app, $options) {

            $assets = $options['assets'];

            $cmf = new CssMinFilter();
            return $cmf;
        });
    }

    public function registerFilters()
    {
        $config = $this->app->make('config');
        $repository = $this->app->make('Concrete\Package\AssetPipeline\Src\Asset\Filter\SettingsRepositoryInterface');
        $filters = $config->get('app.asset_filters');
        if (is_array($filters)) {
            foreach ($filters as $key => $options) {
                $repository->registerFilterSettings($key, $options);
            }
        }
    }

}
