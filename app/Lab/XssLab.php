<?php

namespace App\Lab;

use Closure;
use DOMDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * /lab/xss: the same malicious inputs through different ways of printing them.
 * Nothing here is ever rendered as HTML — the page shows the produced HTML source
 * as escaped text, so the payloads cannot run.
 */
class XssLab
{
    /**
     * Inputs a user might type into a review, a name or a "website" field.
     */
    public const INPUTS = [
        'script' => "Hello!\n<script>alert('xss')</script>",
        'onerror' => '<img src=x onerror="alert(\'xss\')">',
        'javascript_url' => "javascript:alert('xss')",
        'markdown_link' => "[link](javascript:alert('xss'))\n**bold**",
        'https_url' => 'https://uk.wikipedia.org/wiki/XSS',
    ];

    /**
     * @return array<string, array{code: string, render: Closure(string): string}>
     */
    public function approaches(): array
    {
        return [
            // {{ }} compiles to e(): < > & " ' become entities.
            'escaped' => ['code' => '{{ $input }}', 'render' => fn (string $input) => e($input)],
            'raw' => ['code' => '{!! $input !!}', 'render' => fn (string $input) => $input],
            // Escape first, then add <br>: the only tags in the result are the <br>s.
            'nl2br_escaped' => ['code' => '{!! nl2br(e($input)) !!}', 'render' => fn (string $input) => nl2br(e($input))],
            // Wrong order: nl2br() does not escape anything.
            'nl2br_raw' => ['code' => '{!! nl2br($input) !!}', 'render' => fn (string $input) => nl2br($input)],
            // Defaults: html_input "allow", unsafe links allowed — raw HTML passes through.
            'markdown_default' => ['code' => '{!! Str::markdown($input) !!}', 'render' => fn (string $input) => Str::markdown($input)],
            'markdown' => [
                'code' => "{!! Str::markdown(\$input, [\n    'html_input' => 'strip',\n    'allow_unsafe_links' => false,\n]) !!}",
                'render' => fn (string $input) => Str::markdown($input, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            ],
            // Escaped, yet dangerous: entities do not change the URL scheme.
            'href' => ['code' => '<a href="{{ $input }}">…</a>', 'render' => fn (string $input) => '<a href="'.e($input).'">…</a>'],
        ];
    }

    /**
     * Every input through every approach.
     *
     * @return array<string, array{input: string, outputs: array<string, array{html: string, dangerous: bool}>, url: bool, url_http: bool}>
     */
    public function run(): array
    {
        $approaches = $this->approaches();

        return collect(self::INPUTS)->map(fn (string $input) => [
            'input' => $input,
            'outputs' => collect($approaches)->map(function (array $approach) use ($input) {
                $html = ($approach['render'])($input);

                return ['html' => $html, 'dangerous' => self::isDangerous($html)];
            })->all(),
            // The fix for URLs is validation, not escaping: allow-list the schemes.
            'url' => Validator::make(['website' => $input], ['website' => 'url'])->passes(),
            'url_http' => Validator::make(['website' => $input], ['website' => 'url:http,https'])->passes(),
        ])->all();
    }

    /**
     * Would a browser run script from this HTML? Parses it like a browser would
     * (DOMDocument) and looks for a <script> element, an on* event-handler
     * attribute or a javascript: URL in href/src. A demo check, not a sanitizer.
     */
    public static function isDangerous(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        $dom = new DOMDocument;
        // The XML declaration makes libxml read the string as UTF-8.
        @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NONET);

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (strtolower($element->nodeName) === 'script') {
                return true;
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->name);
                // Browsers ignore leading whitespace/control chars before the scheme.
                $scheme = strtolower(ltrim($attribute->value, " \t\n\r\0\x0B"));

                if (str_starts_with($name, 'on') || (in_array($name, ['href', 'src'], true) && str_starts_with($scheme, 'javascript:'))) {
                    return true;
                }
            }
        }

        return false;
    }
}
