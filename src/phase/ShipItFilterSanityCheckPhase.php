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

final class ShipItFilterSanityCheckPhase extends ShipItPhase {
  public function __construct(
    private (function(ShipItChangeset):ShipItChangeset) $filter,
    private ImmSet<string> $sourceRoots,
  ) {}

  <<__Override>>
  public function getReadableName(): string {
    return 'Sanity-check commit filter';
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $_): void {
    $this->assertValid();
  }

  // Public for testing
  public function assertValid(): void {
    $filter = $this->filter;
    $allows_all = false;
    foreach ($this->sourceRoots as $root) {
      $test_file = $root.'/shipit_test_file.txt';
      $test_file = str_replace('//', '/', $test_file);
      $changeset = (new ShipItChangeset())
        ->withDiffs(ImmVector {
          shape('path' => $test_file, 'body' => 'junk')
        });
      $changeset = $filter($changeset);
      if ($changeset->getDiffs()->count() !== 1) {
        invariant_violation(
          "Source root '%s' specified, but is removed by filter; debug: %s\n",
          $root,
          var_export($changeset->getDebugMessages(), /* return = */ true),
        );
      }

      if ($root === '' || $root === '.' || $root === './') {
        $allows_all = true;
      }
    }

    if ($allows_all || $this->sourceRoots->count() === 0) {
      return;
    }

    $path = '!!!shipit_test_file!!!';
    $changeset = (new ShipItChangeset())
      ->withDiffs(ImmVector {
        shape('path' => $path, 'body' => 'junk')
      });
    $changeset = $filter($changeset);
    invariant(
      $changeset->getDiffs()->count() === 0,
      'Path "%s" is not in a sourceRoot, but passes filter',
      $path,
    );
  }
}
