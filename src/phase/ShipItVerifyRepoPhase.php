<?hh // strict

namespace Facebook\ShipIt;

final class ShipItVerifyRepoPhase extends ShipItPhase {
  public function __construct(
    private ImmSet<string> $roots,
    private (function(ShipItChangeset):ShipItChangeset) $filter,
  ) {
    $this->skip();
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Verify that destination repository is sync';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'verify',
        'description' =>
          'Verify that the destination repository is in sync, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
    };
  }

  <<__Override>>
  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $clean_dir = ShipItCreateNewRepoPhase::createNewGitRepo(
      $config,
      $this->roots,
      $this->filter,
    );
    $clean_path = $clean_dir->getPath();

    ShipItUtil::shellExec(
      $clean_path,
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'remote',
      'add',
      'shipit_dest',
      $config->getDestinationPath(),
    );
    ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'fetch',
      'shipit_dest',
    );

    $diffstat = rtrim(ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'diff',
      '--stat',
      'HEAD',
      'shipit_dest/'.$config->getDestinationBranch(),
    ));

    if ($diffstat === '') {
      printf("  Verification OK: destination is in sync.\n");
      exit(0);
    }

    fprintf(
      STDERR,
      "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
      $diffstat,
    );
    exit(1);
  }
}
