<?php
include 'security.php';

$id_customer = (int)($_GET['id'] ?? 0);

$customer   = null;
$activities = [];
$total_in   = 0;
$total_out  = 0;

// 1. Current balance
$stmt_bal = $pdo->prepare("SELECT FIRST_NAME, LAST_NAME, BALANCE, IS_TRUSTED FROM customers WHERE ID_CUSTOMER = ?");
$stmt_bal->execute([$id_customer]);
$customer = $stmt_bal->fetch();

if ($customer) {
    // 2. Top-ups + purchases
    $stmt_activity = $pdo->prepare("
        SELECT
            'TOPUP' AS TYPE,
            t.AMOUNT AS AMOUNT,
            r.NAME AS LABEL,
            t.CREATED_AT,
            a.FIRST_NAME AS BY_FIRST_NAME,
            a.LAST_NAME AS BY_LAST_NAME
        FROM wallet_topup t
        JOIN customers c ON t.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN users_customers uc ON t.ID_USER = uc.ID_USER
        LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
        LEFT JOIN ref_topup_type r ON t.ID_TOPUP_TYPE = r.ID_TOPUP_TYPE
        WHERE c.ID_CUSTOMER = ?

        UNION ALL

        SELECT
            'PURCHASE' AS TYPE,
            -(p.PRICE * tr.QUANTITY) AS AMOUNT,
            CONCAT(tr.QUANTITY, 'x ', p.NAME) AS LABEL,
            tr.CREATED_AT,
            a.FIRST_NAME AS BY_FIRST_NAME,
            a.LAST_NAME AS BY_LAST_NAME
        FROM transactions tr
        JOIN customers c ON tr.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN users_customers uc ON tr.ID_USER = uc.ID_USER
        LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
        LEFT JOIN ref_product p ON tr.ID_PRODUCT = p.ID_PRODUCT
        WHERE c.ID_CUSTOMER = ?

        ORDER BY CREATED_AT DESC
    ");
    $stmt_activity->execute([$id_customer, $id_customer]);
    $activities = $stmt_activity->fetchAll();

    foreach ($activities as $a) {
        if ($a['AMOUNT'] > 0) { $total_in  += $a['AMOUNT']; }
        else                  { $total_out += $a['AMOUNT']; }
    }
}

$balance      = $customer['BALANCE'] ?? 0;
$is_negative  = $balance < 0;
$is_trusted   = (int)($customer['IS_TRUSTED'] ?? 0) === 1;
$state_class  = $is_negative ? 'is-negative' : 'is-positive';
$net          = $total_in + $total_out;
?>

<article id="member">
    <h2 class="major">Member Profile</h2>

    <?php if (!$customer): ?>
        <p>Member not found.</p>
    <?php else: ?>

    <style>
        #member { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #member .num { font-variant-numeric: tabular-nums; }

        /* Balance */
        #member .w-balance { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1.5rem;
            padding: 1.75rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            border-left: 6px solid var(--ok); margin-bottom: 1.25rem; }
        #member .w-balance.is-negative { border-left-color: var(--bad); }
        #member .w-name { margin: 0 0 .25rem; font-size: .95rem; opacity: .8; }
        #member .w-amount { margin: 0; font-size: 3.25rem; line-height: 1; font-weight: 600; letter-spacing: -.02em; color: var(--ok); }
        #member .is-negative .w-amount { color: var(--bad); }
        #member .w-status { margin: .6rem 0 0; font-size: .9rem; color: var(--ok); }
        #member .is-negative .w-status { color: var(--bad); font-weight: 600; }

        #member .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; vertical-align: middle; }
        #member .w-tag.trusted { color: var(--ok); border-color: var(--ok); }

        /* Stats */
        #member .w-stats { display: flex; gap: 2rem; }
        #member .w-stat span { display: block; font-size: .8rem; opacity: .7; }
        #member .w-stat strong { font-size: 1.15rem; }
        #member .w-stat .in  { color: var(--ok); }
        #member .w-stat .out { color: var(--bad); }

        /* History */
        #member .w-history h3 { margin-bottom: .5rem; }
        #member .w-day { margin: 1.75rem 0 .4rem; font-size: .85rem; letter-spacing: 0; text-transform: none; opacity: .65; }
        #member .w-row { display: grid; grid-template-columns: 2.5rem 1fr auto; align-items: center; gap: 1rem;
            padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #member .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(42,201,134,.16); color: var(--ok); }
        #member .w-row.out .w-ico { background: rgba(255,95,109,.16); color: var(--bad); }
        #member .w-ico .icon:before { margin: 0; }
        #member .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #member .w-label { font-weight: 600; line-height: 1.3; }
        #member .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #member .w-val { font-weight: 600; font-size: 1.05rem; color: var(--ok); white-space: nowrap; }
        #member .w-row.out .w-val { color: var(--bad); }
        #member .w-empty { text-align: center; padding: 2.5rem 0; opacity: .7; }
        #member .w-net { display: flex; justify-content: space-between; padding: 1.1rem 0 0; font-weight: 600; }

        @media (max-width: 480px) {
            #member .w-amount { font-size: 2.6rem; }
            #member .w-stats { width: 100%; justify-content: space-between; }
        }
    </style>

    <!-- Balance -->
    <section class="w-balance <?= $state_class ?>">
        <div>
            <p class="w-name"><?= htmlspecialchars($customer['FIRST_NAME'] . ' ' . $customer['LAST_NAME']) ?>
                <?php if ($is_trusted): ?><span class="w-tag trusted" title="Can go below zero when buying" aria-label="Active member"><span class="icon solid fa-star" aria-hidden="true"></span></span><?php endif; ?></p>
            <h4 class="w-amount num"><?= eur($balance, true) ?></h4>
            <?php if ($is_negative): ?>
                <p class="w-status"><span class="icon solid fa-exclamation-triangle"></span> Negative balance</p>
            <?php else: ?>
                <p class="w-status">Available credit</p>
            <?php endif; ?>
        </div>
        <div class="w-stats">
            <div class="w-stat"><span>Topped up</span><strong class="in num"><?= eur($total_in, true) ?></strong></div>
            <div class="w-stat"><span>Spent</span><strong class="out num"><?= eur($total_out) ?></strong></div>
        </div>
    </section>

    <!-- History -->
    <section class="w-history">
        <h3>Transaction history</h3>

        <?php if (empty($activities)): ?>
            <p class="w-empty">No activity yet.</p>
        <?php else: ?>
            <?php $current_day = null; ?>
            <?php foreach ($activities as $a): ?>
                <?php
                    $is_in = $a['AMOUNT'] > 0;
                    $day   = date('Y-m-d', strtotime($a['CREATED_AT']));
                    $by    = trim(($a['BY_FIRST_NAME'] ?? '') . ' ' . ($a['BY_LAST_NAME'] ?? ''));
                ?>
                <?php if ($day !== $current_day): $current_day = $day; ?>
                    <h5 class="w-day"><?= htmlspecialchars(day_label($a['CREATED_AT'])) ?></h5>
                <?php endif; ?>

                <div class="w-row <?= $is_in ? 'in' : 'out' ?>">
                    <div class="w-ico" aria-hidden="true">
                        <?= activity_icon($a['TYPE'], $a['LABEL']) ?>
                    </div>
                    <div>
                        <div class="w-label"><?= htmlspecialchars($a['LABEL'] ?? ($is_in ? 'Top-up' : 'Purchase')) ?></div>
                        <div class="w-meta num">
                            <?= date('H:i', strtotime($a['CREATED_AT'])) ?>
                            <?php if ($by !== ''): ?>· by <?= htmlspecialchars($by) ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="w-val num"><?= eur($a['AMOUNT'], true) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="w-net num">
                <span>Net total</span>
                <span style="color: <?= $net >= 0 ? 'rgb(42, 201, 134)' : 'rgb(255, 95, 109)' ?>;"><?= eur($net, true) ?></span>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>
</article>