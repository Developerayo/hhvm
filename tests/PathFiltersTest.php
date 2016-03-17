<?hh
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class PathFiltersTest extends BaseTest {
  public function stripPathsTestData(
  ): array<string, (
    ImmVector<string>, // $patterns,
    ImmVector<string>, // $exceptions,
    ImmSet<string>, // $expected_files,
  )> {
    return [
      'No change' => tuple(
        ImmVector {},
        ImmVector {},
        ImmSet { 'foo', 'bar', 'herp/derp', 'derp' },
      ),
      'Remove top level file' => tuple(
        ImmVector { '@^bar$@' },
        ImmVector {},
        ImmSet { 'foo', 'herp/derp', 'derp' },
      ),
      'Remove directory ' => tuple(
        ImmVector { '@^herp/@' },
        ImmVector { },
        ImmSet { 'foo', 'bar', 'derp' },
      ),
      'Remove file' => tuple (
        ImmVector { '@(^|/)derp(/|$)@' },
        ImmVector { },
        ImmSet { 'foo', 'bar' },
      ),
      'Remove file, except if parent directory has specific name' => tuple(
        ImmVector { '@(^|/)derp(/|$)@' },
        ImmVector { '@(^|/)herp/derp$@' },
        ImmSet { 'foo', 'bar', 'herp/derp' },
      ),
      'Multiple patterns' => tuple(
        ImmVector { '@^foo$@', '@^bar$@' },
        ImmVector {},
        ImmSet { 'herp/derp', 'derp' },
      ),
      'Multiple exceptions' => tuple(
        ImmVector { '@@' },
        ImmVector { '@foo@', '@bar@' },
        ImmSet { 'foo', 'bar' },
      ),
    ];
  }

  /**
   * @dataProvider stripPathsTestData
   */
  public function testStripPaths(
    ImmVector<string> $patterns,
    ImmVector<string> $exceptions,
    ImmSet<string> $expected_files,
  ): void {
    $changeset = (new ShipItChangeset())->withDiffs(
      self::diffsFromMap(ImmMap {
        'foo' => 'placeholder',
        'bar' => 'placeholder',
        'herp/derp' => 'placeholder',
        'derp' => 'placeholder',
      }),
    );

    $changeset = ShipItPathFilters::stripPaths(
      $changeset,
      $patterns,
      $exceptions,
    );

    $this->assertEquals(
      $expected_files,
      $changeset->getDiffs()->map($diff ==> $diff['path'])->toImmSet(),
    );
  }
}
