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

// Split customers into two arrays
$positives = array_filter($customers, fn($c) => $c['BALANCE'] >= 0);
$negatives = array_filter($customers, fn($c) => $c['BALANCE'] < 0);

// Subtotals
$posTotal = array_sum(array_column($positives, 'BALANCE'));
$negTotal = array_sum(array_column($negatives, 'BALANCE'));

if (!function_exists('member_initials')) {
    function member_initials($first, $last) {
        $a = mb_substr(trim((string)$first), 0, 1);
        $b = mb_substr(trim((string)$last), 0, 1);
        return htmlspecialchars(mb_strtoupper($a . $b));
    }
}

// One row per member (shared by both lists)
if (!function_exists('renderRows')) {
    function renderRows($data) {
        foreach ($data as $c):
            $balance   = (float) $c['BALANCE'];
            $color     = $balance >= 0 ? 'rgb(42, 201, 134)' : 'rgb(255, 95, 109)';
            $active    = $c['IS_ACTIVE'] == 1;
            $trusted   = $c['IS_TRUSTED'] == 1;
            $memberUrl = "./?id=" . (int)$c['ID_CUSTOMER'] . "#member";
            $fullName  = $c['FIRST_NAME'] . ' ' . $c['LAST_NAME'];
            ?>
            <div class="w-row member-row <?= $active ? '' : 'inactive-row is-off' ?>"
                 data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="w-ico" aria-hidden="true"><?= member_initials($c['FIRST_NAME'], $c['LAST_NAME']) ?></div>
                <div class="w-label">
                    <a href="<?= $memberUrl ?>" style="text-decoration:none; color:inherit;"><?= htmlspecialchars($fullName) ?></a>
                    <?php if ($trusted): ?><span class="w-tag trusted">Trusted</span><?php endif; ?>
                    <?php if (!$active): ?><span class="w-tag">Hidden</span><?php endif; ?>
                </div>
                <div class="w-val num" style="color: <?= $color ?>;"><?= eur($balance, true) ?></div>
                <form method="post" action="" class="inline">
                    <input type="hidden" name="id_customer" value="<?= (int)$c['ID_CUSTOMER'] ?>">
                    <input type="hidden" name="set_active" value="<?= $active ? 0 : 1 ?>">
                    <button type="submit" name="toggle_cust"
                            class="ic-btn icon solid <?= $active ? 'fa-eye' : 'fa-eye-slash' ?>"
                            title="<?= $active ? 'Hide' : 'Show' ?>"></button>
                </form>
                <form method="post" action="" class="inline">
                    <input type="hidden" name="id_customer" value="<?= (int)$c['ID_CUSTOMER'] ?>">
                    <input type="hidden" name="set_trusted" value="<?= $trusted ? 0 : 1 ?>">
                    <button type="submit" name="toggle_trust"
                            class="ic-btn icon solid <?= $trusted ? 'fa-user-shield' : 'fa-user-alt-slash' ?>"
                            title="<?= $trusted ? 'Do not trust' : 'Trust' ?>"
                            style="color: <?= $trusted ? 'rgb(42, 201, 134)' : 'rgb(255, 95, 109)' ?>; opacity: 1;"></button>
                </form>
            </div>
        <?php endforeach;
    }
}
?>

<article id="members">
    <h2 class="major">Members</h2>

    <style>
        #members { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #members .num { font-variant-numeric: tabular-nums; }

        /* Cards (forms) */
        #members .am-card { border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            padding: 1.5rem 1.5rem 1.25rem; margin-bottom: 2rem; }
        #members .am-card h3 { display: flex; align-items: center; gap: .6rem; margin: 0 0 .25rem; font-size: 1.15rem; }
        #members .am-card h3 .icon:before { margin: 0; }
        #members .am-hint { margin: 0 0 1.25rem; font-size: .85rem; opacity: .65; }
        #members .am-card label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #members .am-card button.fit { width: 100%; }

        /* Lists */
        #members .w-row { display: grid; align-items: center; gap: .5rem 1rem; padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #members .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(255,255,255,.08); }
        #members .w-ico .icon:before { margin: 0; }
        #members .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #members .w-label { font-weight: 600; line-height: 1.3; }
        #members .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #members .w-val { font-weight: 600; font-size: 1.05rem; white-space: nowrap; }
        #members .w-empty { text-align: center; padding: 1.5rem 0; opacity: .7; }
        #members .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; opacity: .8; vertical-align: middle; }
        #members .w-tag.trusted { color: var(--ok); border-color: var(--ok); opacity: 1; }
        #members .is-off { opacity: .5; }
        #members .ic-btn { background: none; box-shadow: none; border: 0; cursor: pointer; padding: 0; width: 2.5rem; height: 2.5rem;
            line-height: 2.5rem; font-size: 1.15rem; color: inherit; opacity: .85; text-align: center; }
        #members .ic-btn:hover { opacity: 1; background: none; }
        #members .ic-btn:before { margin: 0; }
        #members form.inline { display: inline; margin: 0; }

        #members .w-row { grid-template-columns: 2.5rem 1fr auto 2.5rem 2.5rem; gap: .5rem .6rem; }
        #members .ms-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-bottom: 1.75rem; }
        #members .ms-cell { padding: 1rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--soft); text-align: center; }
        #members .ms-cell span { display: block; font-size: .8rem; opacity: .7; margin-bottom: .2rem; }
        #members .ms-cell strong { font-size: 1.4rem; }
        #members .ms-search { margin-bottom: 1.75rem; }
        #members .ms-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin: 0 0 .25rem; }
        #members .ms-head h3 { margin: 0; }
        #members .ms-sub { font-size: .85rem; opacity: .75; }
        #members .table-divider { margin: 2rem 0; }
        @media (max-width: 480px) {
            #members .ms-cell { padding: .75rem .4rem; }
            #members .ms-cell strong { font-size: 1.1rem; }
            #members .w-row { gap: .4rem .35rem; }
            #members .ic-btn { width: 2rem; }
            #members .w-row { grid-template-columns: 2.5rem 1fr auto 2rem 2rem; }
        }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <!-- New member -->
    <section class="am-card">
        <h3><span class="icon solid fa-plus-circle"></span> Add a member</h3>
        <p class="am-hint">Trusted members can go below zero when they buy something.</p>
        <form method="post" action="">
            <div class="fields">
                <div class="field half">
                    <label for="first_name">First name</label>
                    <input type="text" name="first_name" id="first_name" required />
                </div>
                <div class="field half">
                    <label for="last_name">Last name</label>
                    <input type="text" name="last_name" id="last_name" required />
                </div>
                <div class="field">
                    <input type="checkbox" name="is_trusted" id="is_trusted" value="1">
                    <label for="is_trusted" style="cursor: pointer;"><i class="fas fa-user-shield"></i> Trusted</label>
                </div>
                <div class="field">
                    <button type="submit" name="add_customer" class="primary fit">
                        <i class="fa fa-plus-circle"></i> Add member
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- Totals -->
    <div class="ms-stats">
        <div class="ms-cell"><span>Wallet credit</span><strong class="num" style="color: rgb(42,201,134);"><?= eur($posTotal, true) ?></strong></div>
        <div class="ms-cell"><span>Wallet debt</span><strong class="num" style="color: rgb(255,95,109);"><?= eur($negTotal) ?></strong></div>
        <div class="ms-cell"><span>Members</span><strong class="num"><?= count($customers) ?></strong></div>
    </div>

    <!-- Search -->
    <div class="ms-search">
        <input type="text" id="memberSearch" placeholder="Search members by name..." autocomplete="off" />
    </div>

    <div class="table-section">
        <div class="ms-head">
            <h3 style="color: rgb(255, 95, 109);">Negative balances</h3>
            <span class="ms-sub num"><?= count($negatives) ?> · <?= eur($negTotal) ?></span>
        </div>
        <div id="negTable">
            <?php if (empty($negatives)): ?>
                <p class="w-empty">Nobody owes anything.</p>
            <?php else: renderRows($negatives); endif; ?>
        </div>
    </div>

    <hr class="table-divider">

    <div class="table-section">
        <div class="ms-head">
            <h3 style="color: rgb(42, 201, 134);">Positive balances</h3>
            <span class="ms-sub num"><?= count($positives) ?> · <?= eur($posTotal, true) ?></span>
        </div>
        <div id="posTable">
            <?php if (empty($positives)): ?>
                <p class="w-empty">No members yet.</p>
            <?php else: renderRows($positives); endif; ?>
        </div>
    </div>
</article>

<script>
function normalizeMemberSearch(value) {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function filterMembers() {
    const filter = normalizeMemberSearch(document.getElementById('memberSearch').value);
    const sections = document.querySelectorAll('#members .table-section');
    const divider = document.querySelector('#members .table-divider');
    let visibleSections = 0;

    sections.forEach(section => {
        const rows = section.querySelectorAll('.member-row');
        let hasMatch = false;

        rows.forEach(row => {
            const name = normalizeMemberSearch(row.getAttribute('data-name'));
            const match = name.includes(filter);
            row.style.display = match ? "" : "none";
            if (match) hasMatch = true;
        });

        // Hide a whole section when nothing matches (but keep it when the search is empty)
        const show = hasMatch || filter === '';
        section.style.display = show ? "block" : "none";
        if (show) visibleSections++;
    });

    if (divider) divider.style.display = (visibleSections > 1) ? "block" : "none";
}

document.getElementById('memberSearch').addEventListener('input', filterMembers);
document.getElementById('memberSearch').addEventListener('keyup', filterMembers);
</script>