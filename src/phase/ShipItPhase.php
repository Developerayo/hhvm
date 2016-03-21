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

require_once(__DIR__.'/ShipItCLIArgument.php');

abstract class ShipItPhase {
  private bool $skipped = false;

  abstract public function getReadableName(): string;
  abstract protected function runImpl(ShipItBaseConfig $config): void;

  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector { };
  }

  final public function isSkipped(): bool {
    return $this->skipped;
  }

  final protected function skip(): void {
    $this->skipped = true;
  }

  final protected function unskip(): void {
    $this->skipped = false;
  }

  final public function run(ShipItBaseConfig $config): void {
    if ($this->isSkipped()) {
      printf("Skipping phase: %s\n", $this->getReadableName());
      return;
    }
    printf("Starting phase: %s\n", $this->getReadableName());
    $this->runImpl($config);
    printf("Finished phase: %s\n", $this->getReadableName());
  }
}
