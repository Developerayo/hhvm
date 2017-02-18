<?hh
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ImportIt;


final class SubmoduleTest extends \Facebook\ShipIt\BaseTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = \Facebook\ShipIt\ShipItRepoGIT::getChangesetFromExportedPatch(
      file_get_contents(
        __DIR__.'/git-diffs/submodule-hhvm-third-party.diff'
      )
    );
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $changeset = ImportItSubmoduleFilter::moveSubmoduleCommitToTextFile(
      $changeset,
      'third-party',
      'fbcode/hphp/facebook/third-party-rev.txt',
    );

    $this->assertSame(1, $changeset->getDiffs()->keys()->count());
    $change = $changeset->getDiffs()->firstValue();
    assert($change !== null);
    $change = $change['body'];
    $this->assertNotEmpty($change);

    $this->assertContains(
      '--- a/fbcode/hphp/facebook/third-party-rev.txt',
      $change,
    );
    $this->assertContains(
      '+++ b/fbcode/hphp/facebook/third-party-rev.txt',
      $change,
    );

    $old_pos = strpos($change, '2142ac3221e20e2f6c640cbb15019d59a9e7624d');
    $new_pos = strpos($change, 'd31d3a5bcbb617bf338c8f7eb51763effa4ea227');

    $this->assertNotFalse($old_pos);
    $this->assertNotFalse($new_pos);
    $this->assertGreaterThan($old_pos, $new_pos);
  }
}
