<?php
include 'security.php';

// Helpers
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

<article id="amikale">
    <h2 class="major">Amikale</h2>

    <style>
        #amikale { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #amikale .num { font-variant-numeric: tabular-nums; }

        /* History */
        #amikale .w-history h3 { margin-bottom: .5rem; }
        #amikale .w-day { margin: 1.75rem 0 .4rem; font-size: .85rem; letter-spacing: 0; text-transform: none; opacity: .65; }
        #amikale .w-row { display: grid; grid-template-columns: 2.5rem 1fr auto; align-items: center; gap: 1rem;
            padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #amikale .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(42,201,134,.16); color: var(--ok); }
        #amikale .w-row.out .w-ico { background: rgba(255,95,109,.16); color: var(--bad); }
        #amikale .w-ico .icon:before { margin: 0; }
        #amikale .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #amikale .w-label { font-weight: 600; line-height: 1.3; }
        #amikale .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #amikale .w-val { font-weight: 600; font-size: 1.05rem; color: var(--ok); white-space: nowrap; }
        #amikale .w-row.out .w-val { color: var(--bad); }
        #amikale .w-empty { text-align: center; padding: 2.5rem 0; opacity: .7; }
        #amikale .w-net { display: flex; justify-content: space-between; padding: 1.1rem 0 0; font-weight: 600; }



        /* Form cards (green = top-up, red = sell, expense stays neutral) */
        #amikale .am-card { border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            padding: 1.5rem 1.5rem 1.25rem; margin-bottom: 1.25rem; }
        #amikale .am-card h3 { display: flex; align-items: center; gap: .6rem; margin: 0 0 .25rem; font-size: 1.15rem; }
        #amikale .am-card h3 .icon:before { margin: 0; }
        #amikale .am-hint { margin: 0 0 1.25rem; font-size: .85rem; opacity: .65; }
        #amikale .am-card label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #amikale .am-card button.fit { width: 100%; }

        #amikale .am-topup h3 .icon { color: var(--ok); }
        #amikale .am-sell h3 .icon { color: var(--bad); }
        #amikale .am-topup button.primary { background-color: var(--ok); box-shadow: none; color: #0b2a1d; }
        #amikale .am-sell button.primary { background-color: var(--bad); box-shadow: none; color: #2a0a0e; }
        #amikale .am-card button.primary:hover { filter: brightness(1.08); }
        #amikale .am-card button.primary:active { filter: brightness(.94); }

        #amikale .w-receipt { display: inline-block; margin-top: .3rem; font-size: .8rem; text-decoration: none;
            border-bottom: 1px dashed currentColor; }
        #amikale .w-row.in .w-receipt { color: var(--ok); }
        #amikale .w-row.out .w-receipt { color: var(--bad); }
        #amikale .am-download { margin-top: 1.5rem; }

        @media (max-width: 736px) {
            #amikale .am-card { padding: 1.25rem 1rem 1rem; }
        }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <!-- =================== Sell Product =================== -->
    <section id="sell-product" class="am-card am-sell">
        <h3><span class="icon solid fa-shopping-cart"></span> Sell a product</h3>
        <p class="am-hint">Pick one or more members, a product and a quantity. Each selected member is charged.</p>
        <form method="post" id="sellForm">
            <div class="fields">
                <div class="field">
                    <label for="members">Members</label>
                    <select id="members" name="id_customer[]" multiple required>
                        <?php foreach ($customers_1 as $c):
                            $plus = $c['BALANCE'] > 0 ? '+' : '';
                        ?>
                        <option value="<?= $c['ID_CUSTOMER']; ?>"
                                data-balance="<?= htmlspecialchars($c['BALANCE'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-trusted="<?= (int)$c['IS_TRUSTED']; ?>">
                            <?= htmlspecialchars($c['FIRST_NAME'] . ' ' . $c['LAST_NAME']) . ' ' . eur($c['BALANCE'], true); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field half">
                    <label for="product">Product</label>
                    <select name="id_product" id="product" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['ID_PRODUCT']; ?>"
                                data-price="<?= htmlspecialchars($p['PRICE'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($p['NAME']) . ' (' . eur($p['PRICE']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field half">
                    <label for="qty">Quantity</label>
                    <select name="qty" id="qty" required>
                        <option value="">-- Select Quantity --</option>
                        <?php for ($i = 1; $i <= 500; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                <div class="field">
                    <button type="submit" name="sell" class="primary fit">
                        <i class="fa fa-shopping-cart"></i> Sell
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- =================== Top-Up Wallet =================== -->
    <section id="topup-wallet" class="am-card am-topup">
        <h3><span class="icon solid fa-wallet"></span> Top up a wallet</h3>
        <p class="am-hint">Add credit to a member after receiving the payment.</p>
        <form method="post">
            <div class="fields">
                <div class="field">
                    <label for="topup-member">Member</label>
                    <select name="id_customer" id="topup-member" class="js-member" required>
                        <option value="">-- Select Member --</option>
                        <?php foreach ($customers_2 as $c):
                            $plus = $c['BALANCE'] > 0 ? '+' : '';
                        ?>
                        <option value="<?= $c['ID_CUSTOMER']; ?>">
                            <?= htmlspecialchars($c['FIRST_NAME'] . ' ' . $c['LAST_NAME']) . ' ' . eur($c['BALANCE'], true); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field half">
                    <label for="type">Method</label>
                    <select name="id_type" id="type" required>
                        <option value="">-- Select Method --</option>
                        <?php foreach ($topup_types as $t): ?>
                        <option value="<?= $t['ID_TOPUP_TYPE']; ?>"><?= htmlspecialchars($t['NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field half">
                    <label for="amount">Amount</label>
                    <select name="amount" id="amount" required>
                        <option value="">-- Select Amount --</option>
                        <?php for ($i = 1; $i <= 200; $i++): ?>
                        <option value="<?= $i ?>">+<?= $i ?>€</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                <div class="field">
                    <button type="submit" name="do_topup" class="primary fit">
                        <i class="fa fa-wallet"></i> Top up
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- =================== Purchase =================== -->
    <section id="purchase" class="am-card am-expense">
        <h3><span class="icon solid fa-receipt"></span> Record an expense</h3>
        <p class="am-hint">Money spent by Amikale: stock, restaurant, bar&hellip; Attach the receipt if you have one.</p>
        <form method="post" enctype="multipart/form-data">
            <div class="fields">
                <div class="field">
                    <label for="comment">What was it for?</label>
                    <input type="text" name="comment" id="comment" maxlength="100" placeholder="Stock, Resto, Bar, etc." required />
                </div>
                <div class="field half">
                    <label for="expense-amount">Amount (€)</label>
                    <input type="number" name="amount" id="expense-amount" step="0.01" placeholder="0" min="1" required />
                </div>
                <div class="field half">
                    <label for="receipt">Receipt (optional)</label>
                    <input type="file" name="receipt" id="receipt" accept="image/*,.pdf" />
                </div>
                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                <div class="field">
                    <button type="submit" name="do_purchase" class="primary fit">
                        <i class="fa fa-receipt"></i> Save expense
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- =================== Latest Transactions =================== -->
    <section id="latest-transactions" class="w-history">
        <h3>Last 24 hours</h3>

        <?php if (empty($transactions)): ?>
            <p class="w-empty">No transactions in the last 24 hours.</p>
        <?php else: ?>
            <?php $current_day = null; ?>
            <?php foreach ($transactions as $tr): ?>
                <?php
                    $is_in = $tr['AMOUNT'] > 0;
                    $day   = date('Y-m-d', strtotime($tr['CREATED_AT']));
                    $who   = trim($tr['FIRST_NAME'] . ' ' . ($tr['LAST_NAME'] ?? ''));
                    $by    = trim(($tr['BY_FIRST_NAME'] ?? '') . ' ' . ($tr['BY_LAST_NAME'] ?? ''));
                    $type  = $is_in ? 'TOPUP' : 'PURCHASE';
                ?>
                <?php if ($day !== $current_day): $current_day = $day; ?>
                    <h5 class="w-day"><?= htmlspecialchars(day_label($tr['CREATED_AT'])) ?></h5>
                <?php endif; ?>

                <div class="w-row <?= $is_in ? 'in' : 'out' ?>">
                    <div class="w-ico" aria-hidden="true">
                        <?= activity_icon($type, $tr['LABEL']) ?>
                    </div>
                    <div>
                        <div class="w-label"><?= htmlspecialchars($who) ?></div>
                        <div class="w-meta num">
                            <?php if (!empty($tr['LABEL'])): ?><?= htmlspecialchars($tr['LABEL']) ?> · <?php endif; ?>
                            <?= date('H:i', strtotime($tr['CREATED_AT'])) ?>
                            <?php if ($by !== ''): ?>· by <?= htmlspecialchars($by) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($tr['RECEIPT_PATH'])): ?>
                            <a href="javascript:void(0);" class="w-receipt"
                               onclick="viewReceipt(<?= htmlspecialchars(json_encode($tr['RECEIPT_PATH']), ENT_QUOTES) ?>)">
                                <i class="fa fa-paperclip"></i> Receipt
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="w-val num"><?= eur($tr['AMOUNT'], true) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <ul class="actions am-download">
            <li><a href="export_transactions" class="button primary icon solid fa-download">Download all transactions</a></li>
        </ul>
    </section>
</article>