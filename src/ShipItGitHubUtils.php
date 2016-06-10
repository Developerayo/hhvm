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

type ShipItGitHubCredentials = shape(
  'name' => string,
  'user' => string,
  'email' => string,
  'password' => string,
);

abstract class ShipItGitHubUtils {
  /** Fetch information on a user that has permission to write to a project.
   *
   * For example, for most projects on github.com/facebook/, it will return
   * information on a random facebook-github-bot user - though for
   * github.com/facebook/hhvm/ it wil return hhvm-bot.
   *
   * This is used by ::initializeRepo().
   */
  public abstract static function getCredentialsForProject(
    string $organization,
    string $project,
  ): ShipItGitHubCredentials;

  /**
   * Configure the user and origin for a repository, cloning if necessary.
   *
   * - requires getCredentialsForProject() to be implemented
   * - configures 'origin' to be authenticated HTTPS
   */
  final public static function initializeRepo(
    string $organization,
    string $project,
    string $local_path,
    ?ShipItGitHubCredentials $credentials,
  ): void {
    $git_config = ($key, $value) ==> new ShipItShellCommand(
      $local_path,
      'git', 'config', $key, $value,
    );

    if (!is_null($credentials)) {
      $origin = sprintf(
        'https://%s:%s@github.com/%s/%s.git',
        urlencode($credentials['user']),
        urlencode($credentials['password']),
        $organization,
        $project,
      );

      self::cloneAndVerifyRepo($origin, $local_path);

      $git_config('user.name', $credentials['name'])->runSynchronously();
      $git_config('user.email', $credentials['email'])->runSynchronously();
    } else {
      $origin = sprintf(
        'https://github.com/%s/%s.git',
        $organization,
        $project,
      );

      self::cloneAndVerifyRepo($origin, $local_path);
    }

    $git_config('remote.origin.url', $origin)->runSynchronously();
  }

  private static function cloneAndVerifyRepo(
    string $origin,
    string $local_path,
  ): void {
    if (!file_exists($local_path)) {
      ShipItRepoGIT::cloneRepo(
        $origin,
        $local_path,
      );
    }
    invariant(
      file_exists($local_path.'/.git'),
      '%s is not a git repo',
      $local_path,
    );
  }

  final public static async function makeAPIRequest(
    ShipItGitHubCredentials $credentials,
    string $path,
  ): Awaitable<ImmVector<string>> {
    $results = Vector { };
    $request_headers = Vector { 'Accept: application/vnd.github.v3.patch' };

    $url = sprintf(
      'https://%s:%s@api.github.com%s',
      urlencode($credentials['user']),
      urlencode($credentials['password']),
      $path,
    );
    while ($url !== null) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Facebook/ShipIt');
      curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      $response = await \HH\Asio\curl_exec($ch);
      $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      $response_header = substr($response, 0, $header_len);
      $results[] = substr($response, $header_len);

      $url = null;
      foreach (explode("\n", trim($response_header)) as $header_line) {
        if (substr($header_line, 0, 5) === 'HTTP/') {
          continue;
        }
        $sep = strpos($header_line, ':');
        if ($sep === false) {
          continue;
        }

        $name = strtolower(substr($header_line, 0, $sep));
        if ($name === 'link') {
          $matches = [];
          if (
            preg_match(
              '@<(?<next>https://api.github.com[^>]+)>; rel="next"@',
              $header_line,
              $matches,
            )
          ) {
            $url = $matches['next'];
            break;
          }
        }
      }
    }
    return $results->toImmVector();
  }
}
