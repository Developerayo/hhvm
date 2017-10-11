<?hh //strict
/**
 * Copyright (c) 2017-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt\Config;

class ShipItConfigRegistry {

    private static ?ImmSet<classname<IShipItConfig>>
      $config_classes = null;

    /**
     * 'facebook/fbshipit' => 'Facebook\ShipIt\Config\FacebookFbshipit'
     */
    private static function getClassName(
      string $org,
      string $repo
    ): classname<IShipItConfig> {
      invariant(strlen($org) > 0, 'Org cannot be empty');
      invariant(strlen($repo) > 0, 'Repo cannot be empty');
      /* HH_IGNORE_ERROR[4110]: dynamic classname generation */
      return 'Facebook\\ShipIt\\Config\\'.
        ucfirst(strtolower($org)).ucfirst(strtolower($repo));
    }

    public static function loadConfigClasses(
    ): ImmSet<classname<IShipItConfig>> {
      $config_classes = Set {};
      // TODO use hhvm-autoload and parse from the autoload map
      // instead of walking the filetree
      $shipit_root = __DIR__.'../..';
      $directory = new \RecursiveDirectoryIterator($shipit_root.'/config/');
      $iterator = new \RecursiveIteratorIterator($directory);
      $regex = new \RegexIterator(
        $iterator,
        '@^.+\.php$@i',
        \RecursiveRegexIterator::GET_MATCH
      );
      foreach(array_keys(iterator_to_array($regex)) as $filename) {
        $class_name = self::getClassName(
          basename(dirname($filename)),
          basename($filename, '.php'),
        );
        $config_classes[] = $class_name;
      }
      return new ImmSet($config_classes);
    }

    public static function getShipItConfigClasses(
    ): ImmSet<classname<IShipItConfig>> {
      if (self::$config_classes === null) {
        self::$config_classes = self::loadConfigClasses();
      }
      return self::$config_classes;
    }

    public static function getRepoShipItConfig(
      string $org,
      string $repo,
    ): classname<IShipItConfig> {
      $config_classes = self::$config_classes;
      if ($config_classes === null) {
        $config_classes = self::loadConfigClasses();
        self::$config_classes = $config_classes;
      }
      $class_name = self::getClassName($org, $repo);
      invariant(
        $config_classes->contains($class_name),
        'Unknown repo: %s',
        $repo,
      );
      invariant(
        class_exists($class_name),
        'ShipItConfigRegistry error -- invalid classname: %s',
        $class_name,
      );
      return $class_name;
    }
}
