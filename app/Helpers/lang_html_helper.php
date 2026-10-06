<?php

/**
 * Helper terjemahan untuk keluaran HTML.
 *
 * Fungsi ini hanya untuk dipakai di view. Untuk lapisan API, service, atau
 * validator, pakai lang() polos: hasilnya masuk JSON, bukan HTML, dan
 * meng-escape di sana akan tampak sebagai &amp;quot; di layar pemakai.
 */

if (! function_exists('lang_html')) {
    /**
     * Terjemahkan string bahasa lalu escape untuk konteks HTML.
     *
     * Setara dengan esc(lang($line, $args, $locale)). Konteks 'html' pada esc()
     * memakai htmlspecialchars dengan flag ENT_QUOTES, sehingga hasilnya juga
     * aman dipakai di dalam nilai atribut bertanda kutip.
     *
     * @param array<array-key, float|int|string> $args
     */
    function lang_html(string $line, array $args = [], ?string $locale = null): string
    {
        return esc(lang($line, $args, $locale));
    }
}
