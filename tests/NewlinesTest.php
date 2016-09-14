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

final class NewlinesTest extends BaseTest {
  const UNIX_TXT = "foo\nbar\nbaz\n";
  const WINDOWS_TXT = "foo\r\nbar\r\nbaz\r\n";

  public function testTestData(): void {
    $this->assertSame(
      strlen(self::UNIX_TXT) + 3,
      strlen(self::WINDOWS_TXT),
    );
  }

  public function testMercurialSource(): void {
    $temp_dir = new ShipItTempDir('mercurial-newline-test');

    $this->createTestFiles($temp_dir);

    $this->execSteps(
      $temp_dir->getPath(),
      [ 'hg', 'init' ],
      [ 'hg', 'commit', '-Am', 'add files' ],
    );

    $repo = new ShipItRepoHG($temp_dir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('.');
    assert($changeset !== null);

    $this->assertContainsCorrectNewLines($changeset);
    $this->assertCreatesCorrectNewLines($changeset);
  }

  public function testGitSource(): void {
    $temp_dir = new ShipItTempDir('mercurial-newline-test');

    $this->createTestFiles($temp_dir);
    $this->initGitRepo($temp_dir);

    $this->execSteps(
      $temp_dir->getPath(),
      [ 'git', 'add', '.' ],
      [ 'git', 'commit', '-m', 'add files' ],
    );

    $repo = new ShipItRepoGIT($temp_dir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('HEAD');
    assert($changeset !== null);

    $this->assertContainsCorrectNewLines($changeset);
    $this->assertCreatesCorrectNewLines($changeset);
  }

  private function createTestFiles(
    ShipItTempDir $temp_dir,
  ) {
    file_put_contents($temp_dir->getPath().'/unix.txt', self::UNIX_TXT);
    file_put_contents($temp_dir->getPath().'/windows.txt', self::WINDOWS_TXT);
  }

  private function assertContainsCorrectNewLines(
    ShipItChangeset $changeset,
  ): void {
    $map = Map { };
    foreach ($changeset->getDiffs() as $diff) {
      $map[$diff['path']] = $diff['body'];
    }
    $this->assertContains(
      "\n",
      $map['unix.txt'],
    );
    $this->assertContains(
      "\r\n",
      $map['windows.txt'],
    );
    $this->assertNotContains(
      "\r\n",
      $map['unix.txt'],
    );
  }

  private function initGitRepo(ShipItTempDir $temp_dir): void {
    $this->execSteps(
      $temp_dir->getPath(),
      [ 'git', 'init' ],
    );
    $this->configureGit($temp_dir);
  }

  private function assertCreatesCorrectNewLines(
    ShipItChangeset $changeset,
  ): void {
    $temp_dir = new ShipItTempDir('newline-output-test');
    $this->initGitRepo($temp_dir);

    $repo = new ShipItRepoGIT($temp_dir->getPath(), '--orphan=master');
    $repo->commitPatch($changeset);

    $this->assertSame(
      self::UNIX_TXT,
      file_get_contents($temp_dir->getPath().'/unix.txt'),
      'Unix test file',
    );
    $this->assertSame(
      self::WINDOWS_TXT,
      file_get_contents($temp_dir->getPath().'/windows.txt'),
      'Windows text file',
    );
  }
}
