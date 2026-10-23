<?php

namespace App\Services\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Saring HTML dari editor artikel sebelum disimpan: hanya tag & atribut yang
 * bisa dibuat editor yang dipertahankan, script/event handler/style dibuang.
 */
class ArticleSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            // Tag tak dikenal dibuang, tapi teks di dalamnya tetap ada.
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('blockquote')
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            ->allowElement('hr')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowElement('img', ['src', 'alt', 'title'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener')
            ->withMaxInputLength(1_000_000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        $clean = trim($this->sanitizer->sanitize((string) $html));

        // Hapus paragraf kosong di awal/akhir yang sering tertinggal dari editor.
        return trim(preg_replace('#^(?:<p>(?:\s|<br\s*/?>)*</p>)+|(?:<p>(?:\s|<br\s*/?>)*</p>)+$#', '', $clean));
    }

    /** Isi kosong (hanya spasi / paragraf kosong), tanpa gambar. */
    public function isEmpty(?string $html): bool
    {
        return trim(strip_tags((string) $html, '<img>')) === '';
    }
}
