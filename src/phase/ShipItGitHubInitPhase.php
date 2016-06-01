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

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Initialize '.$this->side.' GitHub repository';
  }

  <<__Override>>
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

    // Allows override of destination committer name/email but keeps the same
    // username and password.
    $overrides = $this->side === ShipItRepoSide::DESTINATION ?
      shape(
        'name' => $config->getDestinationCommitterName(),
        'email' => $config->getDestinationCommitterEmail(),
      ) :
      null;
    $class::initializeRepo(
      $this->organization,
      $this->project,
      $local_path,
      $overrides,
    );
  }
}
