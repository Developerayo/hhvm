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

abstract final class ImportItPathFilters {
  /**
   * Change directory paths in a diff using a mapping.  This is a convenience
   * method that takes ShipIt path mappings and converts them into something
   * useable for ImportIt.
   *
   * @param $mapping a map from directory paths in the destination repository to
   *   paths in the source repository. The last matching mapping is used.
   * @param $skip_patterns a set of patterns of paths that shouldn't be touched.
   */
  public static function moveDirectories(
    \Facebook\ShipIt\ShipItChangeset $changeset,
    ImmMap<string, string> $shipit_mapping,
    ImmVector<string> $skip_patterns = ImmVector {},
  ): \Facebook\ShipIt\ShipItChangeset {
    $ordered_mapping = Vector {};
    foreach ($shipit_mapping as $dest_path => $src_path) {
      $ordered_mapping->add(tuple($src_path, $dest_path));
    }
    $mapping = Map {};
    for ($i = $ordered_mapping->count() - 1; $i >= 0; $i--) {
      list($src_path, $dest_path) = $ordered_mapping[$i];
      invariant(
        !$mapping->containsKey($src_path),
        'Mulitiple paths map from "%s" ("%s" and "%s")!',
        $src_path,
        $dest_path,
        $mapping[$src_path],
      );
      $mapping[$src_path] = $dest_path;
    }
    return \Facebook\ShipIt\ShipItPathFilters::moveDirectories(
      $changeset,
      $mapping->toImmMap(),
      $skip_patterns,
    );
  }
}
