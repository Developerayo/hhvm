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

final class ShipItShellCommandResult {
  public function __construct(
    private int $exitCode,
    private string $stdout,
    private string $stderr,
  ) {
  }

  public function getExitCode(): int {
    return $this->exitCode;
  }

  public function getStdOut(): string {
    return $this->stdout;
  }

  public function getStdErr(): string {
    return $this->stderr;
  }
}
