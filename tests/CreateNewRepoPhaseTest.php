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

final class CreateNewRepoPhaseTest extends BaseTest {
  public function testCommitter(): void {
    $getCommitter = ($config) ==> {
      return shape('name' => 'Foo', 'email' => 'bar@example.com');
    };
    $config = new ShipItBaseConfig('', '', '');
    $phase = new ShipItCreateNewRepoPhase(
      ImmSet {},
      $changeset ==> $changeset,
      $getCommitter
    );
    $committer = $phase->getCommitter($config);
    $this->assertEquals('Foo', $committer['name']);
    $this->assertEquals('bar@example.com', $committer['email']);
  }
}
