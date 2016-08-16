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

class ShipItRepoHGException extends ShipItRepoException {}

/**
 * HG specialization of ShipItRepo
 */
class ShipItRepoHG extends ShipItRepo implements ShipItSourceRepo {
  private ?string $branch;

  public function __construct(
    string $path,
    string $branch,
  ): void {
    parent::__construct($path, $branch);

    try {
      // $this->path will be set by here as it is the first thing to
      // set on the constructor call. So it can be used in hgCommand, etc.
      $hg_root = trim($this->hgCommand('root'));
    } catch (ShipItRepoException $ex) {
      throw new ShipItRepoHGException($this, "{$this->path} is not a HG repo");
    }
  }

  <<__Override>>
  public function setBranch(string $branch): bool {
    $this->branch = $branch;
    return true;
  }

  public function findNextCommit(
    string $revision,
    ImmSet<string> $roots,
  ): ?string {
    if (!$this->branch) {
      throw new ShipItRepoHGException($this, "setBranch must be called first.");
    }
    $branch = $this->branch;
    $log = $this->hgCommand(
      'log',
      '--limit',
      '1',
      '-r',
      "({$revision}::{$branch}) - {$revision}",
       '--template',
       '{node}\\n',
       ...$roots,
    );
    $log = trim($log);
    if ($log === '') {
      return null;
    }
    if (strlen($log) != 40) {
      throw new ShipItRepoHGException($this, "{$log} doesn't look like a valid".
                                            " hg changeset id");
    }
    return $log;
  }

  /*
   * Generator yielding patch sections starting with header,
   * then each of the diff blocks (individually)
   * and finally the footer
   */
  protected static function ParseHgRegions(string $patch) {
    $contents = '';
    foreach(explode("\n", $patch) as $line) {
      $line = preg_replace('/(\r\n|\n)/', "\n", $line);

      if (
        preg_match(
          '@^diff --git( ([ab]/(.*?)|/dev/null)){2}@',
          rtrim($line),
        )
      ) {
        yield $contents;
        $contents = '';
      }
      $contents .= $line . "\n";
    }
    if ($contents !== '') {
      yield $contents;
    }
  }

  private static function parseHeader(string $header): ShipItChangeset {
    $changeset = new ShipItChangeset();

    $subject = null;
    $message = '';
    foreach (explode("\n", $header) as $line) {
      if (strlen($line) == 0) {
        $message .= "\n";
        continue;
      }
      if ($line[0] === '#') {
        if (!strncasecmp($line, '# User ', 7)) {
          $changeset = $changeset->withAuthor(substr($line, 7));
        } else if (!strncasecmp($line, '# Date ', 7)) {
          $changeset = $changeset->withTimestamp((int)substr($line, 7));
        }
        // Ignore anything else in the envelope
        continue;
      }
      if ($subject === null) {
        $subject = $line;
        continue;
      }
      $message .= "{$line}\n";
    }

    return $changeset
      ->withSubject((string) $subject)
      ->withMessage(trim($message));
  }

  public function getNativePatchFromID(string $revision): string {
    return $this->hgCommand(
      'export',
      '--git',
      '-r', $revision,
      '--encoding', 'UTF-8',
    );
  }

  public function getChangesetFromID(string $revision): ?ShipItChangeset {
    $patch = $this->getNativePatchFromID($revision);
    $changeset = $this->getChangesetFromNativePatch($revision, $patch);
    return $changeset;
  }

  private function getChangesetFromNativePatch(
    string $revision,
    string $patch,
  ): ?ShipItChangeset {
    $changeset = self::getChangesetFromExportedPatch($patch);
    if ($changeset === null) {
      return $changeset;
    }

    // we need to have plain diffs for each file, and rename/copy from
    // breaks this, and we can't turn it off in hg.
    //
    // for example, if the change to 'proprietary/foo.cpp' is removed,
    // but 'public/foo.cpp' is not, this breaks:
    //
    //   rename from proprietary/foo.cpp to public/foo.cpp
    //
    // If we have any matching files, re-create their diffs using git, which
    // will do full diffs for both sides of the copy/rename.
    $matches = [];
    preg_match_all(
      '/^(?:rename|copy) (?:from|to) (?<files>.+)$/m',
      $patch,
      $matches,
      PREG_PATTERN_ORDER,
    );
    $has_rename_or_copy = new ImmSet($matches['files']);
    $has_mode_change = $changeset
      ->getDiffs()
      ->filter($diff ==> preg_match('/^old mode/m', $diff['body']) === 1)
      ->map($diff ==> $diff['path'])
      ->toImmSet();

    $needs_git = $has_rename_or_copy
      ->concat($has_mode_change)
      ->toImmSet();

    if ($needs_git) {
      $diffs = $changeset
        ->getDiffs()
        ->filter($diff ==> !$needs_git->contains($diff['path']))
        ->toVector();
      $diffs->addAll($this->makeDiffsUsingGit(
        $revision,
        $needs_git,
      ));
      $changeset = $changeset->withDiffs($diffs->toImmVector());
    }

    return $changeset->withID($revision);
  }

  public static function getChangesetFromExportedPatch(
    string $patch,
  ): ?ShipItChangeset {
    $changeset = null;
    $diffs = Vector { };

    foreach(self::ParseHgRegions($patch) as $region) {
      if ($changeset === null) {
        $changeset = self::parseHeader($region);
        continue;
      }
      list($header, $body) = explode("\n", $region, 2);
      $path = substr(array_pop(explode(' ', $header)), 2);
      $diffs[] = shape(
        'path' => $path,
        'body' => $body,
      );
    }

    if ($changeset === null) {
      return $changeset;
    }
    return $changeset->withDiffs($diffs->toImmVector());
  }
  protected function hgPipeCommand(?string $stdin, ...$args): string {
    $command = (new ShipItShellCommand($this->path, 'hg', ...$args))
      ->setEnvironmentVariables(ImmMap {
        'HGPLAIN' => '1',
      })
      ->setRetries(1);
    if ($stdin) {
      $command->setStdIn($stdin);
    }
    return $command->runSynchronously()->getStdOut();
  }

  protected function hgCommand(...$args): string {
    return $this->hgPipeCommand(null, ...$args);
  }

  <<__Override>>
  public function pull(): void {
    $lock = $this->getSharedLock()->getExclusive();

    if (ShipItRepo::$VERBOSE & ShipItRepo::VERBOSE_FETCH) {
      fwrite(STDERR, "** Updating checkout in {$this->path}\n");
    }
    $this->hgCommand('pull');
  }

  private function makeDiffsUsingGit(
    string $rev,
    ImmSet<string> $files,
  ): ImmVector<ShipItDiff> {
    $tempdir = new ShipItTempDir('git-wd');
    $path = $tempdir->getPath();

    $this->checkoutFilesAtRevToPath($files, $rev.'^', $path.'/a');
    $this->checkoutFilesAtRevToPath($files, $rev, $path.'/b');

    $result = (new ShipItShellCommand(
      $path,
        'git', 'diff',
        '--binary',
        '--no-prefix',
        '--no-renames',
        'a',
        'b',
      ))->setNoExceptions()->runSynchronously();

    invariant(
      $result->getExitCode() === 1,
      'git diff exited with %d, which means no changes; expected 1, '.
      'which means non-empty diff.',
      $result->getExitCode(),
    );
    $patch = $result->getStdOut();

    $ret = Vector { };
    foreach (
      ShipItUtil::parsePatchWithoutHeader($patch) as $region
    ) {
      list($path, $body) = ShipItUtil::parseDiffRegion($region);
      $ret[] = shape(
        'path' => $path,
        'body' => $body,
      );
    }
    return $ret->toImmVector();
  }

  private function checkoutFilesAtRevToPath(
    ImmSet<string> $files,
    string $rev,
    string $path,
  ): void {
    /* Use a list of patterns from a file (/dev/stdin) instead
     * of specifying on the command line - otherwise, we can
     * generate a command that is larger than the maximum length
     * allowed by the system, so, exec() won't actually execute.
     *
     * Example diff:
     *   rFBSed54f611dc0aebe17010b3416e64549d95ee3a49
     *   ... which is https://github.com/facebook/nuclide/commit/2057807d2653dd1af359f44f658eadac6eaae34b
     */
    $patterns = $files->map(
      $file ==> 'path:'.$file,
    );
    $patterns = implode("\n", $patterns);

    // Prefetch is needed for reasonable performance with the remote file
    // log extension
    $lock = $this->getSharedLock()->getExclusive();
    try {
      $this->hgPipeCommand(
        $patterns,
        'prefetch',
        '-r', $rev,
        'listfile:/dev/stdin',
      );
    } catch (ShipItShellCommandException $e) {
      // ignore, not all repos are shallow
    }
    $lock->release();

    $this->hgPipeCommand(
      $patterns,
      'archive',
      '-r', $rev,
      '-I', 'listfile:/dev/stdin',
      $path,
    );
  }

  public function export(
    ImmSet<string> $roots,
    ?string $rev = null,
  ): shape('tempDir' => ShipItTempDir, 'revision' => string) {
    if ($rev === null) {
      $rev = $this->hgCommand(
        'log',
        '-r',
        $this->branch,
        '-T',
        '{node}'
      );
    }

    $temp_dir = new ShipItTempDir('hg-export');
    $this->checkoutFilesAtRevToPath(
      $roots,
      $rev,
      $temp_dir->getPath(),
    );

    return shape('tempDir' => $temp_dir, 'revision' => $rev);
  }
}
