<?hh // strict

namespace Facebook\ShipIt;

final class ShipItCreateNewRepoPhase extends ShipItPhase {
  public function __construct(
    private ImmSet<string> $roots,
    private (function(ShipItChangeset):ShipItChangeset) $filter,
  ) {
    $this->skip();
  }

  public function getReadableName(): string {
    return 'Create a new git repo with an initial commit';
  }

  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'special-create-new-repo',
        'description' =>
          'Create a new git repository with a single commit, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
    };
  }

  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $source = ShipItRepo::typedOpen(
      ShipItRepoHG::class,
      $config->getSourcePath(),
      $config->getSourceBranch(),
    );

    print("  Exporting...\n");
    $export = $source->export($this->roots);
    $export_dir = $export['tempDir'];
    $rev = $export['revision'];

    print("  Creating unfiltered commit...\n");

    self::execSteps(
      $export_dir->getPath(),
      ImmVector {
        ImmVector { 'git', 'init' },
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
    $filter = $this->filter;
    $changeset = $filter($changeset)->withSubject('Initial commit');
    $changeset = ShipItSync::addTrackingData($changeset, $rev);

    if ($config->isVerboseEnabled()) {
      $changeset->dumpDebugMessages();
    }

    print("  Creating new repo...\n");

    $filtered_dir = new ShipItTempDir('git-with-initial-commit');
    $filtered_dir->keep();
    self::execSteps(
      $filtered_dir->getPath(),
      ImmVector { ImmVector { 'git', 'init' } },
    );
    $filtered_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $filtered_dir->getPath(),
      '--orphan=master',
    );
    $filtered_repo->commitPatch($changeset);
    print('  New repository created at '.$filtered_dir->getPath()."\n");
    exit(0);
  }

  private static function execSteps(
    string $path,
    ImmVector<ImmVector<string>> $steps,
  ): void {
    foreach ($steps as $step) {
      ShipItUtil::shellExec(
        $path,
        /* stdin = */ null,
        ShipItUtil::DONT_VERBOSE,
        ...$step,
      );
    }
  }
}
