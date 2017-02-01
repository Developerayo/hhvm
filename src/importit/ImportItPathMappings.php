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
 * An interface a ShipIt CLI class would want to implement if it uses ImportIt
 * to pull changes into an internal repository.
 */
interface ImportItPathMappings {
  /**
   * A map from directory paths in the source repository to paths in the
   * destination repository. The first matching mapping is used.
   */
  public static function getPathMappings(): ImmMap<string, string>;
}
