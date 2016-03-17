<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class ShipItPushPhase extends ShipItPhase {
  <<__Override>>
  final public function getReadableName(): string {
    return "Push destination repository";
  }

  <<__Override>>
  final public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-push',
        'description' => 'Do not push the destination repository',
        'write' => $_ ==> $this->skip(),
      ),
    };
  }

  <<__Override>>
  final protected function runImpl(ShipItBaseConfig $config): void {
    $repo = ShipItRepo::open(
      $config->getDestinationPath(),
      $config->getDestinationBranch(),
    );
    invariant(
      $repo instanceof ShipItDestinationRepo,
      '%s is not a writable repository type - got %s, needed %s',
      $config->getDestinationPath(),
      get_class($repo),
      ShipItDestinationRepo::class,
    );
    $repo->push();
  }
}
