<?hh // strict
/**
 * Copyright (c) 2017-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ImportIt;

use \Facebook\ShipIt\ {
  ShipItBaseConfig,
  ShipItChangeset,
  ShipItDestinationRepo,
  ShipItRepo
};

final class ImportItSyncPhase extends \Facebook\ShipIt\ShipItPhase {

  private ?string $expectedHeadRev;
  private ?string $patchesDirectory;
  private ?string $pullRequestNumber;

  public function __construct(
    private (function(ShipItChangeset): ShipItChangeset) $filter,
  ) {
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Import Commits';
  }

  <<__Override>>
  final public function getCLIArguments(
  ): ImmVector<\Facebook\ShipIt\ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'expected-head-revision::',
        'description' => 'The expected revision at the HEAD of the PR',
        'write' => $x ==> $this->expectedHeadRev = $x,
      ),
      shape(
        'long_name' => 'pull-request-number::',
        'description' => 'The number of the Pull Request to import',
        'write' => $x ==> $this->pullRequestNumber = $x,
      ),
      shape(
        'long_name' => 'save-patches-to::',
        'description' => 'Directory to copy created patches to. Useful for '.
                         'debugging',
        'write' => $x ==> $this->patchesDirectory = $x,
      ),
    };
  }

  <<__Override>>
  final protected function runImpl(
    ShipItBaseConfig $config,
  ): void {
    list($changeset, $destination_base_rev) =
      $this->getSourceChangsetAndDestinationBaseRevision($config);
    $this->applyPatchToDestination($config, $changeset, $destination_base_rev);
  }

  private function getSourceChangsetAndDestinationBaseRevision(
    ShipItBaseConfig $config,
  ): (ShipItChangeset, ?string) {
    $pr_number = $this->pullRequestNumber;
    $expected_head_rev = $this->expectedHeadRev;
    invariant(
      $pr_number !== null && $expected_head_rev !== null,
      '--pull-request-number and --expected-head-revision must be set!',
    );
    $source_repo = new ImportItRepoGIT(
      $config->getSourcePath(),
      $config->getSourceBranch(),
    );
    return $source_repo->getChangesetAndBaseRevisionForPullRequest(
      $pr_number,
      $expected_head_rev,
      $config->getSourceBranch(),
    );
  }

  private function applyPatchToDestination(
    ShipItBaseConfig $config,
    ShipItChangeset $changeset,
    ?string $base_rev,
  ): void {
    $destination_repo = ShipItRepo::open(
      $config->getDestinationPath(),
      $config->getDestinationBranch(),
    );
    if ($base_rev !== null) {
      printf("  Updating destination branch to new base revision...\n");
      $destination_repo->updateBranchTo($base_rev);
    }
    invariant(
      $destination_repo instanceof ShipItDestinationRepo,
      'The destination repository must implement ShipItDestinationRepo!',
    );
    printf("  Filtering...\n",);
    $filter_fn = $this->filter;
    $changeset = $filter_fn($changeset);
    printf("  Exporting...\n",);
    $this->maybeSavePatch($destination_repo, $changeset);
    try {
      $rev = $destination_repo->commitPatch($changeset);
      printf(
        "  Done.  %s committed in %s\n",
        $rev,
        $destination_repo->getPath(),
      );
    } catch (\Exception $e) {
      if ($this->patchesDirectory !== null) {
        printf(
          "  Failure to apply patch at %s\n",
          $this->getPatchLocationForChangeset($changeset),
        );
      } else {
        printf(
          "  Failure to apply patch:\n%s\n",
          $destination_repo->renderPatch($changeset),
        );
      }
      throw $e;
    }
  }

  private function maybeSavePatch(
    ShipItDestinationRepo $destination_repo,
    ShipItChangeset $changeset,
  ): void {
    if ($this->patchesDirectory === null) {
      return;
    }
    if (!file_exists($this->patchesDirectory)) {
      mkdir($this->patchesDirectory, 0755, /* recursive = */ true);
    } elseif (!is_dir($this->patchesDirectory)) {
      fprintf(
        STDERR,
        "Cannot log to %s: the path exists and is not a directory.\n",
        $this->patchesDirectory,
      );
      return;
    }
    $file = $this->getPatchLocationForChangeset($changeset);
    file_put_contents($file, $destination_repo->renderPatch($changeset));
    $changeset = $changeset->withDebugMessage(
      'Saved patch file: %s',
      $file,
    );
  }

  private function getPatchLocationForChangeset(
    ShipItChangeset $changeset,
  ): string {
    return $this->patchesDirectory.'/'.$changeset->getID().'.patch';
  }
}
