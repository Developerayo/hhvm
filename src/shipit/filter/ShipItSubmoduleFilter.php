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

final class ShipItSubmoduleFilter {
  private static function makeSubmoduleDiff(
    string $path,
    string $old_rev,
    string $new_rev,
  ): string {
    return "index {$old_rev}..{$new_rev} 160000\n".
           "--- a/{$path}\n".
           "+++ b/{$path}\n".
           "@@ -1 +1 @@\n".
           "-Subproject commit {$old_rev}\n".
           "+Subproject commit {$new_rev}\n";
  }

  /**
   * Convert a text file like:
   *   Subproject commit deadbeef
   * ...to an actual subproject commit.
   *
   * For example, hphp/facebook/third-party-rev.txt contains this, and becomes
   * the 'third-party/' submodule on github.com/facebook/hhvm/
   */
  public static function useSubmoduleCommitFromTextFile(
    ShipItChangeset $changeset,
    string $text_file_with_rev,
    string $submodule_path,
  ): ShipItChangeset {
    $diffs = Vector { };
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $body = $diff['body'];

      if ($path !== $text_file_with_rev) {
        $diffs[] = $diff;
        continue;
      }

      $old_rev = $new_rev = null;
      foreach(explode("\n", $body) as $line) {
        if (!strncmp('-Subproject commit ', $line, 19)) {
          $old_rev = trim(substr($line, 19));
        } else if (!strncmp('+Subproject commit ', $line, 19)) {
          $new_rev = trim(substr($line, 19));
        }
      }

      if ($old_rev === null || $new_rev === null) {
        // Do nothing - this will lead to a 'patch does not apply' error for
        // human debugging, which seems like a reasonable error to give :)
        $diffs[] = $diff;
        continue;
      }

      $diffs[] = shape(
        'path' => $submodule_path,
        'body' => self::makeSubmoduleDiff(
          $submodule_path,
          $old_rev,
          $new_rev,
        ),
      );
    }

    return $changeset->withDiffs($diffs->toImmVector());
  }
}
