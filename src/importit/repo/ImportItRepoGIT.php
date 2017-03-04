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

use \Facebook\ShipIt\ {
  ShipItChangeset
};

/**
 * Specialization of ShipItRepoGIT
 */
class ImportItRepoGIT extends \Facebook\ShipIt\ShipItRepoGIT {

  /**
   * Obtain a changeset from the GitHub repository for the Pull Request and
   * possibly return a revision that this PR is based on in the destination
   * repository.
   */
  public function getChangesetAndBaseRevisionForPullRequest(
    string $pr_number,
    string $expected_head_rev,
    string $source_default_branch,
  ): (ShipItChangeset, ?string) {
    $_lock = $this->getSharedLock()->getExclusive();
    // First, fetch the special head ref that GitHub creates for the PR.
    $this->gitCommand('fetch', 'origin', 'refs/pull/'.$pr_number.'/head');
    $actual_head_rev = trim($this->gitCommand('rev-parse', 'FETCH_HEAD'));
    invariant(
      $expected_head_rev === $actual_head_rev,
      'Expected %s to be the HEAD of the pull request, but got %s',
      $expected_head_rev,
      $actual_head_rev,
    );
    // Now compute the merge base with the default branch (that we would land
    // the pull request to).
    $merge_base = trim($this->gitCommand(
      'merge-base',
      $actual_head_rev,
      $source_default_branch,
    ));
    // We now have enough information to generate a binary diff and commit it.
    $diff = $this->gitCommand(
      'diff',
      '--binary',
      $merge_base,
      $actual_head_rev,
    );
    $branch_name = 'ImportIt-patch-for-'.$pr_number;
    $this->gitCommand('checkout', '-B', $branch_name, $merge_base);
    $this->setBranch($branch_name);
    $this->gitPipeCommand($diff, 'apply', '--binary', '-');
    $this->gitCommand(
      'commit',
      '--all',
      '--allow-empty',
      '-m',
      'ImportIt commit for #'.$pr_number,
    );

    $rev = trim($this->gitCommand('rev-parse', 'HEAD'));
    $changeset = $this->getChangesetFromID($rev);
    invariant($changeset !== null, 'Impossible');
    return tuple(
      $changeset,
      $this->findLastSourceCommit(ImmSet {}),
    );
  }
}
