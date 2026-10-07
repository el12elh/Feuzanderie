<?php
// Shared helpers - include once from every page: require_once __DIR__ . '/helpers.php';

if (!function_exists('eur')) {
    // 12 -> "12€", 12.5 -> "12.50€" (no thousands separator)
    function eur($v, $signed = false) {
        $dec  = (abs($v - round($v)) > 0.001) ? 2 : 0;
        $sign = ($signed && $v > 0) ? '+' : '';
        return $sign . number_format($v, $dec, '.', '') . '€';
    }
}

if (!function_exists('day_label')) {
    function day_label($datetime) {
        $d = date('Y-m-d', strtotime($datetime));
        if ($d === date('Y-m-d'))                      return 'Today';
        if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
        return date('d/m/Y', strtotime($datetime));
    }
}

if (!function_exists('member_initials')) {
    function member_initials($first, $last) {
        $a = mb_substr(trim((string)$first), 0, 1);
        $b = mb_substr(trim((string)$last), 0, 1);
        return htmlspecialchars(mb_strtoupper($a . $b));
    }
}

if (!function_exists('activity_icon')) {
    // Returns the icon markup (FontAwesome class or inline SVG) for a top-up type / product name
    function activity_icon($type, $label) {
        $l  = mb_strtolower((string)$label);
        if (class_exists('Normalizer')) $l = Normalizer::normalize($l, Normalizer::FORM_C); // e + combining accent -> è
        $fa = function ($c) { return '<span class="icon solid ' . $c . '"></span>'; };

        // Custom SVGs (FontAwesome has no peanut / saucisson); they inherit the circle colour
        $svg_peanut = '<svg viewBox="0 0 24 24" aria-hidden="true"><g transform="rotate(-45 12 12)" fill="currentColor">'
            . '<ellipse cx="7.5" cy="12" rx="5.5" ry="5"/><ellipse cx="16.5" cy="12" rx="5.5" ry="5"/></g>'
            . '<g fill="rgba(0,0,0,.35)"><circle cx="6.5" cy="15" r=".8"/><circle cx="9" cy="17" r=".8"/>'
            . '<circle cx="15" cy="7" r=".8"/><circle cx="17.5" cy="9" r=".8"/></g></svg>';
        $svg_saucisson = '<svg viewBox="0 0 24 24" aria-hidden="true"><g transform="rotate(-30 12 12)">'
            . '<rect x="4" y="8.5" width="16" height="7" rx="3.5" fill="currentColor"/>'
            . '<path d="M4 12H1.5M20 12h2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<path d="M9 9.5v5M12.5 9.5v5M16 9.5v5" stroke="rgba(0,0,0,.35)" stroke-width="1.2" stroke-linecap="round"/></g></svg>';

        $svg_shot = '<svg viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M5.5 3h13l-1.7 16.6a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8z" fill="currentColor"/>'
            . '<path d="M6.6 8.5h10.8" stroke="rgba(0,0,0,.35)" stroke-width="1.3" stroke-linecap="round"/></svg>';

        $svg_cigarette = '<svg viewBox="0 0 24 24" aria-hidden="true"><g transform="rotate(-30 12 12)">'
            . '<rect x="2" y="12" width="20" height="4" rx="1" fill="currentColor"/>'
            . '<rect x="2" y="12" width="6" height="4" rx="1" fill="rgba(0,0,0,.35)"/>'
            . '<path d="M17 9c-1.6-1.6 1.6-2.6 0-4.4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></g></svg>';

        if ($type === 'PURCHASE') {
            $map = [
                '/member|cotisation|adh[eé]sion|licen[cs]e/u' => $fa('fa-id-card'),
                '/peanut|cacahu[eè]te|chips/u'                => $svg_peanut,
                '/saucisson|sausage/u'                        => $svg_saucisson,
                '/beer|bi[eè]re|soft/u'                       => $fa('fa-beer'),
                '/cocktail|coktail/u'                         => $fa('fa-cocktail'),
                '/repas|meal|food/u'                          => $fa('fa-utensils'),
                '/glaci[eè]re|fridge|cooler/u'                => $fa('fa-snowflake'),
                '/sho{1,2}t/u'                                => $svg_shot,
                '/cigarette|cigarettes|clope|clopes|tabac|tobacco|smoke|smoking/u' => $svg_cigarette,
            ];
            foreach ($map as $re => $html) {
                if (preg_match($re, $l)) return $html;
            }
            return $fa('fa-shopping-basket');
        }
        if (preg_match('/refund|rembours/u', $l))                    return $fa('fa-undo');
        if (preg_match('/cash|esp[eè]ces?/u', $l))                   return $fa('fa-money-bill-wave');
        if (preg_match('/sumup|stripe|card|carte|cb/u', $l))         return $fa('fa-credit-card');
        if (preg_match('/bank|banque|transfer|virement|wire/u', $l)) return $fa('fa-university');
        return $fa('fa-coins'); // unknown top-up type
    }
}