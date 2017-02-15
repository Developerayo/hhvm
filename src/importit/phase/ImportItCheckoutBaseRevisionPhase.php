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

final class ImportItCheckoutBaseRevisionPhase extends ImportItPhase {

  private ?string $baseId;

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Checkout base';
  }

  <<__Override>>
  final public function getCLIArguments(
  ): ImmVector<\Facebook\ShipIt\ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-checkout-base',
        'description' => 'Skip checking out the base revision of the patch',
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => 'source-id-base::',
        'description' => 'The id the patch is based on',
        'write' => $v ==> $this->baseId = $v,
      ),
    };
  }

  <<__Override>>
  final protected function runImpl(
    \Facebook\ShipIt\ShipItBaseConfig $config,
  ): void {
    $revision = $this->baseId;
    invariant(
      $revision !== null,
      '--source-id-base or --skip-checkout-base must be set!',
    );
    $this->getSourceRepo($config)->checkoutNewBranchAt(
      'importit-applied-patch',
      $revision,
    );
  }
}
