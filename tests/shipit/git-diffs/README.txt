Diffs should be exported in the same way that the ShipItRepo does - for example:

$ hhvm -m debug
Welcome to HipHop Debugger!
Type "help" or "?" for a complete list of commands.

hphpd> require_once('./autoload.php')
hphpd> $repo = new ShipItRepoGIT('/tmp/gittest', HH\ImmVector { '' })
hphpd> $patch = $repo->getNativePatchFromID('f9f3f5645604ab1d95c21a7825a8f73948661f62')
hphpd> file_put_contents('tests/git-diffs/file-to-symlink.diff', $patch)
