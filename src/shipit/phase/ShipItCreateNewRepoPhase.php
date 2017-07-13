<?hh // strict

namespace Facebook\ShipIt;

final class ShipItCreateNewRepoPhase extends ShipItPhase {
  private ?string $sourceCommit = null;
  private ?string $outputPath = null;

  public function __construct(
    private (function(ShipItChangeset):ShipItChangeset) $filter,
    private shape('name' => string, 'email' => string) $committer,
  ) {
    $this->skip();
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Create a new git repo with an initial commit';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'create-new-repo',
        'description' =>
          'Create a new git repository with a single commit, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
      shape(
        'long_name' => 'create-new-repo-from-commit::',
        'description' =>
          'Like --create-new-repo, but at a specified source commit',
        'write' => $rev ==> {
            $this->sourceCommit = $rev;
            $this->unskip();
        },
      ),
      shape(
        'long_name' => 'create-new-repo-output-path::',
        'description' =>
          'When using --create-new-repo or --create-new-repo-from-commit, '.
          'create the new repository in this directory',
        'write' => $path ==> $this->outputPath = $path,
      ),
      shape( // deprecated, renamed for consistency with verify
        'long_name' => 'special-create-new-repo',
        'replacement' => 'create-new-repo',
      ),
    };
  }

  <<__Override>>
  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $output = $this->outputPath;
    if ($output !== null && file_exists($output)) {
      fwrite(STDERR, "  Path '%s' already exists\n", $output);
      exit(1);
    }

    $temp_dir = self::createNewGitRepo(
      $config,
      $this->filter,
      $this->committer,
      $this->sourceCommit,
    );
    // We're either keeping it, or renaming it; either way, we don't want it
    // automatically deleted on exit
    $temp_dir->keep();

    if ($output === null) {
      $output = $temp_dir->getPath();
    } else {
      $parent = dirname($output);
      if (!file_exists($parent)) {
        mkdir($parent, 0755, /* recursive = */ true);
      }
      rename($temp_dir->getPath(), $output);
    }

    print('  New repository created at '.$output."\n");
    exit(0);
  }

  private static function initGitRepo(
    ShipItTempDir $temp_dir,
    shape('name' => string, 'email' => string) $committer,
  ): void {
    self::execSteps(
      $temp_dir->getPath(),
      ImmVector {
        ImmVector { 'git', 'init' },
        ImmVector { 'git', 'config', 'user.name', $committer['name'] },
        ImmVector { 'git', 'config', 'user.email', $committer['email'] },
      },
    );
  }

  public static function createNewGitRepo(
    ShipItBaseConfig $config,
    (function(ShipItChangeset):ShipItChangeset) $filter,
    shape('name' => string, 'email' => string) $committer,
    ?string $revision = null,
  ): ShipItTempDir {
    $source = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $config->getSourcePath(),
      $config->getSourceBranch(),
    );

    print("  Exporting...\n");
    $export = $source->export($config->getSourceRoots(), $revision);
    $export_dir = $export['tempDir'];
    $rev = $export['revision'];

    print("  Creating unfiltered commit...\n");

    self::initGitRepo($export_dir, $committer);
    self::execSteps(
      $export_dir->getPath(),
      ImmVector {
        ImmVector { 'git', 'add', '.' },
        ImmVector {
          'git',
          'commit',
          '-m',
          'initial unfiltered commit',
        },
      },
    );

    print("  Filtering...\n");
    $exported_repo = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $export_dir->getPath(),
      'master',
    );
    $changeset = $exported_repo->getChangesetFromID('HEAD');
    invariant($changeset !== null, 'got a null changeset :/');
    $changeset = $changeset->withID($rev);
    $changeset = $filter($changeset)->withSubject('Initial commit');
    $changeset = ShipItSync::addTrackingData($changeset, $rev);

    if ($config->isVerboseEnabled()) {
      $changeset->dumpDebugMessages();
    }

    print("  Creating new repo...\n");

    $filtered_dir = new ShipItTempDir('git-with-initial-commit');
    self::initGitRepo($filtered_dir, $committer);
    $filtered_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $filtered_dir->getPath(),
      '--orphan='.$config->getDestinationBranch(),
    );
    $filtered_repo->commitPatch($changeset);

    print("  Cleaning up...\n");
    // As we're done with these and nothing else has the random paths, the lock
    // files aren't needed
    foreach ([$export_dir, $filtered_dir] as $repo) {
      $lock_file = ShipItRepo::getLockFilePathForRepoPath($repo->getPath());
      if (file_exists($lock_file)) {
        unlink($lock_file);
      }
    }

    return $filtered_dir;
  }

  private static function execSteps(
    string $path,
    ImmVector<ImmVector<string>> $steps,
  ): void {
    foreach ($steps as $step) {
/* HH_FIXME[4128] Use ShipItShellCommand */
      ShipItUtil::shellExec(
        $path,
        /* stdin = */ null,
        ShipItUtil::DONT_VERBOSE,
        ...$step,
      );
    }
  }
}
