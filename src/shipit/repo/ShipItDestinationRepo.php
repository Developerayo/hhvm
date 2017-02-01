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

interface ShipItDestinationRepo {
  require extends ShipItRepo;

  /**
   * Find the contents of the fbshipit-source-id: header in the latest commit.
   *
   * @param $roots list of paths that contain synced commits.
   */
  public function findLastSourceCommit(
    ImmSet<string> $roots,
  ): ?string;

  /**
   * Generate a text patch ready for committing
   */
  public function renderPatch(ShipItChangeset $patch): string;

  /**
   * Commit a standardized patch to the repo
   */
  public function commitPatch(ShipItChangeset $patch): string;

  /**
   * push local changes to the upstream
   */
  public function push(): void;
}
