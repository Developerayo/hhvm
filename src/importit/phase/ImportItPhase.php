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

abstract class ImportItPhase extends \Facebook\ShipIt\ShipItPhase {

  private static ?ImportItRepoGIT $repo;

  public function __construct(
    private (function(): ImportItRepoGIT) $repoGetter,
  ) {}

  /**
   * Used to obtain the one and only ImportItRepoGIT to be used by all phases
   * that are ImportIt specific.
   */
  protected function getSourceRepo(): ImportItRepoGIT {
    if (self::$repo !== null) {
      return self::$repo;
    }
    $getter = $this->repoGetter;
    self::$repo = $getter();
    invariant(
      self::$repo !== null,
      'Expecting to get a non-null ImportItRepoGIT from function.',
    );
    return self::$repo;
  }
}
