<?php
include 'security.php';

if (!function_exists('eur')) {
    // 12 -> "12€", 12.5 -> "12.50€" (no thousands separator)
    function eur($v, $signed = false) {
        $dec  = (abs($v - round($v)) > 0.001) ? 2 : 0;
        $sign = ($signed && $v > 0) ? '+' : '';
        return $sign . number_format($v, $dec, '.', '') . '€';
    }
}

if (!function_exists('activity_icon')) {
    // Returns the icon markup (FontAwesome class or inline SVG) for a top-up type / product name
    function activity_icon($type, $label) {
        $l  = mb_strtolower((string)$label);
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

        if ($type === 'PURCHASE') {
            $map = [
                '/member|cotisation|adh[eé]sion|licen[cs]e/' => $fa('fa-id-card'),
                '/peanut|cacahu[eè]te|chips/'                => $svg_peanut,
                '/saucisson|sausage/'                        => $svg_saucisson,
                '/beer|bi[eè]re|soft/'                       => $fa('fa-beer'),
                '/cocktail|coktail/'                         => $fa('fa-cocktail'),
                '/repas|meal|food/'                          => $fa('fa-utensils'),
                '/glaci[eè]re|fridge|cooler/'                => $fa('fa-snowflake'),
                '/sho{1,2}t/'                                => $svg_shot,
                '/cigarette|cigarettes|clope|clopes|tabac|tobacco|smoke|smoking/' => $svg_cigarette,
            ];
            foreach ($map as $re => $html) {
                if (preg_match($re, $l)) return $html;
            }
            return $fa('fa-shopping-basket');
        }
        if (preg_match('/refund|rembours/', $l))                    return $fa('fa-undo');
        if (preg_match('/cash|esp[eè]ces?/', $l))                   return $fa('fa-money-bill-wave');
        if (preg_match('/sumup|stripe|card|carte|cb/', $l))         return $fa('fa-credit-card');
        if (preg_match('/bank|banque|transfer|virement|wire/', $l)) return $fa('fa-university');
        return $fa('fa-coins'); // unknown top-up type
    }
}

?>

<article id="products">
    <h2 class="major">Products</h2>

    <style>
        #products { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #products .num { font-variant-numeric: tabular-nums; }

        /* Cards (forms) */
        #products .am-card { border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            padding: 1.5rem 1.5rem 1.25rem; margin-bottom: 2rem; }
        #products .am-card h3 { display: flex; align-items: center; gap: .6rem; margin: 0 0 .25rem; font-size: 1.15rem; }
        #products .am-card h3 .icon:before { margin: 0; }
        #products .am-hint { margin: 0 0 1.25rem; font-size: .85rem; opacity: .65; }
        #products .am-card label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #products .am-card button.fit { width: 100%; }

        /* Lists */
        #products .w-row { display: grid; align-items: center; gap: .5rem 1rem; padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #products .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(255,255,255,.08); }
        #products .w-ico .icon:before { margin: 0; }
        #products .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #products .w-label { font-weight: 600; line-height: 1.3; }
        #products .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #products .w-val { font-weight: 600; font-size: 1.05rem; white-space: nowrap; }
        #products .w-empty { text-align: center; padding: 1.5rem 0; opacity: .7; }
        #products .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; opacity: .8; vertical-align: middle; }
        #products .is-off { opacity: .5; }
        #products .ic-btn { background: none; box-shadow: none; border: 0; cursor: pointer; padding: 0; width: 2.5rem; height: 2.5rem;
            line-height: 2.5rem; font-size: 1.15rem; color: inherit; opacity: .85; text-align: center; }
        #products .ic-btn:hover { opacity: 1; background: none; }
        #products .ic-btn:before { margin: 0; }
        #products form.inline { display: inline; margin: 0; }

        #products .w-row { grid-template-columns: 2.5rem 1fr auto auto; }
        #products .w-val { color: inherit; }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <!-- New product -->
    <section class="am-card">
        <h3><span class="icon solid fa-plus-circle"></span> Add a product</h3>
        <p class="am-hint">It will be available in the sell form right away.</p>
        <form method="post" action="">
            <div class="fields">
                <div class="field half">
                    <label for="prod_name">Name</label>
                    <input type="text" name="prod_name" id="prod_name" placeholder="Beer, Cocktail, etc." required />
                </div>
                <div class="field half">
                    <label for="prod_price">Price (€)</label>
                    <input type="number" name="prod_price" id="prod_price" step="0.01" placeholder="0" min="1" max="1000" required />
                </div>
                <div class="field">
                    <button type="submit" name="add_product" class="primary fit">
                        <i class="fa fa-plus-circle"></i> Add product
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- Inventory -->
    <?php
        $n_active = 0; $n_hidden = 0;
        foreach ($all_prods as $p) { if ($p['IS_ACTIVE'] == 1) $n_active++; else $n_hidden++; }
    ?>
    <h3 style="margin-bottom:.25rem;">Inventory</h3>
    <p class="am-hint num"><?= $n_active ?> active<?php if ($n_hidden): ?> · <?= $n_hidden ?> hidden<?php endif; ?></p>

    <?php if (empty($all_prods)): ?>
        <p class="w-empty">No products yet. Add your first one above.</p>
    <?php else: ?>
        <?php foreach ($all_prods as $p): $on = $p['IS_ACTIVE'] == 1; ?>
            <div class="w-row <?= $on ? '' : 'is-off' ?>">
                <div class="w-ico" aria-hidden="true"><?= activity_icon('PURCHASE', $p['NAME']) ?></div>
                <div class="w-label">
                    <?= htmlspecialchars($p['NAME']) ?>
                    <?php if (!$on): ?><span class="w-tag">Hidden</span><?php endif; ?>
                </div>
                <div class="w-val num"><?= eur($p['PRICE']) ?></div>
                <form method="post" action="" class="inline">
                    <input type="hidden" name="id_product" value="<?= $p['ID_PRODUCT'] ?>">
                    <input type="hidden" name="set_active" value="<?= $on ? 0 : 1 ?>">
                    <button type="submit" name="toggle_prod"
                            class="ic-btn icon solid <?= $on ? 'fa-eye' : 'fa-eye-slash' ?>"
                            title="<?= $on ? 'Hide product' : 'Show product' ?>">
                        <span class="label"><?= $on ? 'Hide' : 'Show' ?></span>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</article>