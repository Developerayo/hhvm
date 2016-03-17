<?hh
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

/** This is interesting because it ends up with two diffs for the same path:
 * 1. deleting the file
 * 2. creating the symlink
 */
final class FileToSymlinkTest extends BaseTest {
  public function testFileToSymlink(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      file_get_contents(
        __DIR__.'/git-diffs/file-to-symlink.diff',
      )
    );
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $this->assertEquals(
      ImmVector { 'bar', 'bar' },
      $changeset->getDiffs()->map($diff ==> $diff['path']),
    );

    // Order is important: file should be deleted before symlink is created
    $delete_file = $changeset->getDiffs()[0];
    $this->assertContains('deleted file mode 100644', $delete_file['body']);
    $create_symlink = $changeset->getDiffs()[1];
    $this->assertContains('new file mode 120000', $create_symlink['body']);
  }
}
