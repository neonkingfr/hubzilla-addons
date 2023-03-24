<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4bbe6cd87c4fda5fbd053745ad7076cb
{
    public static $prefixLengthsPsr4 = array (
        'H' => 
        array (
            'Hybridauth\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Hybridauth\\' => 
        array (
            0 => __DIR__ . '/..' . '/hybridauth/hybridauth/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4bbe6cd87c4fda5fbd053745ad7076cb::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4bbe6cd87c4fda5fbd053745ad7076cb::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}