<?php
// 1. Customer linked to the logged-in user
$stmt_link = $pdo->prepare("SELECT ID_CUSTOMER FROM users_customers WHERE ID_USER = ?");
$stmt_link->execute([$_SESSION['user_id']]);
$link = $stmt_link->fetch();

$customer    = null;
$activities  = [];
$total_in    = 0;
$total_out   = 0;

if ($link) {
    $id_customer = $link['ID_CUSTOMER'];

    // 2. Current balance
    $stmt_bal = $pdo->prepare("SELECT FIRST_NAME, LAST_NAME, BALANCE, IS_TRUSTED FROM customers WHERE ID_CUSTOMER = ?");
    $stmt_bal->execute([$id_customer]);
    $customer = $stmt_bal->fetch();

    // 3. Top-ups + purchases
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

<article id="wallet">
    <h2 class="major">Wallet</h2>

    <?php if (!$link || !$customer): ?>
        <p>Your account is not yet linked to a customer profile. An administrator will link it shortly.</p>
    <?php else: ?>

    <style>
        #wallet { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #wallet .num { font-variant-numeric: tabular-nums; }

        /* Balance */
        #wallet .w-balance { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1.5rem;
            padding: 1.75rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            border-left: 6px solid var(--ok); margin-bottom: 1.25rem; }
        #wallet .w-balance.is-negative { border-left-color: var(--bad); }
        #wallet .w-name { margin: 0 0 .25rem; font-size: .95rem; opacity: .8; }
        #wallet .w-amount { margin: 0; font-size: 3.25rem; line-height: 1; font-weight: 600; letter-spacing: -.02em; color: var(--ok); }
        #wallet .is-negative .w-amount { color: var(--bad); }
        #wallet .w-status { margin: .6rem 0 0; font-size: .9rem; color: var(--ok); }
        #wallet .is-negative .w-status { color: var(--bad); font-weight: 600; }

        #wallet .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; vertical-align: middle; }
        #wallet .w-tag.trusted { color: var(--ok); border-color: var(--ok); }
        #wallet .w-tag.trusted .icon { font-size: .72rem; line-height: 1; vertical-align: middle; }

        /* Active member perks */
        #wallet .w-perks { display: flex; align-items: flex-start; gap: .85rem; margin: 0 0 1.25rem; padding: 1rem 1.25rem;
            border: 1px solid var(--ok); border-radius: .75rem; background: rgba(42,201,134,.08); }
        #wallet .w-perks .icon { color: var(--ok); margin-top: .15rem; }
        #wallet .w-perks p { margin: 0; font-size: .92rem; line-height: 1.45; }

        /* Stats */
        #wallet .w-stats { display: flex; gap: 2rem; }
        #wallet .w-stat span { display: block; font-size: .8rem; opacity: .7; }
        #wallet .w-stat strong { font-size: 1.15rem; }
        #wallet .w-stat .in  { color: var(--ok); }
        #wallet .w-stat .out { color: var(--bad); }

        /* Top-up */
        #wallet .w-topup { margin: 2.5rem 0; }
        #wallet .w-topup h3 { margin-bottom: 1rem; }
        #wallet .w-chips { display: grid; grid-template-columns: repeat(6, 1fr); gap: .5rem; margin-bottom: 1rem; }
        #wallet .w-chips input { position: absolute; opacity: 0; pointer-events: none; }
        #wallet .w-chips label { display: block; text-align: center; padding: .5rem 0; margin: 0; border: 1px solid var(--line);
            border-radius: .4rem; font-size: .85rem; line-height: 1.2; height: auto; cursor: pointer; font-weight: 600; transition: background .15s, border-color .15s, color .15s; }
        #wallet .w-chips label:hover { background: var(--soft); }
        /* hide the theme's circle check mark on radio labels */
        #wallet .w-chips label:before, #wallet .w-chips label:after { content: none; display: none; }
        #wallet .w-chips input + label { padding-left: 0; padding-right: 0; }
        #wallet .w-chips input:checked + label { background: var(--ok); border-color: var(--ok); color: #0b2a1d; }
        #wallet .w-chips input:focus-visible + label { outline: 2px solid #fff; outline-offset: 2px; }
        #wallet .w-topup button { width: 100%; }

        /* History */
        #wallet .w-history h3 { margin-bottom: .5rem; }
        #wallet .w-day { margin: 1.75rem 0 .4rem; font-size: .85rem; letter-spacing: 0; text-transform: none; opacity: .65; }
        #wallet .w-row { display: grid; grid-template-columns: 2.5rem 1fr auto; align-items: center; gap: 1rem;
            padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #wallet .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(42,201,134,.16); color: var(--ok); }
        #wallet .w-row.out .w-ico { background: rgba(255,95,109,.16); color: var(--bad); }
        #wallet .w-ico .icon:before { margin: 0; }
        #wallet .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #wallet .w-label { font-weight: 600; line-height: 1.3; }
        #wallet .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #wallet .w-val { font-weight: 600; font-size: 1.05rem; color: var(--ok); white-space: nowrap; }
        #wallet .w-row.out .w-val { color: var(--bad); }
        #wallet .w-empty { text-align: center; padding: 2.5rem 0; opacity: .7; }
        #wallet .w-net { display: flex; justify-content: space-between; padding: 1.1rem 0 0; font-weight: 600; }

        @media (max-width: 480px) {
            #wallet .w-amount { font-size: 2.6rem; }
            #wallet .w-chips { grid-template-columns: repeat(3, 1fr); }
            #wallet .w-stats { width: 100%; justify-content: space-between; }
        }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <!-- Balance -->
    <section class="w-balance <?= $state_class ?>">
        <div>
            <p class="w-name"><?= htmlspecialchars($customer['FIRST_NAME'] . ' ' . $customer['LAST_NAME']) ?>
                <?php if ($is_trusted): ?><span class="w-tag trusted" title="Can go below zero when buying" aria-label="Active member"><span class="icon solid fa-star" aria-hidden="true"></span></span><?php endif; ?></p>
            <h4 class="w-amount num"><?= eur($balance, true) ?></h4>
            <?php if ($is_negative): ?>
                <p class="w-status"><span class="icon solid fa-exclamation-triangle"></span> Please top up your account ASAP</p>
            <?php else: ?>
                <p class="w-status">Available credit</p>
            <?php endif; ?>
        </div>
        <div class="w-stats">
            <div class="w-stat"><span>Topped up</span><strong class="in num"><?= eur($total_in, true) ?></strong></div>
            <div class="w-stat"><span>Spent</span><strong class="out num"><?= eur($total_out) ?></strong></div>
        </div>
    </section>
    
    <?php if ($is_trusted): ?>
    <!-- Active member message -->
    <aside class="w-perks">
        <span class="icon solid fa-star" aria-hidden="true"></span>
        <p>As an active member, you benefit from a negative balance of up to €20, the right to join the end-of-season trip, and a range of exclusive benefits.</p>
    </aside>
    <?php endif; ?>

    <!-- Top-up -->
    <section id="stripe-topup" class="w-topup">
        <h3>Add credit</h3>
        <form method="post" action="create_stripe_topup">
            <div class="w-chips" role="radiogroup" aria-label="Top-up amount">
                <?php foreach ([25, 50, 75, 100, 150, 200] as $i): ?>
                    <div>
                        <input type="radio" name="amount" id="amt<?= $i ?>" value="<?= $i ?>" required>
                        <label for="amt<?= $i ?>">+<?= $i ?>€</label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="primary"><i class="fa fa-credit-card"></i> Pay with Stripe</button>
        </form>
    </section>

    <!-- History -->
    <section class="w-history">
        <h3>Transaction history</h3>

        <?php if (empty($activities)): ?>
            <p class="w-empty">No activity yet. Add credit to get started.</p>
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