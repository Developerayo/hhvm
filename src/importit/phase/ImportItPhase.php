<?hh // strict
/**
 * Copyright (c) 2017-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ImportIt;

use \Facebook\ShipIt\ {
  ShipItBaseConfig
};

abstract class ImportItPhase extends \Facebook\ShipIt\ShipItPhase {

  private static ?ImportItRepoGIT $repo;

  public function __construct(
    private (function(ShipItBaseConfig): ImportItRepoGIT) $repoGetter,
  ) {}

  /**
   * Used to obtain the one and only ImportItRepoGIT to be used by all phases
   * that are ImportIt specific.
   */
  protected function getSourceRepo(
    ShipItBaseConfig $config,
  ): ImportItRepoGIT {
    if (self::$repo !== null) {
      return self::$repo;
    }
    $getter = $this->repoGetter;
    self::$repo = $getter($config);
    invariant(
      self::$repo !== null,
      'Expecting to get a non-null ImportItRepoGIT from function.',
    );
    return self::$repo;
  }
}
