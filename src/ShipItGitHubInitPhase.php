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

final class ShipItGitHubInitPhase extends ShipItPhase {

  public function __construct(
    private string $organization,
    private string $project,
    private ShipItRepoSide $side,
    private classname<ShipItGitHubUtils> $github_utils,
  ): void {
  }

  public function getReadableName(): string {
    return 'Initialize '.$this->side.' GitHub repository';
  }

  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-'.$this->side.'-init',
        'description' => "Don't initialize the GitHub checkout",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => $this->side.'-github-org::',
        'description' => 'GitHub Organization ['.$this->organization.']',
        'write' => $v ==> $this->organization = $v,
      ),
      shape(
        'long_name' => $this->side.'-github-project::',
        'description' => 'GitHub Project ['.$this->project.']',
        'write' => $v ==> $this->project = $v,
      ),
    };
  }

  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $class = $this->github_utils;
    $local_path =
      $this->side === ShipItRepoSide::SOURCE
      ? $config->getSourcePath()
      : $config->getDestinationPath();

    $class::initializeRepo($this->organization, $this->project, $local_path);
  }

  // the 0 argument is run-time log rate. Users can't do anything about this,
  // so suppress all log messages about it.
  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__setOrganization(string $s): void {
    $this->organization = $s;
  }

  <<__Deprecated('For OSSSyncAndPush migration - will be removed', 0)>>
  public function __fb__setProject(string $p): void {
    $this->project = $p;
  }
}
