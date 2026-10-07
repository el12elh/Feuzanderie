<article id="forgot_password">
    <h2 class="major">Forgot Password</h2>

    <style>
        #forgot_password .auth-form { max-width: 26rem; margin: 0 auto; }
        #forgot_password .auth-intro { margin: 0 0 1.5rem; opacity: .8; text-align: center; }
        #forgot_password .auth-form label { text-transform: none; letter-spacing: 0; font-size: .85rem; font-weight: 600; margin: 0 0 .4rem; opacity: .85; }
        #forgot_password .auth-form .field .hint { margin: .4rem 0 0; font-size: .8rem; opacity: .6; }
        #forgot_password .auth-form button.fit { width: 100%; }
        #forgot_password .pw-wrap { position: relative; }
        #forgot_password .pw-wrap input { padding-right: 3rem; }
        #forgot_password .pw-toggle { position: absolute; top: 0; right: 0; width: 3rem; height: 100%; padding: 0; background: none; box-shadow: none;
            border: 0; cursor: pointer; color: inherit; opacity: .7; text-align: center; line-height: 1; font-size: 1.05rem; }
        #forgot_password .pw-toggle:hover, #forgot_password .pw-toggle:focus-visible { opacity: 1; background: none; }
        #forgot_password .pw-toggle:before { margin: 0; }
        #forgot_password .auth-links { margin-top: 1.75rem; text-align: center; font-size: .95rem; line-height: 2; }
        #forgot_password .auth-links a { text-decoration: none; border-bottom: 1px dotted currentColor; }
        #forgot_password .auth-note { margin: 0 0 1.25rem; text-align: center; opacity: .8; }
    </style>

    <form method="post" action="" class="auth-form">
        <p class="auth-intro">Enter your email and we will send you a link to choose a new password.</p>
        <div class="fields">
            <div class="field">
                <label for="forgot-email">Email</label>
                <input type="email" name="username" id="forgot-email" value="" autocomplete="email" placeholder="you@example.com" required />
            </div>
        </div>
        <ul class="actions stacked" style="margin-top: 1rem;">
            <li><button type="submit" name="request_reset" value="Send Reset Link" class="primary fit">Send reset link</button></li>
        </ul>
    </form>

    <p class="auth-links">
        <a href="#signin">Back to sign in</a>
    </p>
</article>