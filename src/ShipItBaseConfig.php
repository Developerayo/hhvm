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

final class ShipItBaseConfig {
  private bool $mutable = false;
  private bool $canBeMadeMutable = true;

  public function __construct(
    private string $baseDirectoryPath,
    private string $defaultSourceDirectoryName,
    private string $defaultDestinationDirectoryName,
  ) { }

  // the 0 argument is run-time log rate. Users can't do anything about this,
  // so suppress all log messages about it.
  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__makeMutable(): void {
    invariant(
      $this->canBeMadeMutable,
      '__fb__makeMutable() called after __fb__makeImmutable',
    );
    $this->mutable = true;
  }

  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__makeImmutable(): void {
    $this->mutable = false;
    $this->canBeMadeMutable = false;
  }

  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__setSourceDirectoryName(string $v): void {
    invariant(
      $this->mutable,
      'trying to mutate immutable object',
    );
    $this->defaultSourceDirectoryName = $v;
  }

  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__setDestinationDirectoryName(string $v): void {
    invariant(
      $this->mutable,
      'trying to mutate immutable object',
    );
    $this->defaultDestinationDirectoryName = $v;
  }

  public function getBaseDirectory(): string {
    return $this->baseDirectoryPath;
  }

  public function withBaseDirectory(string $v): this {
    return $this->modified($ret ==> $ret->baseDirectoryPath = $v);
  }

  private ?string $sourcePath;

  public function getSourcePath(): string {
    return $this->sourcePath
      ?? $this->baseDirectoryPath.'/'.$this->defaultSourceDirectoryName;
  }

  public function withSourcePath(string $v): this {
    return $this->modified($ret ==> $ret->sourcePath = $v);
  }

  private ?string $destinationPath;
  public function getDestinationPath(): string {
    return $this->destinationPath
      ?? $this->baseDirectoryPath.'/'.$this->defaultDestinationDirectoryName;
  }

  public function withDestinationPath(string $v): this {
    return $this->modified($ret ==> $ret->destinationPath = $v);
  }

  private bool $verbose = false;
  public function isVerboseEnabled(): bool {
    return $this->verbose;
  }

  public function withVerboseEnabled(): this {
    return $this->modified($ret ==> $ret->verbose = true);
  }

  private string $sourceBranch = 'master';
  public function getSourceBranch(): string {
    return $this->sourceBranch;
  }

  public function withSourceBranch(string $branch): this {
    return $this->modified($ret ==> $ret->sourceBranch = $branch);
  }

  private string $destinationBranch = 'master';
  public function getDestinationBranch(): string {
    return $this->destinationBranch;
  }

  public function withDestinationBranch(string $branch): this {
    return $this->modified($ret ==> $ret->destinationBranch = $branch);
  }

  private function modified<Tignored>(
    (function(ShipItBaseConfig):Tignored) $mutator,
  ): ShipItBaseConfig {
    $ret = $this->mutable ? $this : (clone $this);
    $mutator($ret);
    return $ret;
  }
}
