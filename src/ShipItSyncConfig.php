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

final class ShipItSyncConfig {
  private ?string $firstCommit = null;
  private ImmSet<string> $skippedSourceCommits = ImmSet { };
  private ?string $patchesDirectory = null;
  private ImmSet<string> $destinationRoots = ImmSet { };
  private ?string $statsFilename = null;

  public function __construct(
    private ImmSet<string> $sourceRoots,
    private
      (function(ShipItBaseConfig, ShipItChangeset): ShipItChangeset)
      $filter,
  ) {
  }

  public function getFirstCommit(): ?string {
    return $this->firstCommit;
  }

  public function withFirstCommit(?string $commit): this {
    invariant(
      $commit !== '',
      'Pass null instead of empty string',
    );
    return $this->modified($ret ==> $ret->firstCommit = $commit);
  }

  public function getSkippedSourceCommits(): ImmSet<string> {
    return $this->skippedSourceCommits;
  }

  public function withSkippedSourceCommits(ImmSet<string> $commits): this {
    return $this->modified($ret ==> $ret->skippedSourceCommits = $commits);
  }

  public function getPatchesDirectory(): ?string {
    return $this->patchesDirectory;
  }

  public function withPatchesDirectory(?string $dir): this {
    invariant(
      $dir !== '',
      'Pass null instead of empty string',
    );
    return $this->modified($ret ==> $ret->patchesDirectory = $dir);
  }

  public function getDestinationRoots(): ImmSet<string> {
    return $this->destinationRoots;
  }

  public function withDestinationRoots(ImmSet<string> $roots): this {
    return $this->modified($ret ==> $ret->destinationRoots = $roots);
  }

  public function getSourceRoots(): ImmSet<string> {
    return $this->sourceRoots;
  }

  public function getFilter(
  ): (function(ShipItBaseConfig, ShipItChangeset): ShipItChangeset) {
    return $this->filter;
  }

  public function getStatsFilename(): ?string {
    return $this->statsFilename;
  }

  public function withStatsFilename(?string $filename): this {
    invariant(
      $filename !== '',
      'Pass null instead of empty string',
    );
    return $this->modified($ret ==> $ret->statsFilename = $filename);
  }

  private function modified<Tignored>(
    (function(ShipItSyncConfig):Tignored) $mutator,
  ): ShipItSyncConfig {
    $ret = clone $this;
    $mutator($ret);
    return $ret;
  }
}
