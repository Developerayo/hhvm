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

/**
 * Specialization of ShipItRepoGIT
 */
class ImportItRepoGIT extends \Facebook\ShipIt\ShipItRepoGIT {

  /**
   * Checks out the specified revision in a newly named branch.
   */
  public function checkoutNewBranchAt(
    string $branch,
    string $revision,
  ): void {
    $this->gitCommand('checkout', '-B', $branch, $revision);
  }

  public function importPatch(
    string $patch_file,
  ): void {
    $this->gitCommand('am', $patch_file);
  }
}
