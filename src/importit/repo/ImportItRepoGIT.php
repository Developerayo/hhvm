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
    $lock = $this->getSharedLock()->getExclusive();
    $base_rev = $this->getHEADSha();
    // First, we apply our patch file.
    $this->gitCommand('am', $patch_file);
    // Now, reset to the parent revision.
    $this->gitCommand('reset', '--hard', $base_rev);
    // Now squash all the commits that were after it in the tree.  HEAD@{1}
    // points to our previous HEAD, whish is now the last applied patch.
    $this->gitCommand('merge', '--squash', 'HEAD@{1}');
    // Now we can safely commit our changes.
    $this->gitCommand('commit', '--no-edit');
  }
}
