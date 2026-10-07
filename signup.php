<article id="signup">
    <h2 class="major">Sign Up</h2>

    <style>
        #signup .auth-form { max-width: 26rem; margin: 0 auto; }
        #signup .auth-intro { margin: 0 0 1.5rem; opacity: .8; text-align: center; }
        #signup .auth-form label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #signup .auth-form .field .hint { margin: .4rem 0 0; font-size: .8rem; opacity: .6; }
        #signup .auth-form button.fit { width: 100%; }
        #signup .pw-wrap { position: relative; }
        #signup .pw-wrap input { padding-right: 3rem; }
        #signup .pw-toggle { position: absolute; top: 0; right: 0; width: 3rem; height: 100%; padding: 0; background: none; box-shadow: none;
            border: 0; cursor: pointer; color: inherit; opacity: .7; text-align: center; line-height: 1; font-size: 1.05rem; }
        #signup .pw-toggle:hover, #signup .pw-toggle:focus-visible { opacity: 1; background: none; }
        #signup .pw-toggle:before { margin: 0; }
        #signup .auth-links { margin-top: 1.75rem; text-align: center; font-size: .95rem; line-height: 2; }
        #signup .auth-links a { text-decoration: none; border-bottom: 1px dotted currentColor; }
        #signup .auth-note { margin: 0 0 1.25rem; text-align: center; opacity: .8; }
    </style>

    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>

    <form method="post" action="" class="auth-form">
        <p class="auth-intro">Create an account to follow your balance. An administrator will link it to your member profile.</p>
        <div class="fields">
            <div class="field">
                <label for="signup-email">Email</label>
                <input type="email" name="username" id="signup-email" maxlength="50" autocomplete="email" placeholder="you@example.com" required />
            </div>
                <div class="field">
                    <label for="signup-password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="signup-password" minlength="6" autocomplete="new-password" required />
                        <button type="button" class="pw-toggle icon solid fa-eye" aria-label="Show password" onclick="togglePassword(this)"></button>
                    </div>
                    <p class="hint">At least 6 characters.</p>
                </div>
        </div>
        <ul class="actions stacked" style="margin-top: 1rem;">
            <li><button type="submit" name="signup" value="Sign up" class="primary fit">Create account</button></li>
        </ul>
    </form>

    <p class="auth-links">
        Already have an account? <a href="#signin"><b>Sign in</b></a>
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