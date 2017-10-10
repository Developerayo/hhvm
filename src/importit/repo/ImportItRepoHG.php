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

/**
 * Specialization of ShipItRepoHG
 */
class ImportItRepoHG extends \Facebook\ShipIt\ShipItRepoHG {
    <<__Override>>
    public function setBranch(string $branch): bool {
      // This class creates a bookmark and uses it to keep track of where it is.
      // So if $branch is `master`, that can cause trouble. Easiest solution is
      // to alphavary the name.
      return parent::setBranch($branch . "_importit");
    }
}
