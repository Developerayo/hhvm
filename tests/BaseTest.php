<?hh
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

abstract class BaseTest extends \PHPUnit_Framework_TestCase {
  protected static function diffsFromMap(
    ImmMap<string, string> $diffs,
  ): ImmVector<ShipItDiff> {
    return $diffs->mapWithKey(
      ($path, $body) ==> shape('path' => $path, 'body' => $body)
    )->toImmVector();
  }

  protected function execSteps(
    string $cwd,
    ...$steps
  ): void {
    foreach ($steps as $step) {
      ShipItUtil::shellExec(
        $cwd,
        /* stdin = */ null,
        ShipItUtil::DONT_VERBOSE,
        ...$step,
      );
    }
  }
}
