<?php
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_rows = $pdo->query("SELECT (
    (SELECT COUNT(*) FROM wallet_topup WHERE ID_TOPUP_TYPE IN (1, 2, 3, 4))
    +
    (SELECT COUNT(*) FROM purchases)
) AS total_transactions")->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $limit));

// Global totals (money in / money out)
$totals = $pdo->query("SELECT
    (SELECT COALESCE(SUM(t.AMOUNT), 0) FROM wallet_topup t WHERE t.ID_TOPUP_TYPE IN (1, 2, 3, 4)) AS TOTAL_IN,
    (SELECT COALESCE(SUM(p.AMOUNT), 0) FROM purchases p) AS TOTAL_OUT
")->fetch();
$global_in    = (int)$totals['TOTAL_IN'];
$global_out   = -(int) round($totals['TOTAL_OUT']);   // shown as a negative number
$global_total = $global_in + $global_out;

$sql = "SELECT
    c.FIRST_NAME AS FIRST_NAME,
    c.LAST_NAME AS LAST_NAME,
    t.AMOUNT AS AMOUNT,
    r.NAME AS LABEL,
    t.CREATED_AT,
    a.FIRST_NAME AS BY_FIRST_NAME,
    a.LAST_NAME AS BY_LAST_NAME,
    NULL AS RECEIPT_PATH
FROM wallet_topup t
JOIN customers c ON t.ID_CUSTOMER = c.ID_CUSTOMER
LEFT JOIN users_customers uc ON t.ID_USER = uc.ID_USER
LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
LEFT JOIN ref_topup_type r ON t.ID_TOPUP_TYPE = r.ID_TOPUP_TYPE
WHERE t.ID_TOPUP_TYPE IN (1, 2, 3, 4)
UNION
SELECT
    '-Amikale' AS FIRST_NAME,
    '' AS LAST_NAME,
    -p.AMOUNT,
    p.COMMENT AS LABEL,
    p.CREATED_AT,
    a.FIRST_NAME AS BY_FIRST_NAME,
    a.LAST_NAME AS BY_LAST_NAME,
    RECEIPT_PATH
FROM purchases p
LEFT JOIN users_customers uc ON p.ID_USER = uc.ID_USER
LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
ORDER BY CREATED_AT DESC, FIRST_NAME, LAST_NAME, LABEL
LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$cash_flow = $stmt->fetchAll();

// Helpers
if (!function_exists('eur')) {
    // 12 -> "12€", 12.5 -> "12.50€"
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

$state_class = $global_total < 0 ? 'is-negative' : 'is-positive';
?>

<article id="cashflow">
    <h2 class="major">Cash Flow</h2>

    <style>
        #cashflow { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #cashflow .num { font-variant-numeric: tabular-nums; }

        /* Balance */
        #cashflow .w-balance { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1.5rem;
            padding: 1.75rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            border-left: 6px solid var(--ok); margin-bottom: 1.25rem; }
        #cashflow .w-balance.is-negative { border-left-color: var(--bad); }
        #cashflow .w-name { margin: 0 0 .25rem; font-size: .95rem; opacity: .8; }
        #cashflow .w-amount { margin: 0; font-size: 3.25rem; line-height: 1; font-weight: 600; letter-spacing: -.02em; color: var(--ok); }
        #cashflow .is-negative .w-amount { color: var(--bad); }
        #cashflow .w-status { margin: .6rem 0 0; font-size: .9rem; color: var(--ok); }
        #cashflow .is-negative .w-status { color: var(--bad); font-weight: 600; }

        /* Stats */
        #cashflow .w-stats { display: flex; gap: 2rem; }
        #cashflow .w-stat span { display: block; font-size: .8rem; opacity: .7; }
        #cashflow .w-stat strong { font-size: 1.15rem; }
        #cashflow .w-stat .in  { color: var(--ok); }
        #cashflow .w-stat .out { color: var(--bad); }

        /* History */
        #cashflow .w-history h3 { margin-bottom: .5rem; }
        #cashflow .w-day { margin: 1.75rem 0 .4rem; font-size: .85rem; letter-spacing: 0; text-transform: none; opacity: .65; }
        #cashflow .w-row { display: grid; grid-template-columns: 2.5rem 1fr auto; align-items: center; gap: 1rem;
            padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #cashflow .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(42,201,134,.16); color: var(--ok); }
        #cashflow .w-row.out .w-ico { background: rgba(255,95,109,.16); color: var(--bad); }
        #cashflow .w-ico .icon:before { margin: 0; }
        #cashflow .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #cashflow .w-label { font-weight: 600; line-height: 1.3; }
        #cashflow .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #cashflow .w-val { font-weight: 600; font-size: 1.05rem; color: var(--ok); white-space: nowrap; }
        #cashflow .w-row.out .w-val { color: var(--bad); }
        #cashflow .w-receipt { display: inline-block; margin-top: .3rem; font-size: .8rem; text-decoration: none;
            border-bottom: 1px dashed currentColor; color: inherit; opacity: .85; }
        #cashflow .w-row.in .w-receipt { color: var(--ok); }
        #cashflow .w-row.out .w-receipt { color: var(--bad); }
        #cashflow .w-pager { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem; }
        #cashflow .w-empty { text-align: center; padding: 2.5rem 0; opacity: .7; }
        #cashflow .w-net { display: flex; justify-content: space-between; padding: 1.1rem 0 0; font-weight: 600; }

        @media (max-width: 480px) {
            #cashflow .w-amount { font-size: 2.6rem; }
            #cashflow .w-stats { width: 100%; justify-content: space-between; }
        }
    </style>

    <!-- Global total -->
    <section class="w-balance <?= $state_class ?>">
        <div>
            <p class="w-name">Total cash</p>
            <h4 class="w-amount num"><?= eur($global_total, true) ?></h4>
        </div>
        <div class="w-stats">
            <div class="w-stat"><span>Money in</span><strong class="in num"><?= eur($global_in, true) ?></strong></div>
            <div class="w-stat"><span>Money out</span><strong class="out num"><?= eur($global_out) ?></strong></div>
        </div>
    </section>

    <!-- History -->
    <section class="w-history">
        <h3>Transactions</h3>

        <?php if (empty($cash_flow)): ?>
            <p class="w-empty">No activity yet.</p>
        <?php else: ?>
            <?php $current_day = null; ?>
            <?php foreach ($cash_flow as $tr): ?>
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

            <div class="w-pager">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>#cashflow" class="button small">Previous</a>
                <?php endif; ?>
                <span class="num">Page <?= $page ?> / <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>#cashflow" class="button small">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</article>