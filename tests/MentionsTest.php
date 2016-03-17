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


final class MentionsTest extends BaseTest {
  public function examplesForGetMentions(
  ): array<(string, ImmSet<string>)> {
    return [
      tuple(
        '@foo',
        ImmSet { '@foo' },
      ),
      tuple(
        '@foo @bar',
        ImmSet { '@foo', '@bar' },
      ),
      tuple(
        '@foo foo@example.com',
        ImmSet { '@foo' },
      ),
      tuple(
        "\n@foo\n",
        ImmSet { '@foo' },
      ),
    ];
  }

  /**
   * @dataProvider examplesForGetMentions
   */
  public function testGetMentions(
    string $message,
    ImmSet<string> $expected,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    $this->assertEquals(
      $expected,
      ShipItMentions::getMentions($changeset),
    );
  }

  public function rewriteMentionsExamples(
  ): array<(string, (function(string):string), string)> {
    return [
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@foo' ? '@herp' : $mention,
        '@herp @bar @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@bar' ? '@herp' : $mention,
        '@foo @herp @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@bar' ? '' : $mention,
        '@foo  @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> substr($mention, 1),
        'foo bar baz',
      ),
    ];
  }

  /**
   * @dataProvider rewriteMentionsExamples
   */
  public function testRewriteMentions(
    string $message,
    (function(string): string) $callback,
    string $expected_message,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    $this->assertSame(
      $expected_message,
      ShipItMentions::rewriteMentions($changeset, $callback)->getMessage(),
    );
  }

  public function testContainsMention(): void {
    $changeset = (new ShipItChangeset())->withMessage('@foo @bar');
    $this->assertTrue(
      ShipItMentions::containsMention($changeset, '@foo')
    );
    $this->assertTrue(
      ShipItMentions::containsMention($changeset, '@bar')
    );
    $this->assertFalse(
      ShipItMentions::containsMention($changeset, '@baz')
    );
  }
}
