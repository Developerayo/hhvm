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

interface ShipItSourceRepo {
  require extends ShipItRepo;
  /**
   * Get the next child of this revision in the current branch.
   *
   * @param $roots list of paths that contain synced commits.
   */
  public function findNextCommit(
    string $commit,
    ImmSet<string> $roots,
  ): ?string;

  /**
   * Get a standardized representation of the specified revision
   */
  public function getChangesetFromID(string $revision): ?ShipItChangeset;

  /**
   * Raw output of 'git show'/'hg export' or similar.
   *
   * Useful for testing.
   */
  public function getNativePatchFromID(string $revision): string;

  /**
   * Get a standardized representation of the string diff. This should be the
   * output from git format-patch, hg-export or similar. Handy for testing.
   */
  public static function getChangesetFromExportedPatch(
    string $exported_diff,
  ): ?ShipItChangeset;

  /**
   * Create a directory containing the specified paths.
   */
  public function export(
    ImmSet<string> $roots,
  ): shape('tempDir' => ShipItTempDir, 'revision' => string);
}
