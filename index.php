<?php
    include 'db.php';
    include 'functions.php';
    include 'queries.php';
?>

<!DOCTYPE HTML>
<html lang="en">
    <head>
        <title>Feuzanderie</title>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no"/>
        <link rel="stylesheet" href="assets/css/main.css"/>
        <noscript>
            <link rel="stylesheet" href="assets/css/noscript.css"/>
        </noscript>
        <link rel="shortcut icon" sizes="57x57" href="./images/logo.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
        <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    </head>
    <body class="is-preload">
        <!-- Wrapper -->
        <div id="wrapper">
            <!-- Christmas snow effect -->
            <?php
                $currentMonth = date('n');
                $currentDay = date('j');
                // Show snow effect from December 1st to December 25th
                if ($currentMonth == 12 && $currentDay <= 25): 
            ?>
            <link rel="stylesheet" href="assets/css/noel/neige.css"/>
            
            <div class="snow" style="position:fixed!important">
                <div class="snow__layer">
                    <div class="snow__fall"></div>
                </div>
                <div class="snow__layer">
                    <div class="snow__fall"></div>
                </div>
                <div class="snow__layer">
                    <div class="snow__fall"></div>
                    <div class="snow__fall"></div>
                    <div class="snow__fall"></div>
                </div>
                <div class="snow__layer">
                    <div class="snow__fall"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Header -->
            <header id="header">
                <div class="logo" onclick="location.reload();" style="cursor: pointer;">
                    <img src="images/logo.png" alt="Logo">
                </div>
                <div class="content">
                    <div class="inner">
                        <h1>Feuzanderie🦅</h1>
                        <p>USJ Amikale</p>
                    </div>
                </div>
                <nav>
                    <ul>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li><a href="#signin"><strong>Sign In</strong></a></li>
                        <?php else: ?>
                            <li><a href="#wallet"><strong>Wallet</strong></a></li>
                            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                                <li><a href="#amikale"><strong>Amikale</strong></a></li>
                                <li><a href="#products"><strong>Products</strong></a></li>
                                <li><a href="#members"><strong>Members</strong></a></li>
                                <li><a href="#admin"><strong>Admin</strong></a></li>
                                <li><a href="#cashflow"><strong>Cash Flow</strong></a></li>
                                <li><a href="#dashboard"><strong>Dashboard</strong></a></li>
                            <?php else: ?>
                                <li><a href="#dashboard"><strong>Dashboard</strong></a></li>
                                <li><a href="#contact"><strong>Contact</strong></a></li>
                            <?php endif; ?>
                            <li><a href="signout"><strong>Sign Out</strong></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </header>
            <div id="main">
            <?php
                // --- GUESTS ONLY ---
                if (!isset($_SESSION['user_id'])) :
                    include 'signup.php';
                    include 'signin.php';
                    include 'forgot_password.php';
                    include 'reset_password.php';
                // --- LOGGED IN USERS ---
                else :
                    include 'wallet.php';
                    include 'dashboard.php';
                    include 'contact.php';
                    // --- ADMINS ONLY ---
                    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) :        
                        include 'amikale.php';
                        include 'products.php';
                        include 'members.php';
                        include 'member.php';
                        include 'admin.php';
                        include 'cashflow.php';   
                    endif;
                endif;
                ?>
            </div>
            <div id="toast" class="toast">
                <span class="toast-message"></span>
                <div class="toast-progress"></div>
            </div>

            <?php include 'toast.php'; ?>
            <!-- Footer -->
            <footer id="footer">
                <p class="copyright">&copy; <?php echo date('Y'); ?> Feuzanderie<br />
                    &lt;/&gt; with ❤ by 
                <a href="https://www.linkedin.com/in/el-mehdi-el-haddad/" target="_blank"
                    rel="noreferrer">El Mehdi El Haddad</a></p>
            </footer>   

        </div>
        <!-- Global Toast -->
        <div id="toast" class="toast">
            <span class="toast-message"></span>
            <div class="toast-progress"></div>
        </div>

        <!-- Receipt Preview -->
        <div id="receipt-preview">
            <div class="close-btn" onclick="closeReceipt(event)"></div>
            <iframe id="receipt-frame" src=""></iframe>
        </div>

        <!-- BG -->
        <div id="bg"></div>

        <!-- Scripts -->
        <script src="assets/js/jquery.min.js"></script>
        <script src="assets/js/browser.min.js"></script>
        <script src="assets/js/breakpoints.min.js"></script>
        <script src="assets/js/util.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
        <script src="assets/js/amikale.js"></script>
    </body>
</html>