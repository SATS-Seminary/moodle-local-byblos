<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared HTML builders for academic-focused portfolio section types.
 *
 * These are invoked identically from the public renderer (classes/renderer.php)
 * and the editor preview renderer (classes/section_renderer.php) so the editing
 * preview matches the published view exactly.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

/**
 * Renders the academic section types (chart, cloud, quote, stats, citations).
 *
 * All methods produce fully self-contained HTML fragments that do not depend on
 * theme CSS to look correct — presentation-critical rules use inline styles and
 * `!important`. Every wrapper receives a `byblos-section-{type}` class so theme
 * authors can still hook further customisations.
 */
class section_helpers {
    /**
     * Parse a hex colour (#rrggbb or #rgb) into an array of 0–255 RGB integers.
     *
     * @param string $hex Input colour string (tolerant of missing '#').
     * @param array  $fallback Fallback triple if parsing fails.
     * @return int[] [r, g, b]
     */
    private static function hex_to_rgb(string $hex, array $fallback = [13, 110, 253]): array {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Return a hex colour string from an RGB triple (each 0–255).
     *
     * @param int $r Red 0–255.
     * @param int $g Green 0–255.
     * @param int $b Blue 0–255.
     * @return string `#rrggbb`.
     */
    private static function rgb_to_hex(int $r, int $g, int $b): string {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Shift a hex colour by a deterministic amount, clamped to byte range.
     * Used to produce pie/chart slice variants from one base colour.
     *
     * @param string $hex   Base colour.
     * @param int    $index Index of the variant (0-based).
     * @param int    $count Total number of variants we're generating (for spread).
     * @return string Adjusted hex colour.
     */
    public static function variant_color(string $hex, int $index, int $count): string {
        [$r, $g, $b] = self::hex_to_rgb($hex);
        $count = max(1, $count);
        // Spread hues around the base: alternate lighten/darken, widening by index.
        $step  = (int) (70 * ($index / $count));
        $sign  = ($index % 2 === 0) ? 1 : -1;
        $r2 = $r + $sign * $step;
        $g2 = $g - $sign * (int) ($step * 0.6);
        $b2 = $b + $sign * (int) ($step * 0.3);
        return self::rgb_to_hex($r2, $g2, $b2);
    }
    // Chart section.

    /**
     * Render a server-side SVG chart (bar, line, pie, donut).
     *
     * @param array $config Decoded configdata: `heading`, `type`, `color`, `items[{label,value}]`.
     * @return string HTML fragment.
     */
    public static function render_chart(array $config): string {
        $heading = (string) ($config['heading'] ?? '');
        $type    = (string) ($config['type'] ?? 'bar');
        $color   = (string) ($config['color'] ?? '#0d6efd');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($type, ['bar', 'line', 'pie', 'donut'], true)) {
            $type = 'bar';
        }

        $html = '<div class="byblos-section-chart" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-chart-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>' . get_string('nochart', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        switch ($type) {
            case 'line':
                $html .= self::render_chart_line($items, $color);
                break;
            case 'pie':
                $html .= self::render_chart_pie($items, $color, false);
                break;
            case 'donut':
                $html .= self::render_chart_pie($items, $color, true);
                break;
            case 'bar':
            default:
                $html .= self::render_chart_bar($items, $color);
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * SVG horizontal bar chart.
     *
     * @param array  $items [{label, value}, ...]
     * @param string $color Base bar colour (hex).
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_bar(array $items, string $color): string {
        $max = 0.0;
        foreach ($items as $it) {
            $v = (float) ($it['value'] ?? 0);
            if ($v > $max) {
                $max = $v;
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }

        $rowh    = 34;
        $labelw  = 150;
        $chartw  = 640;
        $padding = 20;
        $count   = count($items);
        $h       = $padding * 2 + $rowh * $count;
        $barmax  = $chartw - $labelw - 60;

        $svg = '<svg viewBox="0 0 ' . $chartw . ' ' . $h . '" preserveAspectRatio="xMidYMid meet"'
            . ' style="width:100% !important; max-width:100% !important; height:auto !important;"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img">';

        foreach (array_values($items) as $i => $it) {
            $label = (string) ($it['label'] ?? '');
            $val   = (float) ($it['value'] ?? 0);
            $barw  = (int) round(($val / $max) * $barmax);
            $y     = $padding + $i * $rowh;

            $svg .= '<text x="' . ($labelw - 8) . '" y="' . ($y + 18) . '"'
                . ' text-anchor="end" font-size="13" fill="#333">' . s($label) . '</text>';
            $svg .= '<rect x="' . $labelw . '" y="' . ($y + 6) . '" width="' . $barw
                . '" height="' . ($rowh - 14) . '" rx="3" ry="3" fill="' . s($color) . '"></rect>';
            $svg .= '<text x="' . ($labelw + $barw + 6) . '" y="' . ($y + 18) . '"'
                . ' font-size="12" fill="#666">' . s((string) $val) . '</text>';
        }

        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }

    /**
     * SVG line chart with data points.
     *
     * @param array  $items [{label, value}, ...]
     * @param string $color Line / point colour.
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_line(array $items, string $color): string {
        $max = 0.0;
        foreach ($items as $it) {
            $v = (float) ($it['value'] ?? 0);
            if ($v > $max) {
                $max = $v;
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }

        $w       = 640;
        $h       = 260;
        $padleft = 40;
        $padr    = 20;
        $padtop  = 20;
        $padbot  = 40;

        $plotw = $w - $padleft - $padr;
        $ploth = $h - $padtop - $padbot;
        $count = max(1, count($items));

        $points = [];
        foreach (array_values($items) as $i => $it) {
            $val = (float) ($it['value'] ?? 0);
            $x   = $padleft + ($count > 1 ? ($i / ($count - 1)) * $plotw : $plotw / 2);
            $y   = $padtop + $ploth - ($val / $max) * $ploth;
            $points[] = [$x, $y, (string) ($it['label'] ?? ''), $val];
        }

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid meet"'
            . ' style="width:100% !important; max-width:100% !important; height:auto !important;"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img">';
        // Axes.
        $svg .= '<line x1="' . $padleft . '" y1="' . ($padtop + $ploth) . '" x2="' . ($w - $padr)
            . '" y2="' . ($padtop + $ploth) . '" stroke="#ccc" stroke-width="1"/>';
        $svg .= '<line x1="' . $padleft . '" y1="' . $padtop . '" x2="' . $padleft
            . '" y2="' . ($padtop + $ploth) . '" stroke="#ccc" stroke-width="1"/>';

        // Polyline.
        $polyline = '';
        foreach ($points as $p) {
            $polyline .= round($p[0], 2) . ',' . round($p[1], 2) . ' ';
        }
        $svg .= '<polyline points="' . trim($polyline) . '" fill="none" stroke="'
            . s($color) . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';

        // Points + labels.
        foreach ($points as $p) {
            [$x, $y, $label, $val] = $p;
            $svg .= '<circle cx="' . round($x, 2) . '" cy="' . round($y, 2) . '" r="4"'
                . ' fill="' . s($color) . '"></circle>';
            $svg .= '<text x="' . round($x, 2) . '" y="' . ($padtop + $ploth + 18) . '"'
                . ' text-anchor="middle" font-size="11" fill="#666">' . s($label) . '</text>';
            $svg .= '<text x="' . round($x, 2) . '" y="' . (round($y, 2) - 8) . '"'
                . ' text-anchor="middle" font-size="11" fill="#333">' . s((string) $val) . '</text>';
        }

        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }

    /**
     * SVG pie or donut chart with an adjacent legend.
     *
     * @param array  $items [{label, value}, ...]
     * @param string $color Base slice colour (others derived).
     * @param bool   $donut If true, render as donut (hollow centre).
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_pie(array $items, string $color, bool $donut): string {
        $total = 0.0;
        foreach ($items as $it) {
            $total += max(0, (float) ($it['value'] ?? 0));
        }
        if ($total <= 0) {
            $total = 1.0;
        }

        $w    = 480;
        $h    = 280;
        $cx   = 140;
        $cy   = 140;
        $r    = 120;
        $rin  = $donut ? 60 : 0;
        $count = count($items);

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid meet"'
            . ' style="width:100% !important; max-width:100% !important; height:auto !important;"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img">';

        $angle = -M_PI_2; // Start at top.
        $idx = 0;
        foreach ($items as $it) {
            $val = max(0, (float) ($it['value'] ?? 0));
            if ($val <= 0) {
                $idx++;
                continue;
            }
            $slice = ($val / $total) * 2 * M_PI;
            $a1 = $angle;
            $a2 = $angle + $slice;
            $largearc = $slice > M_PI ? 1 : 0;

            $x1 = $cx + $r * cos($a1);
            $y1 = $cy + $r * sin($a1);
            $x2 = $cx + $r * cos($a2);
            $y2 = $cy + $r * sin($a2);

            $slicecolor = self::variant_color($color, $idx, max(1, $count));

            if ($donut) {
                $xi1 = $cx + $rin * cos($a1);
                $yi1 = $cy + $rin * sin($a1);
                $xi2 = $cx + $rin * cos($a2);
                $yi2 = $cy + $rin * sin($a2);
                $path = 'M ' . round($x1, 2) . ' ' . round($y1, 2)
                    . ' A ' . $r . ' ' . $r . ' 0 ' . $largearc . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2)
                    . ' L ' . round($xi2, 2) . ' ' . round($yi2, 2)
                    . ' A ' . $rin . ' ' . $rin . ' 0 ' . $largearc . ' 0 ' . round($xi1, 2) . ' ' . round($yi1, 2)
                    . ' Z';
            } else {
                $path = 'M ' . $cx . ' ' . $cy
                    . ' L ' . round($x1, 2) . ' ' . round($y1, 2)
                    . ' A ' . $r . ' ' . $r . ' 0 ' . $largearc . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2)
                    . ' Z';
            }

            $svg .= '<path d="' . $path . '" fill="' . s($slicecolor)
                . '" stroke="#fff" stroke-width="2"></path>';

            $angle = $a2;
            $idx++;
        }

        // Legend on the right.
        $lx = 290;
        $ly = 30;
        $idx = 0;
        foreach ($items as $it) {
            $label = (string) ($it['label'] ?? '');
            $val   = (float) ($it['value'] ?? 0);
            $pct   = round(($val / $total) * 100, 1);
            $slicecolor = self::variant_color($color, $idx, max(1, $count));

            $svg .= '<rect x="' . $lx . '" y="' . $ly . '" width="14" height="14" rx="2" ry="2"'
                . ' fill="' . s($slicecolor) . '"></rect>';
            $svg .= '<text x="' . ($lx + 22) . '" y="' . ($ly + 12) . '" font-size="12"'
                . ' fill="#333">' . s($label) . ' — ' . s((string) $pct) . '%</text>';
            $ly += 22;
            $idx++;
        }

        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }
    // Cloud section.

    /**
     * Render a word cloud as a flex-wrapped span list with deterministic per-word sizing.
     *
     * @param array $config Decoded configdata: `heading`, `color`, `items[{text,weight}]`.
     * @return string HTML fragment.
     */
    public static function render_cloud(array $config): string {
        $heading = (string) ($config['heading'] ?? '');
        $color   = (string) ($config['color'] ?? '#0d6efd');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        $html = '<div class="byblos-section-cloud" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-cloud-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>' . get_string('nocloud', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div class="byblos-cloud-wrap" style="display:flex !important; flex-wrap:wrap !important;'
            . ' justify-content:center !important; align-items:center !important; gap:0.5rem 0.9rem !important;'
            . ' padding:0.5rem !important;">';

        $count = count($items);
        $idx   = 0;
        foreach ($items as $it) {
            $text   = (string) ($it['text'] ?? '');
            $weight = (int) ($it['weight'] ?? 1);
            $weight = max(1, min(10, $weight));
            if ($text === '') {
                continue;
            }

            $fontsize = 0.75 + ($weight * 0.18); // 0.93rem to 2.55rem.
            $opacity  = 0.6 + ($weight * 0.04);  // 0.64 to 1.0.
            $wordcolor = self::variant_color($color, $idx, max(6, $count));

            $html .= '<span class="byblos-cloud-word" style="display:inline-block !important;'
                . ' font-size:' . number_format($fontsize, 2) . 'rem !important;'
                . ' font-weight:' . (500 + $weight * 20) . ' !important;'
                . ' line-height:1.1 !important;'
                . ' color:' . s($wordcolor) . ' !important;'
                . ' opacity:' . number_format(min(1.0, $opacity), 2) . ' !important;">'
                . s($text) . '</span>';

            $idx++;
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
    // Quote section.

    /**
     * Render a pull-quote with attribution (optionally linked to a source URL).
     *
     * @param array $config Decoded configdata: `body` (rich), `attribution`, `source`.
     * @return string HTML fragment.
     */
    public static function render_quote(array $config): string {
        $body        = (string) ($config['body'] ?? '');
        $attribution = (string) ($config['attribution'] ?? '');
        $source      = (string) ($config['source'] ?? '');

        $html = '<div class="byblos-section-quote" style="padding:2rem 1rem !important;">';
        $html .= '<blockquote class="byblos-quote-block" style="position:relative !important;'
            . ' max-width:760px !important; margin:0 auto !important; padding:1rem 2.5rem !important;'
            . ' text-align:center !important; border:none !important; font-style:italic !important;">';

        // Decorative opening quote mark.
        $html .= '<span aria-hidden="true" class="byblos-quote-mark" style="position:absolute !important;'
            . ' top:-0.2em !important; left:0 !important; font-size:4rem !important; line-height:1 !important;'
            . ' color:rgba(0,0,0,0.15) !important; font-family:Georgia,serif !important;">&ldquo;</span>';

        if ($body !== '') {
            $html .= '<div class="byblos-quote-body" style="font-size:1.25rem !important;'
                . ' line-height:1.55 !important; color:#333 !important; margin-bottom:1rem !important;">'
                . $body . '</div>';
        } else {
            $html .= '<div class="byblos-quote-body text-muted"><em>'
                . get_string('emptyquote', 'local_byblos') . '</em></div>';
        }

        if ($attribution !== '') {
            $attrhtml = s($attribution);
            if ($source !== '') {
                $attrhtml = '<a href="' . s($source) . '" target="_blank" rel="noopener"'
                    . ' style="color:inherit !important; text-decoration:underline !important;">'
                    . $attrhtml . '</a>';
            }
            $html .= '<footer class="byblos-quote-attribution" style="font-style:normal !important;'
                . ' font-size:0.95rem !important; color:#777 !important;">&mdash; ' . $attrhtml . '</footer>';
        }

        $html .= '</blockquote>';
        $html .= '</div>';
        return $html;
    }
    // Stats section.

    /**
     * Render a row of 2–4 big-number stat cards.
     *
     * @param array  $config   Decoded configdata: `heading`, `items[{number,label,description}]`.
     * @param string $themekey Page theme key (for accent colour on numbers).
     * @return string HTML fragment.
     */
    public static function render_stats(array $config, string $themekey = ''): string {
        $heading = (string) ($config['heading'] ?? '');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];
        if (count($items) > 4) {
            $items = array_slice($items, 0, 4);
        }

        $accent = $themekey !== ''
            ? theme::get_accent_color($themekey)
            : '#0d6efd';

        $html = '<div class="byblos-section-stats" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-stats-heading" style="margin-bottom:1rem !important;'
                . ' text-align:center !important;">' . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted text-center"><em>'
                . get_string('nostats', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div class="byblos-stats-grid" style="display:grid !important;'
            . ' grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)) !important;'
            . ' gap:1rem !important;">';

        foreach ($items as $it) {
            $number = (string) ($it['number'] ?? '');
            $label  = (string) ($it['label'] ?? '');
            $desc   = (string) ($it['description'] ?? '');

            $html .= '<div class="byblos-stats-card" style="background:#fff !important;'
                . ' border:1px solid rgba(0,0,0,0.08) !important; border-radius:0.5rem !important;'
                . ' padding:1.5rem 1rem !important; text-align:center !important;'
                . ' box-shadow:0 1px 3px rgba(0,0,0,0.04) !important;">';
            $html .= '<div class="byblos-stats-number" style="font-size:2.5rem !important;'
                . ' font-weight:700 !important; line-height:1 !important;'
                . ' color:' . s($accent) . ' !important; margin-bottom:0.5rem !important;">'
                . s($number) . '</div>';
            if ($label !== '') {
                $html .= '<div class="byblos-stats-label" style="font-size:1rem !important;'
                    . ' font-weight:600 !important; color:#333 !important;'
                    . ' margin-bottom:0.25rem !important;">' . s($label) . '</div>';
            }
            if ($desc !== '') {
                $html .= '<div class="byblos-stats-desc" style="font-size:0.85rem !important;'
                    . ' color:#777 !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    // Citations section.

    /**
     * Render a numbered academic bibliography.
     *
     * @param array $config Decoded configdata: `heading`, `style`, `items[{text,url}]`.
     * @return string HTML fragment.
     */
    public static function render_citations(array $config): string {
        $heading = (string) ($config['heading'] ?? get_string('citations_default_heading', 'local_byblos'));
        $style   = (string) ($config['style'] ?? 'plain');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($style, ['apa', 'mla', 'chicago', 'plain'], true)) {
            $style = 'plain';
        }

        // Per-style spacing and indent tweaks — kept light.
        $stylecss = [
            'apa'     => 'padding-left:2.5rem !important; text-indent:-1.5rem !important; margin-bottom:0.9rem !important;',
            'mla'     => 'padding-left:2.5rem !important; text-indent:-1.5rem !important; margin-bottom:0.6rem !important;',
            'chicago' => 'padding-left:2rem !important; margin-bottom:0.75rem !important;',
            'plain'   => 'padding-left:1rem !important; margin-bottom:0.5rem !important;',
        ];
        $itemstyle = $stylecss[$style];

        $html = '<div class="byblos-section-citations byblos-citations-style-' . s($style)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-citations-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('nocitations', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<ol class="byblos-citations-list" style="list-style:decimal !important;'
            . ' padding-left:1.5rem !important; font-size:0.95rem !important;'
            . ' line-height:1.5 !important; color:#333 !important;">';

        foreach ($items as $it) {
            $text = (string) ($it['text'] ?? '');
            $url  = (string) ($it['url'] ?? '');
            if ($text === '') {
                continue;
            }
            $body = s($text);
            if ($url !== '') {
                $body = '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                    . ' style="color:inherit !important; text-decoration:underline !important;">'
                    . $body . '</a>';
            }
            $html .= '<li style="' . $itemstyle . '">' . $body . '</li>';
        }

        $html .= '</ol>';
        $html .= '</div>';
        return $html;
    }

    /**
     * FontAwesome icon class matching a file's extension. Returns a generic
     * file icon when nothing matches.
     *
     * @param string $url      The file URL (used to sniff the extension).
     * @param string $typehint Optional explicit type key overriding extension sniff.
     * @return string
     */
    private static function file_icon_class(string $url, string $typehint = ''): string {
        $typemap = [
            'pdf'   => 'fa-file-pdf-o',
            'doc'   => 'fa-file-word-o',
            'docx'  => 'fa-file-word-o',
            'word'  => 'fa-file-word-o',
            'xls'   => 'fa-file-excel-o',
            'xlsx'  => 'fa-file-excel-o',
            'csv'   => 'fa-file-excel-o',
            'excel' => 'fa-file-excel-o',
            'ppt'   => 'fa-file-powerpoint-o',
            'pptx'  => 'fa-file-powerpoint-o',
            'key'   => 'fa-file-powerpoint-o',
            'slides' => 'fa-file-powerpoint-o',
            'jpg'   => 'fa-file-image-o',
            'jpeg'  => 'fa-file-image-o',
            'png'   => 'fa-file-image-o',
            'gif'   => 'fa-file-image-o',
            'svg'   => 'fa-file-image-o',
            'webp'  => 'fa-file-image-o',
            'image' => 'fa-file-image-o',
            'mp4'   => 'fa-file-video-o',
            'mov'   => 'fa-file-video-o',
            'avi'   => 'fa-file-video-o',
            'webm'  => 'fa-file-video-o',
            'video' => 'fa-file-video-o',
            'mp3'   => 'fa-file-audio-o',
            'wav'   => 'fa-file-audio-o',
            'm4a'   => 'fa-file-audio-o',
            'audio' => 'fa-file-audio-o',
            'zip'   => 'fa-file-archive-o',
            'rar'   => 'fa-file-archive-o',
            '7z'    => 'fa-file-archive-o',
            'tar'   => 'fa-file-archive-o',
            'gz'    => 'fa-file-archive-o',
            'archive' => 'fa-file-archive-o',
            'txt'   => 'fa-file-text-o',
            'md'    => 'fa-file-text-o',
            'rtf'   => 'fa-file-text-o',
            'text'  => 'fa-file-text-o',
            'html'  => 'fa-file-code-o',
            'htm'   => 'fa-file-code-o',
            'js'    => 'fa-file-code-o',
            'php'   => 'fa-file-code-o',
            'py'    => 'fa-file-code-o',
            'css'   => 'fa-file-code-o',
            'code'  => 'fa-file-code-o',
        ];

        $hint = strtolower(trim($typehint));
        if ($hint !== '' && isset($typemap[$hint])) {
            return $typemap[$hint];
        }

        // Extension sniff — strip query/fragment, then take after the final dot.
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (preg_match('/\.([a-z0-9]{1,8})$/i', $path, $m)) {
            $ext = strtolower($m[1]);
            if (isset($typemap[$ext])) {
                return $typemap[$ext];
            }
        }
        return 'fa-file-o';
    }

    /**
     * Is this URL likely an image we can render inline as a thumbnail?
     *
     * @param string $url
     * @param string $typehint
     * @return bool
     */
    private static function file_is_image(string $url, string $typehint = ''): bool {
        if (strtolower(trim($typehint)) === 'image') {
            return true;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        return (bool) preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $path);
    }

    /**
     * Derive a display title from the URL's filename if no title was provided.
     *
     * @param string $url
     * @return string
     */
    private static function file_title_from_url(string $url): string {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $base = basename($path);
        return $base !== '' ? $base : $url;
    }

    /**
     * Render a configurable file list: list / tile / thumbs display modes.
     *
     * @param array $config Decoded configdata: `heading`, `display` (list|tile|thumbs),
     *                     `items[{url,title,description,type}]`.
     * @return string HTML fragment.
     */
    public static function render_files(array $config): string {
        $heading = (string) ($config['heading'] ?? get_string('files_default_heading', 'local_byblos'));
        $display = (string) ($config['display'] ?? 'list');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($display, ['list', 'tile', 'thumbs'], true)) {
            $display = 'list';
        }

        $html = '<div class="byblos-section-files byblos-files-display-' . s($display)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-files-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('nofiles', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= match ($display) {
            'tile'   => self::render_files_tile($items),
            'thumbs' => self::render_files_thumbs($items),
            default  => self::render_files_list($items),
        };

        $html .= '</div>';
        return $html;
    }

    /**
     * Compact vertical list — one row per file with icon, title, description.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_list(array $items): string {
        $html = '<ul class="byblos-files-list" style="list-style:none !important;'
            . ' padding:0 !important; margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $icon  = self::file_icon_class($url, (string) ($it['type'] ?? ''));

            $html .= '<li class="byblos-files-list-item" style="display:flex !important;'
                . ' align-items:flex-start !important; gap:0.75rem !important;'
                . ' padding:0.6rem 0 !important; border-bottom:1px solid #eef1f4 !important;">';
            $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                . ' style="font-size:1.6rem !important; color:#6c757d !important;'
                . ' width:1.8rem !important; text-align:center !important; flex-shrink:0 !important;'
                . ' line-height:1.2 !important;"></i>';
            $html .= '<div style="flex:1 !important; min-width:0 !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' style="font-weight:600 !important; text-decoration:none !important;'
                . ' color:#0d6efd !important;">' . s($title) . '</a>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-item-desc" style="font-size:0.85rem !important;'
                    . ' color:#666 !important; margin-top:0.1rem !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Card grid — each file as a card with prominent icon, title, description.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_tile(array $items): string {
        $html = '<div class="byblos-files-tiles row" style="margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $icon  = self::file_icon_class($url, (string) ($it['type'] ?? ''));

            $html .= '<div class="col-md-4 col-sm-6 mb-3" style="padding:0.5rem !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' class="byblos-files-tile-card"'
                . ' style="display:block !important; text-decoration:none !important; color:inherit !important;'
                . ' background:#ffffff !important; border:1px solid #e9ecef !important;'
                . ' border-radius:0.5rem !important; padding:1.25rem !important;'
                . ' transition:transform 0.15s, box-shadow 0.15s !important; height:100% !important;">';
            $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                . ' style="display:block !important; font-size:2.5rem !important;'
                . ' color:#0d6efd !important; margin-bottom:0.6rem !important;"></i>';
            $html .= '<div class="byblos-files-tile-title" style="font-weight:600 !important;'
                . ' color:#212529 !important; font-size:0.95rem !important;'
                . ' word-break:break-word !important;">' . s($title) . '</div>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-tile-desc" style="font-size:0.8rem !important;'
                    . ' color:#666 !important; margin-top:0.35rem !important;">' . s($desc) . '</div>';
            }
            $html .= '</a></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Thumbnail grid — image previews inline, others fall back to big icon.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_thumbs(array $items): string {
        $html = '<div class="byblos-files-thumbs row" style="margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $type  = (string) ($it['type'] ?? '');
            $isimage = self::file_is_image($url, $type);
            $icon  = self::file_icon_class($url, $type);

            $html .= '<div class="col-md-3 col-sm-4 col-6 mb-3" style="padding:0.35rem !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' class="byblos-files-thumb-card"'
                . ' style="display:block !important; text-decoration:none !important; color:inherit !important;'
                . ' background:#ffffff !important; border:1px solid #e9ecef !important;'
                . ' border-radius:0.5rem !important; overflow:hidden !important;'
                . ' transition:transform 0.15s, box-shadow 0.15s !important;">';

            // Preview area: image or big icon.
            $html .= '<div class="byblos-files-thumb-media"'
                . ' style="aspect-ratio:4/3 !important; width:100% !important;'
                . ' display:flex !important; align-items:center !important; justify-content:center !important;'
                . ' background:#f8f9fa !important; overflow:hidden !important;">';
            if ($isimage) {
                $html .= '<img src="' . s($url) . '" alt="' . s($title) . '"'
                    . ' style="width:100% !important; height:100% !important; object-fit:cover !important;">';
            } else {
                $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                    . ' style="font-size:3rem !important; color:#6c757d !important;"></i>';
            }
            $html .= '</div>';

            $html .= '<div style="padding:0.6rem 0.75rem !important;">';
            $html .= '<div class="byblos-files-thumb-title" style="font-weight:600 !important;'
                . ' font-size:0.85rem !important; color:#212529 !important;'
                . ' white-space:nowrap !important; overflow:hidden !important;'
                . ' text-overflow:ellipsis !important;">' . s($title) . '</div>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-thumb-desc" style="font-size:0.75rem !important;'
                    . ' color:#666 !important; white-space:nowrap !important; overflow:hidden !important;'
                    . ' text-overflow:ellipsis !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
            $html .= '</a></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Parse a YouTube URL (or bare video id) into its 11-character video id.
     *
     * Accepts all the common forms:
     *   - https://www.youtube.com/watch?v=VIDEO_ID
     *   - https://www.youtube.com/watch?v=VIDEO_ID&t=123
     *   - https://youtu.be/VIDEO_ID
     *   - https://youtu.be/VIDEO_ID?t=60
     *   - https://www.youtube.com/embed/VIDEO_ID
     *   - https://www.youtube.com/shorts/VIDEO_ID
     *   - https://www.youtube.com/live/VIDEO_ID
     *   - VIDEO_ID (raw 11-char id)
     *
     * @param string $input
     * @return string|null 11-character video id, or null if the input can't be parsed.
     */
    public static function parse_youtube_id(string $input): ?string {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // Raw 11-character id.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        // Match: youtu.be short form.
        if (preg_match('~^https?://youtu\.be/([A-Za-z0-9_-]{11})~i', $input, $m)) {
            return $m[1];
        }

        // Match: youtube.com/watch?v=ID.
        if (preg_match('~[?&]v=([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        // Match: youtube.com/embed/ID, /shorts/ID, /live/ID, /v/ID.
        if (
            preg_match(
                '~^https?://(?:www\.)?youtube\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~i',
                $input,
                $m
            )
        ) {
            return $m[1];
        }

        return null;
    }

    /**
     * Render a YouTube embed with optional heading, caption, start offset, and
     * layout (full / center / left / right). Left and right layouts float the
     * video alongside body rich-text.
     *
     * @param array $config Decoded configdata: `url`, `heading`, `description`,
     *                      `start` (seconds), `alignment` (full|center|left|right),
     *                      `body` (rich HTML shown beside video in left/right layouts).
     * @return string HTML fragment.
     */
    public static function render_youtube(array $config): string {
        $url       = (string) ($config['url'] ?? '');
        $heading   = (string) ($config['heading'] ?? '');
        $desc      = (string) ($config['description'] ?? '');
        $start     = (int) ($config['start'] ?? 0);
        $alignment = (string) ($config['alignment'] ?? 'full');
        $body      = (string) ($config['body'] ?? '');

        if (!in_array($alignment, ['full', 'center', 'left', 'right'], true)) {
            $alignment = 'full';
        }

        $html = '<div class="byblos-section-youtube byblos-youtube-align-' . s($alignment)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-youtube-heading" style="margin-bottom:0.75rem !important;">'
                . s($heading) . '</h2>';
        }

        $videoid = self::parse_youtube_id($url);
        if ($videoid === null) {
            $html .= '<div class="alert alert-warning" style="margin:0 !important;">'
                . get_string('youtube_invalid', 'local_byblos') . '</div>';
            $html .= '</div>';
            return $html;
        }

        // Build the embed URL — privacy-enhanced domain + optional start offset.
        $embedurl = 'https://www.youtube-nocookie.com/embed/' . $videoid . '?rel=0';
        if ($start > 0) {
            $embedurl .= '&start=' . $start;
        }

        // The video frame (wrapper + iframe) — same markup for every layout.
        $frame = '<div class="byblos-youtube-frame"'
            . ' style="position:relative !important; width:100% !important;'
            . ' aspect-ratio:16/9 !important; background:#000 !important;'
            . ' border-radius:0.5rem !important; overflow:hidden !important;'
            . ' box-shadow:0 2px 12px rgba(0,0,0,0.12) !important;">'
            . '<iframe src="' . s($embedurl) . '"'
            . ' style="position:absolute !important; inset:0 !important;'
            . ' width:100% !important; height:100% !important; border:0 !important;"'
            . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
            . ' allowfullscreen loading="lazy"'
            . ' title="' . s($heading !== '' ? $heading : 'YouTube video') . '"></iframe>'
            . '</div>';

        $captionhtml = $desc !== ''
            ? '<p class="byblos-youtube-desc" style="margin:0.75rem 0 0 0 !important;'
                . ' font-size:1.05rem !important; color:#6c757d !important;'
                . ' font-style:italic !important; line-height:1.5 !important;">'
                . s($desc) . '</p>'
            : '';

        $bodyhtml = $body !== ''
            ? '<div class="byblos-youtube-body" style="min-width:0 !important;">' . $body . '</div>'
            : '';

        // Per-alignment layout.
        if ($alignment === 'left' || $alignment === 'right') {
            // Two-column grid — video on one side, rich body on the other.
            // Stacks on narrow screens via the `grid-template-columns` min().
            $order = ($alignment === 'left') ? 1 : 2;
            $bodyorder = ($alignment === 'left') ? 2 : 1;
            $sidebody = $body !== '' ? $bodyhtml : '<div class="byblos-youtube-body text-muted"'
                . ' style="min-width:0 !important;"><em>'
                . get_string('youtube_body_placeholder', 'local_byblos') . '</em></div>';
            $html .= '<div class="byblos-youtube-split"'
                . ' style="display:grid !important; gap:1.25rem !important;'
                . ' grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)) !important;'
                . ' align-items:start !important;">';
            $html .= '<div class="byblos-youtube-media" style="order:' . $order . ' !important;'
                . ' min-width:0 !important;">' . $frame . $captionhtml . '</div>';
            // Inject order via wrapper since the helper above doesn't know about it.
            $sidebody = preg_replace(
                '/class="byblos-youtube-body/',
                'style="order:' . $bodyorder . ' !important;" class="byblos-youtube-body',
                $sidebody,
                1
            );
            $html .= $sidebody;
            $html .= '</div>';
        } else if ($alignment === 'center') {
            $html .= '<div class="byblos-youtube-center-wrap"'
                . ' style="max-width:720px !important; margin:0 auto !important;">';
            $html .= '<div class="byblos-youtube-media">' . $frame . $captionhtml . '</div>';
            if ($bodyhtml !== '') {
                $html .= '<div style="margin-top:1.75rem !important;">' . $bodyhtml . '</div>';
            }
            $html .= '</div>';
        } else {
            // Full width — video first, then body below.
            $html .= '<div class="byblos-youtube-media">' . $frame . $captionhtml . '</div>';
            if ($bodyhtml !== '') {
                $html .= '<div style="margin-top:1.75rem !important;">' . $bodyhtml . '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }
    // Pagenav section.

    /**
     * Render a page-navigation widget.
     *
     * Resolves a target list of portfolio pages from either a collection
     * (ordered) or a manual list of page IDs (preserving the caller-supplied
     * order), filters for viewer-accessibility, and renders them in one of
     * four display modes (tabs, pills, cards, next/prev).
     *
     * @param array $config        Decoded configdata:
     *                             `heading`, `source` (collection|manual),
     *                             `collectionid`, `pageids[]`, `display`
     *                             (tabs|pills|cards|nextprev), `show_descriptions`.
     * @param int   $currentpageid Host page id (for active-state detection and
     *                             prev/next navigation). 0 when unknown.
     * @return string HTML fragment.
     */
    public static function render_pagenav(array $config, int $currentpageid = 0): string {
        global $USER;

        $heading    = (string) ($config['heading'] ?? '');
        $source     = (string) ($config['source'] ?? 'collection');
        $display    = (string) ($config['display'] ?? 'pills');
        $showdescs  = !empty($config['show_descriptions']);

        if (!in_array($source, ['collection', 'manual'], true)) {
            $source = 'collection';
        }
        if (!in_array($display, ['tabs', 'pills', 'cards', 'nextprev'], true)) {
            $display = 'pills';
        }

        $wrapclass = 'byblos-section-pagenav byblos-pagenav-display-' . $display;
        $html = '<div class="' . s($wrapclass) . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-pagenav-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        // Resolve the page list; each candidate is filtered by share::can_view_page.
        $pages = [];
        if ($source === 'collection') {
            $collectionid = (int) ($config['collectionid'] ?? 0);
            if ($collectionid > 0 && \local_byblos\share::can_view_collection((int) $USER->id, $collectionid)) {
                $pages = collection::get_pages($collectionid);
            }
        } else {
            $pageids = is_array($config['pageids'] ?? null) ? $config['pageids'] : [];
            foreach ($pageids as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                $p = page::get($pid);
                if (!$p) {
                    continue;
                }
                $pages[] = $p;
            }
        }
        $pages = array_values(array_filter(
            $pages,
            fn($p) => \local_byblos\share::can_view_page((int) $USER->id, (int) $p->id)
        ));

        if (empty($pages)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('pagenav_empty', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        switch ($display) {
            case 'tabs':
                $html .= self::render_pagenav_nav($pages, $currentpageid, 'nav-tabs');
                break;
            case 'cards':
                $html .= self::render_pagenav_cards($pages, $showdescs);
                break;
            case 'nextprev':
                $html .= self::render_pagenav_nextprev($pages, $currentpageid);
                break;
            case 'pills':
            default:
                $html .= self::render_pagenav_nav($pages, $currentpageid, 'nav-pills');
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a Bootstrap nav list (tabs or pills) of page links.
     *
     * @param array  $pages         Array of page records.
     * @param int    $currentpageid Host page id used to mark the active link.
     * @param string $navclass      Either `nav-tabs` or `nav-pills`.
     * @return string
     */
    private static function render_pagenav_nav(array $pages, int $currentpageid, string $navclass): string {
        $html = '<ul class="nav ' . s($navclass) . '" role="tablist">';
        foreach ($pages as $p) {
            $isactive = ((int) $p->id === (int) $currentpageid);
            $url = '/local/byblos/page.php?id=' . (int) $p->id;
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link' . ($isactive ? ' active' : '') . '" href="'
                . s($url) . '"' . ($isactive ? ' aria-current="page"' : '') . '>'
                . s((string) ($p->title ?? '')) . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Render a responsive card grid of page links.
     *
     * @param array $pages
     * @param bool  $showdescs If true, include the page description on each card.
     * @return string
     */
    private static function render_pagenav_cards(array $pages, bool $showdescs): string {
        $html = '<div class="row byblos-pagenav-cards">';
        foreach ($pages as $p) {
            $title = (string) ($p->title ?? '');
            $desc  = (string) ($p->description ?? '');
            $url   = '/local/byblos/page.php?id=' . (int) $p->id;

            $html .= '<div class="col-md-4 col-sm-6 mb-3">';
            $html .= '<div class="card h-100 byblos-pagenav-card"'
                . ' style="border:1px solid #e9ecef !important; border-radius:0.5rem !important;">';
            $html .= '<div class="card-body" style="display:flex !important;'
                . ' flex-direction:column !important;">';
            $html .= '<h5 class="card-title" style="font-size:1rem !important;'
                . ' font-weight:600 !important; margin-bottom:0.5rem !important;">'
                . s($title) . '</h5>';
            if ($showdescs && $desc !== '') {
                $html .= '<p class="card-text text-muted small"'
                    . ' style="flex:1 !important;">' . s($desc) . '</p>';
            }
            $html .= '<a href="' . s($url) . '" class="btn btn-sm btn-outline-primary mt-auto"'
                . ' style="align-self:flex-start !important;">'
                . get_string('pagenav_viewpage', 'local_byblos') . '</a>';
            $html .= '</div></div></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render previous / next buttons relative to $currentpageid.
     *
     * @param array $pages
     * @param int   $currentpageid
     * @return string
     */
    private static function render_pagenav_nextprev(array $pages, int $currentpageid): string {
        $prev = null;
        $next = null;
        $pages = array_values($pages);
        $count = count($pages);
        for ($i = 0; $i < $count; $i++) {
            if ((int) $pages[$i]->id === (int) $currentpageid) {
                if ($i > 0) {
                    $prev = $pages[$i - 1];
                }
                if ($i < $count - 1) {
                    $next = $pages[$i + 1];
                }
                break;
            }
        }

        // If the current page isn't in the list, show first as next (common
        // on preview/editor where currentpageid may not match a list member).
        if ($prev === null && $next === null && $count > 0 && $currentpageid === 0) {
            $next = $pages[0];
        }

        $html = '<div class="byblos-pagenav-nextprev d-flex justify-content-between align-items-stretch"'
            . ' style="gap:1rem !important;">';

        if ($prev !== null) {
            $url = '/local/byblos/page.php?id=' . (int) $prev->id;
            $html .= '<a href="' . s($url) . '" class="btn btn-outline-secondary flex-fill text-left"'
                . ' style="padding:1rem !important;">'
                . '<div class="small text-muted"><i class="fa fa-chevron-left"></i> '
                . get_string('pagenav_previous', 'local_byblos') . '</div>'
                . '<div style="font-weight:600 !important;">' . s((string) ($prev->title ?? '')) . '</div>'
                . '</a>';
        } else {
            $html .= '<span class="flex-fill"></span>';
        }

        if ($next !== null) {
            $url = '/local/byblos/page.php?id=' . (int) $next->id;
            $html .= '<a href="' . s($url) . '" class="btn btn-outline-secondary flex-fill text-right"'
                . ' style="padding:1rem !important;">'
                . '<div class="small text-muted">' . get_string('pagenav_next', 'local_byblos')
                . ' <i class="fa fa-chevron-right"></i></div>'
                . '<div style="font-weight:600 !important;">' . s((string) ($next->title ?? '')) . '</div>'
                . '</a>';
        } else {
            $html .= '<span class="flex-fill"></span>';
        }

        $html .= '</div>';
        return $html;
    }
}
