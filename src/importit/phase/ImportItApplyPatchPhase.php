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
  ShipItChangeset,
  ShipItRepoSide
};

final class ImportItApplyPatchPhase extends ImportItPhase {

  private ?string $patchFile;

  <<__Override>>
  public function __construct(
    (function(): ImportItRepoGIT) $repoGetter,
    private ShipItRepoSide $side,
    private ?(function(ShipItChangeset): ShipItChangeset) $filter = null,
  ) {
    parent::__construct($repoGetter);
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Apply patch to '.$this->side;
  }

  <<__Override>>
  final public function getCLIArguments(
  ): ImmVector<\Facebook\ShipIt\ShipItCLIArgument> {
    if ($this->side == ShipItRepoSide::SOURCE) {
      return ImmVector {
        shape(
          'long_name' => 'patch-file::',
          'description' => 'The patch file to apply',
          'write' => $v ==> $this->patchFile = $v,
        ),
      };
    }
    return ImmVector {};
  }

  <<__Override>>
  final protected function runImpl(
    \Facebook\ShipIt\ShipItBaseConfig $config,
  ): void {
    switch ($this->side) {
      case ShipItRepoSide::SOURCE:
        $patch_file = $this->patchFile;
        invariant(
          $patch_file !== null,
          '--patch-file must be set!',
        );
        printf("  Importing...\n",);
        $this->getSourceRepo()->importPatch($patch_file);
        break;
      case ShipItRepoSide::DESTINATION:
        $filter_fn = $this->filter;
        invariant(
          $filter_fn !== null,
          'No filter function provided!',
        );
        $changeset = $this->getSourceRepo()->getChangesetFromID('HEAD');
        invariant(
          $changeset !== null,
          'No changset found in source repo!',
        );
        $repo = \Facebook\ShipIt\ShipItRepo::open(
          $config->getDestinationPath(),
          $config->getDestinationBranch(),
        );
        invariant(
          $repo instanceof \Facebook\ShipIt\ShipItDestinationRepo,
          'The destination repository must implement ShipItDestinationRepo!',
        );
        printf("  Filtering...\n",);
        $changset = $filter_fn($changeset);
        printf("  Exporting...\n",);
        $repo->commitPatch($filter_fn($changeset));
        printf(
          "  Done.  Patched repository at %s\n",
          $repo->getPath(),
        );
        break;
    }
  }
}
