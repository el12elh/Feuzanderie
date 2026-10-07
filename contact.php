<?php
// Pre-fill the form from the logged-in account:
//  - email from the users table
//  - name from the linked customer (if any)
$contact_name  = '';
$contact_email = '';
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, c.FIRST_NAME AS C_FIRST_NAME, c.LAST_NAME AS C_LAST_NAME
            FROM users u
            LEFT JOIN users_customers uc ON uc.ID_USER = u.ID_USER
            LEFT JOIN customers c ON c.ID_CUSTOMER = uc.ID_CUSTOMER
            WHERE u.ID_USER = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            foreach (['EMAIL', 'USERNAME', 'MAIL'] as $k) {   // whichever column holds the email
                if (!empty($row[$k])) { $contact_email = $row[$k]; break; }
            }
            $contact_name = trim(($row['C_FIRST_NAME'] ?? '') . ' ' . ($row['C_LAST_NAME'] ?? ''));
        }
    } catch (Throwable $e) {
        // fall back to empty, editable fields
    }
}
?>
<article id="contact">
    <h2 class="major">Contact</h2>

    <style>
        #contact { --ok: rgb(42, 201, 134); --bad: rgb(255, 95, 109); --line: rgba(255,255,255,.14); --soft: rgba(255,255,255,.06); }
        #contact .num { font-variant-numeric: tabular-nums; }

        /* Cards (forms) */
        #contact .am-card { border: 1px solid var(--line); border-radius: .75rem; background: var(--soft);
            padding: 1.5rem 1.5rem 1.25rem; margin-bottom: 2rem; }
        #contact .am-card h3 { display: flex; align-items: center; gap: .6rem; margin: 0 0 .25rem; font-size: 1.15rem; }
        #contact .am-card h3 .icon:before { margin: 0; }
        #contact .am-hint { margin: 0 0 1.25rem; font-size: .85rem; opacity: .65; }
        #contact .am-card label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #contact .am-card button.fit { width: 100%; }

        /* Lists */
        #contact .w-row { display: grid; align-items: center; gap: .5rem 1rem; padding: .85rem 0; border-bottom: 1px solid var(--line); }
        #contact .w-ico { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: .95rem;
            background: rgba(255,255,255,.08); }
        #contact .w-ico .icon:before { margin: 0; }
        #contact .w-ico svg { width: 1.25rem; height: 1.25rem; display: block; }
        #contact .w-label { font-weight: 600; line-height: 1.3; }
        #contact .w-meta { font-size: .82rem; opacity: .65; margin-top: .15rem; }
        #contact .w-val { font-weight: 600; font-size: 1.05rem; white-space: nowrap; }
        #contact .w-empty { text-align: center; padding: 1.5rem 0; opacity: .7; }
        #contact .w-tag { display: inline-block; margin-left: .5rem; padding: .05rem .5rem; border: 1px solid var(--line); border-radius: 1rem;
            font-size: .72rem; font-weight: 400; opacity: .8; vertical-align: middle; }
        #contact .is-off { opacity: .5; }
        #contact .ic-btn { background: none; box-shadow: none; border: 0; cursor: pointer; padding: 0; width: 2.5rem; height: 2.5rem;
            line-height: 2.5rem; font-size: 1.15rem; color: inherit; opacity: .85; text-align: center; }
        #contact .ic-btn:hover { opacity: 1; background: none; }
        #contact .ic-btn:before { margin: 0; }
        #contact form.inline { display: inline; margin: 0; }

        #contact .w-row { grid-template-columns: 2.5rem 1fr; text-decoration: none; color: inherit; border-bottom: 1px solid var(--line); }
        #contact a.w-row:hover { background: var(--soft); }
        #contact a.w-row:last-child { border-bottom: 0; }
        #contact .w-ico .icon:before { font-size: 1rem; }
        #contact input[readonly] { opacity: .7; cursor: default; }
        #contact .ct-links { margin-top: 2rem; }
        #contact .ct-links h3 { margin-bottom: .25rem; }
    </style>

    <!-- Message -->
    <section class="am-card">
        <h3><span class="icon solid fa-paper-plane"></span> Send us a message</h3>
        <p class="am-hint">Questions, ideas or feedback: write to us here.<?php if ($contact_email !== ''): ?> Your name and email come from your account.<?php endif; ?></p>
        <form method="post" action="#">
            <div class="fields">
                <div class="field half">
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" autocomplete="name"
                           value="<?= htmlspecialchars($contact_name, ENT_QUOTES) ?>"
                           <?= $contact_name !== '' ? 'readonly' : '' ?> required />
                </div>
                <div class="field half">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" autocomplete="email"
                           value="<?= htmlspecialchars($contact_email, ENT_QUOTES) ?>"
                           <?= $contact_email !== '' ? 'readonly' : '' ?> required />
                </div>
                <div class="field">
                    <label for="message">Message</label>
                    <textarea name="message" id="message" rows="5" required></textarea>
                </div>
            </div>
            <ul class="actions">
                <li><input type="submit" name="contact_submit" value="Send message" class="primary" /></li>
                <li><input type="reset" value="Reset" /></li>
            </ul>
        </form>
    </section>
    <ul class="icons">
        <li>
            <a href="mailto:contact@feuzanderie.fr" 
               class="icon solid fa-envelope">
                <span class="label">Email</span>
            </a>
        </li>
        <li>
            <a href="https://www.instagram.com/feuzfeuzfeuz/"
                target="_blank"
                rel="noreferrer"
                class="icon brands fa-instagram">
                <span class="label">Instagram</span>
            </a>
        </li>
        <li>
            <a href="https://github.com/el12elh/Feuzanderie"
                target="_blank"
                rel="noreferrer"
                class="icon brands fa-github">
                <span class="label">GitHub</span>
            </a>
        </li>
    </ul>
</article>