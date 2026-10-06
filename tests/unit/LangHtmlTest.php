<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LangHtmlTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        helper('lang_html');
    }

    public function testHelperIsRegistered(): void
    {
        $this->assertTrue(function_exists('lang_html'));
    }

    public function testEscapesHtmlInTranslation(): void
    {
        $escaped = lang_html('Reception.validation.reference_taken', ['<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testEscapesDoubleQuotes(): void
    {
        $escaped = lang_html('Reception.validation.reference_taken', ['PB-"001"']);

        $this->assertStringNotContainsString('"', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
    }

    public function testEscapesSingleQuotes(): void
    {
        $escaped = lang_html('Reception.validation.reference_taken', ["PB-'001'"]);

        $this->assertStringNotContainsString("'", $escaped);
        $this->assertStringContainsString('&#039;', $escaped);
    }

    public function testKeepsAmpersandEscapedOnce(): void
    {
        $escaped = lang_html('Reception.validation.reference_taken', ['A & B']);

        $this->assertStringContainsString('A &amp; B', $escaped);
        $this->assertStringNotContainsString('&amp;amp;', $escaped);
    }

    public function testFormatsPlaceholderArguments(): void
    {
        $this->assertSame('3 batch', lang_html('Stock.table.batch_count', [3]));
    }

    public function testTranslatesPlainStringWithoutFilePrefix(): void
    {
        $this->assertSame('teks polos', lang_html('teks polos'));
    }

    public function testAcceptsExplicitLocale(): void
    {
        $this->assertSame('Sign in', lang_html('Auth.login.title', [], 'en'));
    }

    public function testUsesDefaultLocaleWhenLocaleIsOmitted(): void
    {
        $this->assertSame('Masuk', lang_html('Auth.login.title'));
    }
}
