<article id="signin">
    <h2 class="major">Sign In</h2>

    <style>
        #signin .auth-form { max-width: 26rem; margin: 0 auto; }
        #signin .auth-intro { margin: 0 0 1.5rem; opacity: .8; text-align: center; }
        #signin .auth-form label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #signin .auth-form .field .hint { margin: .4rem 0 0; font-size: .8rem; opacity: .6; }
        #signin .auth-form button.fit { width: 100%; }
        #signin .pw-wrap { position: relative; }
        #signin .pw-wrap input { padding-right: 3rem; }
        #signin .pw-toggle { position: absolute; top: 0; right: 0; width: 3rem; height: 100%; padding: 0; background: none; box-shadow: none;
            border: 0; cursor: pointer; color: inherit; opacity: .7; text-align: center; line-height: 1; font-size: 1.05rem; }
        #signin .pw-toggle:hover, #signin .pw-toggle:focus-visible { opacity: 1; background: none; }
        #signin .pw-toggle:before { margin: 0; }
        #signin .auth-links { margin-top: 1.75rem; text-align: center; font-size: .95rem; line-height: 2; }
        #signin .auth-links a { text-decoration: none; border-bottom: 1px dotted currentColor; }
        #signin .auth-note { margin: 0 0 1.25rem; text-align: center; opacity: .8; }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <form method="post" action="" class="auth-form">
        <p class="auth-intro">Welcome back. Sign in to see your wallet.</p>
        <div class="fields">
            <div class="field">
                <label for="signin-email">Email</label>
                <input type="email" name="username" id="signin-email" value="" autocomplete="email" placeholder="you@example.com" required />
            </div>
                <div class="field">
                    <label for="signin-password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="signin-password" minlength="6" autocomplete="current-password" required />
                        <button type="button" class="pw-toggle icon solid fa-eye" aria-label="Show password" onclick="togglePassword(this)"></button>
                    </div>
                </div>
        </div>
        <ul class="actions stacked" style="margin-top: 1rem;">
            <li><button type="submit" name="signin" value="Sign in" class="primary fit">Sign in</button></li>
        </ul>
    </form>

    <p class="auth-links">
        <a href="#forgot_password">Forgot your password?</a><br />
        New here? <a href="#signup"><b>Create an account</b></a>
    </p>
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
</article>