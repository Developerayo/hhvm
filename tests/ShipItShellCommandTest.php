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

final class ShipItShellCommandTest extends BaseTest {
  public function testExitCodeZero(): void {
    $result = (new ShipItShellCommand('/', 'true'))->runSynchronously();
    $this->assertSame(0, $result->getExitCode());
  }

  public function testExitOneException(): void {
    try {
      (new ShipItShellCommand('/', 'false'))->runSynchronously();
      $this->fail('Expected exception');
    } catch (ShipItShellCommandException $e) {
      $this->assertSame(1, $e->getExitCode());
    }
  }

  public function testExitOneWithoutException(): void {
    $result = (new ShipItShellCommand('/', 'false'))
      ->setNoExceptions()
      ->runSynchronously();
    $this->assertSame(1, $result->getExitCode());
  }

  public function testStdIn(): void {
    $result = (new ShipItShellCommand('/', 'cat'))
      ->setStdIn('Hello, world.')
      ->runSynchronously();
    $this->assertSame('Hello, world.', $result->getStdOut());
    $this->assertSame('', $result->getStdErr());
  }

  public function testSettingEnvironmentVariable(): void {
    $herp = bin2hex(random_bytes(16));
    $result = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(ImmMap { 'HERP' => $herp })
      ->runSynchronously();
    $this->assertContains(
      'HERP='.$herp,
      $result->getStdOut(),
    );
  }

  public function testInheritingEnvironmentVariable(): void {
    $to_try = ImmSet {
      // Need to keep SSH/Kerberos environment variables to be able to access
      // repositories
      'SSH_AUTH_SOCK',
      'KRB5CCNAME',
      // Arbitrary common environment variables so we can test /something/ if
      // the above aren't set
      'MAIL',
      'EDITOR',
      'HISTFILE',
      'PATH',
    };

    $output = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(ImmMap { })
      ->runSynchronously()
      ->getStdOut();

    $matched_any = false;
    foreach ($to_try as $var) {
      $value = getenv($var);
      if ($value !== false) {
        $this->assertContains($var.'='.$value."\n", $output);
        $matched_any = true;
      }
    }
    $this->assertTrue(
      $matched_any,
      'No acceptable variables found',
    );
  }

  public function testWorkingDirectory(): void {
    $this->assertSame(
      '/',
      (new ShipItShellCommand('/', 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> trim($$),
    );

    $tmp = sys_get_temp_dir();
    $this->assertSame(
      $tmp,
      (new ShipItShellCommand($tmp, 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> trim($$),
    );
  }

  public function testMultipleArguments(): void {
    $output = (new ShipItShellCommand('/', 'echo', '-n', 'foo', 'bar'))
      ->runSynchronously()
      ->getStdOut();
    $this->assertSame('foo bar', $output);
  }

  public function testEscaping(): void {
    $output = (new ShipItShellCommand('/', 'echo', 'foo', '$FOO'))
      ->setEnvironmentVariables(ImmMap { 'FOO' => 'variable value' })
      ->runSynchronously()
      ->getStdOut();
    $this->assertSame("foo \$FOO\n", $output);
  }
}
