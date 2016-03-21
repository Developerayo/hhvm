<?hh // strict

namespace Facebook\ShipIt;

final class ShipItVerifyRepoPhase extends ShipItPhase {
  private bool $createPatch = false;

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
      shape(
        'long_name' => 'create-fixup-patch',
        'description' =>
          'Create a patch to get the destination repository in sync, then exit',
        'write' => $_ ==> { $this->unskip(); $this->createPatch = true; }
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
    $dirty_remote = 'shipit_dest';
    $dirty_ref = $dirty_remote.'/'.$config->getDestinationBranch();

    ShipItUtil::shellExec(
      $clean_path,
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'remote',
      'add',
      $dirty_remote,
      $config->getDestinationPath(),
    );
    ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'fetch',
      $dirty_remote,
    );

    $diffstat = rtrim(ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'diff',
      '--stat',
      'HEAD',
      $dirty_ref,
    ));

    if ($diffstat === '') {
      if ($this->createPatch) {
        fwrite(
          STDERR,
          "  CREATE PATCH FAILED: destination is already in sync.\n",
        );
        exit(1);
      }
      printf("  Verification OK: destination is in sync.\n");
      exit(0);
    }

    if (!$this->createPatch) {
      fprintf(
        STDERR,
        "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
        $diffstat,
      );
      exit(1);
    }

    $diff = ShipItUtil::shellExec(
      $clean_path,
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'diff',
      $dirty_ref,
      'HEAD',
    );

    $patch_file = tempnam(sys_get_temp_dir(), 'shipit-resync-patch-');
    file_put_contents($patch_file, $diff);

    printf(
      "  Created patch file: %s\n\n".
      "%s\n\n".
      "  To apply:\n\n".
      "    $ cd %s\n".
      "    $ git apply < %s\n".
      "    $ git status\n".
      "    $ git add --all --patch\n".
      "    $ git push\n\n".
      "  WARNING: there are 4 possible causes for differences:\n\n".
      "    1. changes in source haven't been copied to destination\n".
      "    2. changes were made to destination that aren't in source\n".
      "    3. the filter function has a bug\n".
      "    4. FBShipIt has a bug\n\n".
      "  APPLYING THE PATCH IS ONLY CORRECT FOR THE FIRST SITUATION; review\n".
      "  the changes carefully.\n\n",
      $patch_file,
      $diffstat,
      $config->getDestinationPath(),
      $patch_file,
    );
    exit(0);
  }
}
