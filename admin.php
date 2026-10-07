<?php
include 'security.php';

if (!function_exists('member_initials')) {
    function member_initials($first, $last) {
        $a = mb_substr(trim((string)$first), 0, 1);
        $b = mb_substr(trim((string)$last), 0, 1);
        return htmlspecialchars(mb_strtoupper($a . $b));
    }
}

// Split members: waiting for an account link / already linked
$to_link = [];
$linked  = [];
$n_admin = 0;
foreach ($all_customers as $ac) {
    if (empty($ac['LINKED_USER_ID'])) { $to_link[] = $ac; }
    else {
        $linked[] = $ac;
        if ((int)$ac['IS_ADMIN'] === 1) $n_admin++;
    }
}
$has_free_users = !empty($unlinked_users);
?>

<article id="admin">
    <h2 class="major">Admin</h2>

    <style>
        #admin { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #admin .num { font-variant-numeric: tabular-nums; }

        /* Summary */
        #admin .ad-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-bottom: 1.75rem; }
        #admin .ad-cell { padding: 1rem; border: 1px solid var(--line); border-radius: .75rem; background: var(--soft); text-align: center; }
        #admin .ad-cell span { display: block; font-size: .8rem; opacity: .7; margin-bottom: .2rem; }
        #admin .ad-cell strong { font-size: 1.4rem; }
        #admin .ad-search { margin-bottom: 1.75rem; }

        /* Sections */
        #admin .ad-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin: 0 0 .25rem; }
        #admin .ad-head h3 { margin: 0; }
        #admin .ad-sub { font-size: .85rem; opacity: .75; }
        #admin .table-divider { margin: 2rem 0; }

        /* Rows */
        #admin .w-row { display: grid; grid-template-columns: 2.5rem 1fr auto; align-items: center; gap: .5rem 1rem;
            padding: .85rem 0; border-bottom: 1px solid var(--line); margin: 0; }
        #admin .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(255,255,255,.08); }
        #admin .w-label { font-weight: 600; line-height: 1.3; }
        #admin .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; word-break: break-all; }
        #admin .w-empty { text-align: center; padding: 1.5rem 0; opacity: .7; }
        #admin .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; vertical-align: middle; }
        #admin .w-tag.admin { color: var(--ok); border-color: var(--ok); }
        #admin .w-row select { height: 2.5rem; font-size: .85rem; margin-top: .4rem; }
        #admin .ad-actions { display: flex; align-items: center; gap: .25rem; }
        #admin .ad-actions form { display: inline; margin: 0; }

        #admin .ic-btn { background: none; box-shadow: none; border: 0; cursor: pointer; padding: 0; width: 2.5rem; height: 2.5rem;
            line-height: 2.5rem; font-size: 1.1rem; text-align: center; opacity: .9; }
        #admin .ic-btn:hover { opacity: 1; background: none; }
        #admin .ic-btn:disabled { opacity: .3; cursor: not-allowed; }
        #admin .ic-btn:before { margin: 0; }
        #admin .ic-btn.ok  { color: var(--ok); }
        #admin .ic-btn.bad { color: var(--bad); }

        @media (max-width: 480px) {
            #admin .ad-cell { padding: .75rem .4rem; }
            #admin .ad-cell strong { font-size: 1.1rem; }
            #admin .w-row { gap: .4rem .6rem; }
            #admin .ic-btn { width: 2.1rem; }
        }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <!-- Summary -->
    <div class="ad-stats">
        <div class="ad-cell"><span>Linked</span><strong class="num"><?= count($linked) ?></strong></div>
        <div class="ad-cell"><span>To link</span><strong class="num" style="color: <?= count($to_link) ? 'rgb(255,95,109)' : 'inherit' ?>;"><?= count($to_link) ?></strong></div>
        <div class="ad-cell"><span>Admins</span><strong class="num" style="color: rgb(42,201,134);"><?= $n_admin ?></strong></div>
    </div>

    <!-- Search -->
    <div class="ad-search">
        <input type="text" id="adminSearch" placeholder="Search by name or email..." autocomplete="off" />
    </div>

    <!-- Members waiting for a user account -->
    <div class="ad-section">
        <div class="ad-head">
            <h3 style="color: rgb(255, 95, 109);">To link</h3>
            <span class="ad-sub num"><?= count($to_link) ?> member<?= count($to_link) === 1 ? '' : 's' ?> without an account</span>
        </div>

        <?php if (empty($to_link)): ?>
            <p class="w-empty">Every member is linked to an account.</p>
        <?php else: ?>
            <?php foreach ($to_link as $ac): $fullName = $ac['FIRST_NAME'] . ' ' . $ac['LAST_NAME']; ?>
                <form method="post" action="" class="w-row admin-row"
                      data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="w-ico" aria-hidden="true"><?= member_initials($ac['FIRST_NAME'], $ac['LAST_NAME']) ?></div>
                    <div>
                        <div class="w-label"><?= htmlspecialchars($fullName) ?></div>
                        <select name="id_user" required <?= $has_free_users ? '' : 'disabled' ?>>
                            <option value=""><?= $has_free_users ? '-- Select email --' : 'No unlinked account available' ?></option>
                            <?php foreach ($unlinked_users as $uu): ?>
                                <option value="<?= (int)$uu['ID_USER'] ?>"><?= htmlspecialchars($uu['EMAIL']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ad-actions">
                        <input type="hidden" name="id_customer" value="<?= (int)$ac['ID_CUSTOMER'] ?>">
                        <button type="submit" name="link_account" class="ic-btn ok icon solid fa-link"
                                title="Link account" <?= $has_free_users ? '' : 'disabled' ?>>
                            <span class="label">Link</span>
                        </button>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr class="table-divider">

    <!-- Linked accounts -->
    <div class="ad-section">
        <div class="ad-head">
            <h3 style="color: rgb(42, 201, 134);">Linked accounts</h3>
            <span class="ad-sub num"><?= count($linked) ?> account<?= count($linked) === 1 ? '' : 's' ?></span>
        </div>

        <?php if (empty($linked)): ?>
            <p class="w-empty">No linked accounts yet.</p>
        <?php else: ?>
            <?php foreach ($linked as $ac):
                $isAdmin  = (int)$ac['IS_ADMIN'] === 1;
                $fullName = $ac['FIRST_NAME'] . ' ' . $ac['LAST_NAME'];
            ?>
                <div class="w-row admin-row"
                     data-name="<?= htmlspecialchars($fullName . ' ' . $ac['EMAIL'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="w-ico" aria-hidden="true"><?= member_initials($ac['FIRST_NAME'], $ac['LAST_NAME']) ?></div>
                    <div>
                        <div class="w-label">
                            <?= htmlspecialchars($fullName) ?>
                            <?php if ($isAdmin): ?><span class="w-tag admin">Admin</span><?php endif; ?>
                        </div>
                        <div class="w-meta"><?= htmlspecialchars($ac['EMAIL']) ?></div>
                    </div>
                    <div class="ad-actions">
                        <form method="post" action="">
                            <input type="hidden" name="id_user" value="<?= (int)$ac['LINKED_USER_ID'] ?>">
                            <input type="hidden" name="set_admin" value="<?= $isAdmin ? 0 : 1 ?>">
                            <button type="submit" name="toggle_admin"
                                    class="ic-btn icon solid <?= $isAdmin ? 'bad fa-user-minus' : 'ok fa-user-plus' ?>"
                                    title="<?= $isAdmin ? 'Remove admin' : 'Make admin' ?>"
                                    onclick="return confirm('<?= $isAdmin ? 'Remove admin rights from' : 'Make' ?> <?= htmlspecialchars(addslashes($fullName), ENT_QUOTES) ?><?= $isAdmin ? '' : ' an admin' ?>?');">
                                <span class="label"><?= $isAdmin ? 'Remove admin' : 'Make admin' ?></span>
                            </button>
                        </form>
                        <form method="post" action="">
                            <input type="hidden" name="id_customer" value="<?= (int)$ac['ID_CUSTOMER'] ?>">
                            <input type="hidden" name="id_user" value="<?= (int)$ac['LINKED_USER_ID'] ?>">
                            <button type="submit" name="unlink_account" class="ic-btn bad icon solid fa-unlink"
                                    title="Unlink account"
                                    onclick="return confirm('Unlink <?= htmlspecialchars(addslashes($fullName), ENT_QUOTES) ?> from this account?');">
                                <span class="label">Unlink</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var box = document.getElementById('adminSearch');
        if (!box) return;
        function norm(v) { return v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
        function filter() {
            var q = norm(box.value), shown = 0;
            document.querySelectorAll('#admin .ad-section').forEach(function (sec) {
                var rows = sec.querySelectorAll('.admin-row'), any = false;
                rows.forEach(function (r) {
                    var ok = norm(r.getAttribute('data-name')).indexOf(q) !== -1;
                    r.style.display = ok ? '' : 'none';
                    if (ok) any = true;
                });
                var show = any || q === '';
                sec.style.display = show ? 'block' : 'none';
                if (show) shown++;
            });
            var d = document.querySelector('#admin .table-divider');
            if (d) d.style.display = shown > 1 ? 'block' : 'none';
        }
        box.addEventListener('input', filter);
    })();
    </script>
</article>