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

// Not an interface: https://github.com/facebook/hhvm/issues/6820
abstract class ShipItUserInfo {
  // eg convert a local unix account name to a github account name
  abstract public static function getDestinationUserFromLocalUser(
    string $local_user,
  ): Awaitable<?string>;

  // eg convert a local unix account name to "Foo Bar <foobar@example.com>"
  abstract public static function getDestinationAuthorFromLocalUser(
    string $local_user,
  ): Awaitable<?string>;
}
