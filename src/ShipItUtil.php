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

type ShipItAffectedFile = string;
type ShipItDiffAsString = string;

class ShipItShellCommandException extends \Exception {
  public function __construct(
    private string $command,
    private int $exitCode,
    private string $output,
    private string $error,
  ) {
    parent::__construct("$command returned exit code $exitCode: $error");
  }

  public function getError(): string {
    return $this->error;
  }

  public function getExitCode(): int {
    return $this->exitCode;
  }

  public function getOutput(): string {
    return $this->output;
  }
}

abstract class ShipItUtil {
  const SHORT_REV_LENGTH = 7;
  // flags for shellExec, no flag equal to 1
  // for compatibility with ShipItRepo verbose flags
  const DONT_VERBOSE = 0;
  const VERBOSE_SHELL = 2;
  const VERBOSE_SHELL_INPUT = 4;
  const VERBOSE_SHELL_OUTPUT = 8;
  const NO_THROW = 16;
  const RETURN_STDERR = 32;

  /*
   * Generator yielding patch sections starting with header,
   * then each of the diff blocks (individually)
   * and finally the footer
   */
  public static function parsePatchWithHeader(string $patch) {
    return self::parsePatch($patch, true);
  }

  public static function parsePatchWithoutHeader(string $patch) {
    return self::parsePatch($patch, false);
  }

  private static function parsePatch(string $patch, bool $expectHeader) {
    $lookForDiff = !$expectHeader;
    $contents = '';
    $matches = [];

    $minus_lines = 0;
    $plus_lines = 0;
    $seen_range_header = false;

    foreach (explode("\n", $patch) as $line) {
      $line = preg_replace('/(\r\n|\n)/', "\n", $line);

      if (!$lookForDiff) {
        if (rtrim($line) === '---') {
          $lookForDiff = true;
        }
        $contents .= $line."\n";
        continue;
      }

      if (preg_match('@^diff --git [ab]/(.*?) [ab]/\1$@', trim($line))) {
        if ($contents !== '' || $expectHeader) {
          yield $contents;
        }
        $seen_range_header = false;
        $contents = $line."\n";
        continue;
      }
      if (
        preg_match(
          '/^@@ -\d+(,(?<minus_lines>\d+))? \+\d+(,(?<plus_lines>\d+))? @@/',
          $line,
          $matches,
        )
      ) {
        $minus_lines = $matches['minus_lines'] ?? '';
        $minus_lines = $minus_lines === '' ? 1 : (int) $minus_lines;
        $plus_lines = $matches['plus_lines'] ?? '';
        $plus_lines = $plus_lines === '' ? 1 : (int) $plus_lines;

        $contents .= $line."\n";
        $seen_range_header = true;
        continue;
      }

      if (!$seen_range_header) {
        $contents .= $line ."\n";
        continue;
      }

      $leftmost = substr($line, 0, 1);
      if ($leftmost === "\\") {
        $contents .= $line."\n";
        // Doesn't count as a + or - line whatever happens; if NL at EOF
        // changes, there is a + and - for the last line of content
        continue;
      }

      if ($minus_lines <= 0 && $plus_lines <= 0) {
        continue;
      }

      $leftmost = substr($line, 0, 1);
      if ($leftmost === '+') {
        --$plus_lines;
      } else if ($leftmost === '-') {
        --$minus_lines;
      } else if ($leftmost === ' ') {
        // Context goes from both.
        --$plus_lines;
        --$minus_lines;
      } else {
        invariant_violation(
          "Can't parse hunk line: %s",
          $line,
        );
      }
      $contents .= $line."\n";
    }

    if ($contents !== '') {
      // If we got the patch from git-diff, there won't be the signature line
      // from format-patch
      yield $contents;
    }
  }

  /**
   * Convert a region to a (path, patch_body) tuple
   */
  public static function parseDiffRegion(string $region): (string, string) {
    list($header, $body) = explode("\n", $region, 2);
    $matches = array();
    preg_match('@^diff --git [ab]/(.*?) [ab]/\1$@', trim($header), $matches);
    return tuple($matches[1], $body);
  }

  public static function isNewFile(string $body): bool {
    return (bool) preg_match('@^new file@m', $body);
  }

  public static function isFileRemoval(string $body): bool {
    return (bool) preg_match('@^deleted file@m', $body);
  }

  // readStreams reads from multiple streams in "parallel"
  // (by using stream_select) which ensures that
  // reading process won't block waiting for data in one stream when it
  // appears in other
  private static function readStreams(
    array<resource> $streams,
  ): array<string> {
    $outs = array_fill(0, count($streams), '');
    $reverse_map = array();

    for ($i = 0; $i < count($streams); ++$i) {
      stream_set_blocking($streams[$i], 0);
      $reverse_map[$streams[$i]] = $i;
    }
    $stop = false;
    while (!$stop) {
      $null = null;
      $ready_streams = $streams;
      if (!stream_select($ready_streams, $null, $null, null)) {
        $stop = true;
      } else {
        $all_empty = true;
        foreach ($ready_streams as $stream) {
          $out = fread($stream, 1024);
          if ($out !== false && strlen($out) !== 0) {
            $all_empty = false;
            $outs[$reverse_map[$stream]] .= $out;
          }
        }
        $stop = $all_empty;
      }
    }
    return $outs;
  }

  public static function shellExec(
    string $path,
    ?string $stdin,
    int $flags,
    ...$args
  ): string {
    $fds = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w'),
    );
    if ($stdin === null) {
      unset($fds[0]);
    }

    $argn = null;
    foreach ($args as &$argn) {
      $argn = escapeshellarg($argn);
    }
    unset($argn);

    $cmd = implode(' ', $args);
    if ($flags & self::VERBOSE_SHELL) {
      fwrite(STDERR, "\$ $cmd\n");
    }
    $pipes = null;
    $fp = proc_open($cmd, $fds, $pipes, $path);
    if (!$fp || !is_array($pipes)) {
      throw new \Exception("Failed executing $cmd");
    }
    if ($stdin !== null) {
      if ($flags & self::VERBOSE_SHELL_INPUT) {
        fwrite(STDERR, "--STDIN--\n$stdin\n");
      }
      while (strlen($stdin)) {
        $written = fwrite($pipes[0], $stdin);
        $stdin = substr($stdin, $written);
      }
      fclose($pipes[0]);
    }
    list($output, $error) = self::readStreams(array($pipes[1], $pipes[2]));
    if ($flags & self::VERBOSE_SHELL_OUTPUT) {
      if ($error) {
        fwrite(STDERR, "--STDERR--\n$error\n");
      }
      if ($output) {
        fwrite(STDERR, "--STDOUT--\n$output\n");
      }
    }
    $exitcode = proc_close($fp);

    if ($exitcode && !($flags & self::NO_THROW)) {
      throw new ShipItShellCommandException($cmd, $exitcode, $output, $error);
    }

    if ($flags & self::RETURN_STDERR) {
      return $output."\n".$error;
    }
    return $output;
  }

  public static function matchesAnyPattern(
    string $path,
    ImmVector<string> $patterns,
  ): ?string {
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $path)) {
        return $pattern;
      }
    }
    return null;
  }
}
