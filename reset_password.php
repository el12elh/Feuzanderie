<?php
$selector = $_GET['selector'] ?? '';
$token = $_GET['token'] ?? '';
?>

<article id="reset_password">
    <h2 class="major">Reset Password</h2>

    <style>
        #reset_password .auth-form { max-width: 26rem; margin: 0 auto; }
        #reset_password .auth-intro { margin: 0 0 1.5rem; opacity: .8; text-align: center; }
        #reset_password .auth-form label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #reset_password .auth-form .field .hint { margin: .4rem 0 0; font-size: .8rem; opacity: .6; }
        #reset_password .auth-form button.fit { width: 100%; }
        #reset_password .pw-wrap { position: relative; }
        #reset_password .pw-wrap input { padding-right: 3rem; }
        #reset_password .pw-toggle { position: absolute; top: 0; right: 0; width: 3rem; height: 100%; padding: 0; background: none; box-shadow: none;
            border: 0; cursor: pointer; color: inherit; opacity: .7; text-align: center; line-height: 1; font-size: 1.05rem; }
        #reset_password .pw-toggle:hover, #reset_password .pw-toggle:focus-visible { opacity: 1; background: none; }
        #reset_password .pw-toggle:before { margin: 0; }
        #reset_password .auth-links { margin-top: 1.75rem; text-align: center; font-size: .95rem; line-height: 2; }
        #reset_password .auth-links a { text-decoration: none; border-bottom: 1px dotted currentColor; }
        #reset_password .auth-note { margin: 0 0 1.25rem; text-align: center; opacity: .8; }
    </style>

    <?php if ($selector && $token): ?>
        <form method="post" action="" class="auth-form">
            <p class="auth-intro">Choose a new password for your account.</p>
            <input type="hidden" name="selector" value="<?php echo htmlspecialchars($selector, ENT_QUOTES); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
            <div class="fields">
                <div class="field">
                        <label for="reset-password">New password</label>
                        <div class="pw-wrap">
                            <input type="password" name="password" id="reset-password" minlength="6" autocomplete="new-password" required />
                            <button type="button" class="pw-toggle icon solid fa-eye" aria-label="Show password" onclick="togglePassword(this)"></button>
                        </div>
                        <p class="hint">At least 6 characters.</p>
                    </div>
            </div>
            <ul class="actions stacked" style="margin-top: 1rem;">
                <li><button type="submit" name="reset_submit" value="Update Password" class="primary fit">Update password</button></li>
            </ul>
        </form>
    <script>
        if (!window.togglePassword) {
            window.togglePassword = function (btn) {
                var input = btn.parentNode.querySelector('input');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('fa-eye', !show);
                btn.classList.toggle('fa-eye-slash', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            };
        }
    </script>
    <?php else: ?>
        <div class="auth-form">
            <p class="auth-note">This reset link is missing or has expired.</p>
            <ul class="actions stacked">
                <li><a href="#forgot_password" class="button primary fit">Request a new link</a></li>
            </ul>
        </div>
    <?php endif; ?>
</article>