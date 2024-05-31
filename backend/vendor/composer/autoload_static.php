<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit004d5e1c65709edb4979886e1d5b41ed
{
    public static $files = array (
        '60799491728b879e74601d83e38b2cad' => __DIR__ . '/..' . '/illuminate/collections/helpers.php',
        '0b317f968294f4450eb485beff8cc898' => __DIR__ . '/../..' . '/utils/sv-helpers.php',
        '48245e1040f55b622b2af1c3b083bf1b' => __DIR__ . '/../..' . '/utils/sv-forum-helpers.php',
        'fe8d3b938c9f55050ef1a64a98ebf8a0' => __DIR__ . '/../..' . '/utils/sv-woo-helpers.php',
        '33d783d7259cb9134e014d68e71c0e77' => __DIR__ . '/../..' . '/utils/sv-story-helpers.php',
        '174a937957c64f9ae9c303b44e2c2aaf' => __DIR__ . '/../..' . '/utils/sv-member-helpers.php',
        '13412a5b8e3f5db7b27eb3793f77158a' => __DIR__ . '/../..' . '/utils/sv-reaction-helpers.php',
        '25909d7e767f408a9fe8a89866d8e466' => __DIR__ . '/../..' . '/utils/sv-mediapress-helpers.php',
        '1ffbcb01c6788c27b339770834d186d2' => __DIR__ . '/../..' . '/utils/sv-bbm-helper.php',
        'dfdd3999773a398d4505041b72ff92b3' => __DIR__ . '/../..' . '/utils/sv-membership-helper.php',
        '7eb9a460bc39109d6003c218bc42e54b' => __DIR__ . '/../..' . '/utils/sv-gamipress-helpers.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Container\\' => 14,
        ),
        'I' => 
        array (
            'Includes\\' => 9,
            'Illuminate\\Support\\' => 19,
            'Illuminate\\Contracts\\' => 21,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Container\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/container/src',
        ),
        'Includes\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'Illuminate\\Support\\' => 
        array (
            0 => __DIR__ . '/..' . '/illuminate/collections',
            1 => __DIR__ . '/..' . '/illuminate/macroable',
        ),
        'Illuminate\\Contracts\\' => 
        array (
            0 => __DIR__ . '/..' . '/illuminate/contracts',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit004d5e1c65709edb4979886e1d5b41ed::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit004d5e1c65709edb4979886e1d5b41ed::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit004d5e1c65709edb4979886e1d5b41ed::$classMap;

        }, null, ClassLoader::class);
    }
}