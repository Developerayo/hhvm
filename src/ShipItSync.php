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

class ShipItException extends \Exception {}

class ShipItSync {
  public function __construct(
    private ShipItBaseConfig $baseConfig,
    private ShipItSyncConfig $syncConfig,
  ) {
  }

  private function getFirstSourceID(): ?string {
    $config = $this->syncConfig;
    $rev = $config->getFirstCommit();
    if ($rev === null) {
      $src = $this->getRepo(ShipItSourceRepo::class);

      $rev = $src->findNextCommit(
        $this->findLastSyncedCommit(),
        $config->getSourceRoots(),
      );
    }
    return $rev;
  }

  private function getSourceChangesets(): ImmVector<ShipItChangeset> {
    $config = $this->syncConfig;
    $src = $this->getRepo(ShipItSourceRepo::class);

    $changesets = Vector { };
    $rev = $this->getFirstSourceID();
    while ($rev !== null) {
      $changeset = $src->getChangesetFromID($rev);

      if (!$changeset) {
        throw new ShipItException("Unable to get patch for $rev");
      }

      $changesets[] = $changeset;
      $rev = $src->findNextCommit($rev, $config->getSourceRoots());
    }
    return $changesets->toImmVector();
  }

  private function getFilteredChangesets(
  ): ImmVector<ShipItChangeset> {
    $base_config = $this->baseConfig;
    $skipped_ids = $this->syncConfig->getSkippedSourceCommits();
    $filter = $this->syncConfig->getFilter();

    $changesets = Vector { };
    foreach ($this->getSourceChangesets() as $changeset) {
      $skip_match = null;
      foreach ($skipped_ids as $skip_id) {
        if (strpos($changeset->getID(), $skip_id) === 0) {
          $skip_match = $skip_id;
          break;
        }
      }
      if ($skip_match !== null) {
        $changesets[] = $changeset
          ->withDiffs(ImmVector { })
          ->withDebugMessage(
            'USER SKIPPED COMMIT: id "%s" matches "%s"',
            $changeset->getID(),
            $skip_match,
          );
        continue;
      }

      $changeset = $filter($base_config, $changeset);
      if ($changeset->getDiffs()->isEmpty()) {
        $changesets[] = $changeset->withDebugMessage(
          'SKIPPED COMMIT: no matching files',
        );
      } else {
        $changesets[] = self::addTrackingData($changeset);
      }
    }
    return $changesets->toImmVector();
  }

  public function run(): void {
    $changesets = $this->getFilteredChangesets();
    if ($changesets->isEmpty()) {
      print("  No new commits to sync.\n");
      return;
    }

    $patches_dir = $this->syncConfig->getPatchesDirectory();
    if ($patches_dir !== null) {
      mkdir($patches_dir, 0755, /* recursive = */ true);
    }

    $verbose = $this->baseConfig->isVerboseEnabled();
    $dest = $this->getRepo(ShipItDestinationRepo::class);
    foreach ($changesets as $changeset) {
      if ($patches_dir !== null) {
        $file = $patches_dir.'/'.$changeset->getID().'.patch';
        file_put_contents($file, $dest->renderPatch($changeset));
        $changeset = $changeset->withDebugMessage(
          'Saved patch file: %s',
          $file,
        );
      }

      if ($verbose) {
        $changeset->dumpDebugMessages();
      }

      if (!$changeset->isValid()) {
        printf(
          "  SKIP %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        continue;
      }

      try {
        $dest->commitPatch($changeset);
        printf(
          "  OK %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        continue;
      } catch (ShipItRepoException $e) {
        fprintf(
          STDERR,
          "Failed to apply patch %s (%s): %s\n",
          $changeset->getID(),
          $changeset->getMessage(),
          $e->getMessage(),
        );
        throw $e;
      }
    }
  }

  private static function checkLastRev(?string $diff): string {
    if ($diff === null) {
      throw new ShipItException(
        "Unable to determine last differential revision pushed to dest repo"
      );
    }
    if (!preg_match('/^D[0-9]{6,}$/', $diff)) {
      throw new ShipItException(
        "Last differential revision number ('{$diff}') is invalid"
      );
    }
    return $diff;
  }

  private static function checkFindDiff(?string $id, string $diff): string {
    if ($id === null) {
      throw new ShipItException("Unable to find $diff in source repo");
    }
    return $id;
  }

  <<__Memoize>>
  private function getRepo<Trepo as ShipItRepo>(
    classname<Trepo> $class,
  ): Trepo {
    $config = $this->baseConfig;

    if ($class === ShipItSourceRepo::class) {
      return ShipItRepo::typedOpen(
        $class,
        $config->getSourcePath(),
        $config->getSourceBranch(),
      );
    }

    if ($class === ShipItDestinationRepo::class) {
      return ShipItRepo::typedOpen(
        $class,
        $config->getDestinationPath(),
        $config->getDestinationBranch(),
      );
    }

    invariant_violation(
      'Got class %s, expected %s or %s',
      $class,
      ShipItSourceRepo::class,
      ShipItDestinationRepo::class,
    );
  }

  private function findLastSyncedCommit(
  ): string {
    $dest = $this->getRepo(ShipItDestinationRepo::class);

    $src_commit = $dest->findLastSourceCommit(
      $this->syncConfig->getDestinationRoots(),
    );
    if ($src_commit === null) {
      throw new ShipItException("Couldn't find synced commit id");
    }
    return $src_commit;
  }

  public static function addTrackingData(
    ShipItChangeset $changeset,
    ?string $rev = null,
  ): ShipItChangeset {
    if ($rev === null) {
      $rev = $changeset->getID();
    }
    $new_message = $changeset->getMessage()."\n\n".
      'fb-gh-sync-id: '.$rev."\n".
      'fbshipit-source-id: '.$rev;
    return $changeset->withMessage(trim($new_message));
  }
}
