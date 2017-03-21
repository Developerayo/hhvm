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
   * Raw patch file that one might get from git show/hg export.  No header data
   * is included.
   *
   * Useful for testing.
   */
  public function getNativePatchFromID(string $revision): string;

  /**
   * Raw metadata containing information like the commit message, author, and
   * date that one might get from git show/hg export.
   *
   * Useful for testing.
   */
  public function getNativeHeaderFromID(string $revision): string;

  /**
   * Get a standardized representation of the string diff. This should be the
   * output from git format-patch, hg-export or similar. Handy for testing.

   * @param $header The author, date, and message information
   * @param $patch The code changes
   */
  public static function getChangesetFromExportedPatch(
    string $header,
    string $patch,
  ): ?ShipItChangeset;

  /**
   * Create a directory containing the specified paths.
   */
  public function export(
    ImmSet<string> $roots,
    ?string $rev = null, // defaults to the current revision
  ): shape('tempDir' => ShipItTempDir, 'revision' => string);
}
