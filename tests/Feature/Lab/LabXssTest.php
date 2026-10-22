<?php

use App\Lab\XssLab;

test('the XSS lab shows every payload and output as text and never renders them', function () {
    $html = $this->get(route('lab.xss'))->assertOk()->getContent();

    // The page's own <script> blocks (layout) are fine; payloads must never be live.
    expect($html)
        ->not->toContain("<script>alert('xss')</script>")
        ->not->toContain('<img src=x onerror')
        ->not->toContain('href="javascript:')
        ->toContain('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;')
        // Output of the escaped approach, escaped once more for display.
        ->toContain('&amp;lt;script&amp;gt;');
});

test('verdicts: which approach lets which payload through', function () {
    $results = (new XssLab)->run();
    $dangerous = fn (string $input) => array_keys(array_filter($results[$input]['outputs'], fn (array $output) => $output['dangerous']));

    expect($dangerous('script'))->toBe(['raw', 'nl2br_raw'])
        ->and($dangerous('onerror'))->toBe(['raw', 'nl2br_raw', 'markdown_default'])
        // Escaped, but still a working javascript: link.
        ->and($dangerous('javascript_url'))->toBe(['href'])
        ->and($dangerous('markdown_link'))->toBe(['markdown_default'])
        ->and($dangerous('https_url'))->toBe([]);

    expect($results['javascript_url']['url_http'])->toBeFalse()
        ->and($results['https_url']['url_http'])->toBeTrue();
});

test('isDangerous() looks at parsed HTML, not at text', function (string $html, bool $expected) {
    expect(XssLab::isDangerous($html))->toBe($expected);
})->with([
    ['<script>alert(1)</script>', true],
    ['<IMG SRC=x OnError=alert(1)>', true],
    ['<a href=" javascript:alert(1)">x</a>', true],
    ['&lt;script&gt;alert(1)&lt;/script&gt;', false],
    ['<a href="&lt;img onerror=x&gt;">x</a>', false],
    ['<p>javascript:alert(1)</p>', false],
    ['', false],
]);
