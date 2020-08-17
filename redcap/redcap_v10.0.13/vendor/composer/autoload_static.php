<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit44147b6be727f57761473a6c2c1115a7
{
    public static $prefixLengthsPsr4 = array (
        'V' => 
        array (
            'Vanderbilt\\REDCap\\Classes\\' => 26,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Vanderbilt\\REDCap\\Classes\\' => 
        array (
            0 => __DIR__ . '/../..' . '/Classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit44147b6be727f57761473a6c2c1115a7::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit44147b6be727f57761473a6c2c1115a7::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}