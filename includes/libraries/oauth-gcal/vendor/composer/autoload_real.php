<?php

namespace Tyche\BKAP;

class ComposerAutoloaderInit8178541f110b049550bbc7afbc9a52f2
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Tyche\BKAP\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('\Tyche\BKAP\ComposerAutoloaderInit8178541f110b049550bbc7afbc9a52f2', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Tyche\BKAP\ClassLoader();
        spl_autoload_unregister(array('\Tyche\BKAP\ComposerAutoloaderInit8178541f110b049550bbc7afbc9a52f2', 'loadClassLoader'));

        $useStaticLoader = PHP_VERSION_ID >= 50600 && !defined('HHVM_VERSION') && (!function_exists('zend_loader_file_encoded') || !zend_loader_file_encoded());
        if ($useStaticLoader) {
            require_once __DIR__ . '/autoload_static.php';

            call_user_func(\Tyche\BKAP\ComposerStaticInit8178541f110b049550bbc7afbc9a52f2::getInitializer($loader));
        } else {
            $map = require __DIR__ . '/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = require __DIR__ . '/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }

            $classMap = require __DIR__ . '/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }
        }

        $loader->register(true);

        if ($useStaticLoader) {
            $includeFiles = \Tyche\BKAP\ComposerStaticInit8178541f110b049550bbc7afbc9a52f2::$files;
        } else {
            $includeFiles = require __DIR__ . '/autoload_files.php';
        }

        foreach ($includeFiles as $identifier => $file) {
            $fileIdentifier = 'tyche_bkap_' . $identifier;
            composerRequire8178541f110b049550bbc7afbc9a52f2($fileIdentifier, $file);
        }

        return $loader;
    }
}

function composerRequire8178541f110b049550bbc7afbc9a52f2($fileIdentifier, $file)
{

    // Compatibility with Google Listing Ads.
    if ( 'tyche_bkap_a8d3953fd9959404dd22d3dfcd0a79f0' === $fileIdentifier && isset( $GLOBALS['__composer_autoload_files']['a8d3953fd9959404dd22d3dfcd0a79f0'] ) ) {
        return;
    }
    
    if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
        require $file;

        $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
    }
}
