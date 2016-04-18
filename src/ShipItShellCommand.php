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

class ShipItShellCommand {
  private ImmVector<string> $command;

  private Map<string, string> $environmentVariables = Map {};
  private bool $throwForNonZeroExit = true;
  private ?string $stdin = null;
  private bool $outputToScreen = false;

  public function __construct(
    private string $path,
    ...$command
  ) {
    $this->command = new ImmVector($command);
  }

  public function setStdIn(string $input): this {
    $this->stdin = $input;
    return $this;
  }

  public function setOutputToScreen(): this {
    $this->outputToScreen = true;
    return $this;
  }

  public function setEnvironmentVariables(
    ImmMap<string, string> $vars,
  ): this {
    $this->environmentVariables->setAll($vars);
    return $this;
  }

  public function setNoExceptions(): this {
    $this->throwForNonZeroExit = false;
    return $this;
  }

  public function runSynchronously(): ShipItShellCommandResult {
    $command = implode(' ', $this->command->map($str ==> escapeshellarg($str)));

    $fds = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w'),
    );
    $stdin = $this->stdin;
    if ($stdin === null) {
      unset($fds[0]);
    }

    $pipes = null;
    $fp = proc_open(
      $command,
      $fds,
      $pipes,
      $this->path,
      $this->environmentVariables->toArray(),
    );
    if (!$fp || !is_array($pipes)) {
      throw new \Exception("Failed executing $command");
    }
    if ($stdin !== null) {
      while (strlen($stdin)) {
        $written = fwrite($pipes[0], $stdin);
        $stdin = substr($stdin, $written);
      }
      fclose($pipes[0]);
    }

    $stdout_stream = $pipes[1];
    $stderr_stream = $pipes[2];
    stream_set_blocking($stdout_stream, false);
    stream_set_blocking($stderr_stream, false);
    $stdout = '';
    $stderr = '';
    while (true) {
      $ready_streams = [$stdout_stream, $stderr_stream];
      $null_byref = null;
      $result = stream_select(
        $ready_streams,
        /* write streams = */ $null_byref,
        /* exception streams = */ $null_byref,
        /* timeout = */ null,
      );
      if ($result === false) {
        break;
      }
      $all_empty = true;
      foreach ($ready_streams as $stream) {
        $out = fread($stream, 1024);
        if (strlen($out) === 0) {
          continue;
        }
        $all_empty = false;

        if ($stream === $stdout_stream) {
          $stdout .= $out;
          $this->maybeFwrite(STDOUT, $out);
          continue;
        }
        if ($stream === $stderr_stream) {
          $stderr .= $out;
          $this->maybeFwrite(STDERR, $out);
          continue;
        }

        invariant_violation('Unhandled stream!');
      }

      if ($all_empty) {
        break;
      }
    }
    $exitcode = proc_close($fp);

    $result = new ShipItShellCommandResult(
      $exitcode,
      $stdout,
      $stderr,
    );

    if ($exitcode !== 0 && $this->throwForNonZeroExit) {
      throw new ShipItShellCommandException($command, $result);
    }

    return $result;
  }

  private function maybeFwrite(resource $stream, string $out): void {
    if (!$this->outputToScreen) {
      return;
    }
    fwrite($stream, $out);
  }
}
