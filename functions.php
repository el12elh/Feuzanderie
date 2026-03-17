<?php

/* ==========================
   HANDLE POST ACTIONS
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sign In
    if (isset($_POST['signin'])) {
        $email = filter_var($_POST['username'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        // Prepare statement to prevent SQL Injection
        $stmt = $pdo->prepare("SELECT ID_USER, EMAIL, PASSWORD, IS_ADMIN FROM users WHERE EMAIL = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PASSWORD'])) {
            // Password is correct, create session variables
            $_SESSION['user_id'] = $user['ID_USER'];
            $_SESSION['email'] = $user['EMAIL'];
            $_SESSION['is_admin'] = $user['IS_ADMIN'];
            // Redirect to the main transaction page or dashboard
            header("Location: ./");
            exit;
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Invalid email or password'
            ];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;    
        }
    }

    // Sign Up
    if (isset($_POST['signup'])) {
        $email = filter_var($_POST['username'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)) {
            // Check if any users exist to assign Admin status
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();
            $isAdmin = ($userCount == 0) ? 1 : 0;

            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (EMAIL, PASSWORD, IS_ADMIN) 
                                       VALUES (?, ?, ?)");
                $stmt->execute([$email, $hashed_pass, $isAdmin]);
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Account created successfully'
                ];
                header("Location: ./#signin"); 
                exit;
            } catch (PDOException $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => 'Email already registered'
                ];
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;    
            }
        }
    }

    // Request to reset password
    if (isset($_POST['request_reset'])) {
    $email = filter_var($_POST['username'], FILTER_SANITIZE_EMAIL);
    
    // Check if user exists
        $stmt = $pdo->prepare("SELECT ID_USER FROM users WHERE EMAIL = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $selector = bin2hex(random_bytes(8));
            $token = random_bytes(32);
            $token_hash = password_hash($token, PASSWORD_DEFAULT);
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Delete any existing resets for this user
            $pdo->prepare("DELETE FROM users_pwd_reset WHERE ID_USER = ?")->execute([$user['ID_USER']]);

            // Insert new reset request
            $stmt = $pdo->prepare("INSERT INTO users_pwd_reset (ID_USER, SELECTOR, TOKEN_HASH, EXPIRES_AT) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['ID_USER'], $selector, $token_hash, $expires]);

            // Use a full URL (absolute path) because relative links (./) don't work in emails
            $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $resetLink = $baseUrl . "?selector=$selector&token=" . bin2hex($token) . "#reset_password";

            // Email details in English
            $subject = "Password Reset Request";
            $message = "Hello,\r\n\r\n";
            $message .= "We received a request to reset your password for your account.\r\n";
            $message .= "Click the link below to set a new password:\r\n\r\n";
            $message .= $resetLink . "\r\n\r\n";
            $message .= "If you did not make this request, please ignore this email.\r\n\r\n";
            $message .= "Cheers,\r\n";
            $message .= "l'Amikale";

            $headers = "From: FEUZANDERIE <contact@feuzanderie.fr>\r\n";
            $headers .= "Reply-To: contact@feuzanderie.fr\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            // Send the mail
            mail($email, $subject, $message, $headers);

            $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Password reset email sent'
                ];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;  
        }
        else {
            $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => 'No account matches this email'
                ];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;  
        }
    }

    // Reset Password
    if (isset($_POST['reset_submit'])) {
        $new_pwd = $_POST['password'];
        $selector = $_POST['selector'];
        $token = bin2hex($_POST['token']); // Convert back if needed

        // 1. Find the valid reset request
        $stmt = $pdo->prepare("SELECT * FROM users_pwd_reset WHERE SELECTOR = ? AND EXPIRES_AT > NOW()");
        $stmt->execute([$selector]);
        $resetRequest = $stmt->fetch();

        if ($resetRequest && password_verify(hex2bin($_GET['token']), $resetRequest['TOKEN_HASH'])) {
            // 2. Update User Password
            $hashedPassword = password_hash($new_pwd, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET PASSWORD = ? WHERE ID_USER = ?");
            $update->execute([$hashedPassword, $resetRequest['ID_USER']]);

            // 3. Clean up: Delete the reset token
            $pdo->prepare("DELETE FROM users_pwd_reset WHERE ID_USER = ?")->execute([$resetRequest['ID_USER']]);
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Password reseted successfully'
            ];
            header("Location: ./#signin");
            exit;
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Invalid or expired link'
            ];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // Add customer
    if (isset($_POST['add_customer'])) {
        $fname   = htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8');
        $lname   = htmlspecialchars($_POST['last_name'], ENT_QUOTES, 'UTF-8');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (FIRST_NAME, LAST_NAME) VALUES (?, ?)");
            $stmt->execute([$fname, $lname]);
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Member added successfully'
            ];
        } catch (PDOException $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Member with this name already exists'
            ];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Toggle customer visibility
    if (isset($_POST['toggle_cust'])) {
        $new_status  = (int)$_POST['set_active'];
        $id_customer = (int)$_POST['id_customer'];
        $stmt = $pdo->prepare("UPDATE customers SET IS_ACTIVE = ? WHERE ID_CUSTOMER = ?");
        $stmt->execute([$new_status, $id_customer]);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Member status updated'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    // Add product
    if (isset($_POST['add_product'])) {
        $name  = htmlspecialchars($_POST['prod_name'], ENT_QUOTES, 'UTF-8');
        $price = (float)$_POST['prod_price'];
        $stmt = $pdo->prepare("INSERT INTO ref_product (NAME, PRICE) VALUES (?, ?)");
        $stmt->execute([$name, $price]);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Product added successfully'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    // Toggle product visibility
    if (isset($_POST['toggle_prod'])) {
        $id_product = $_POST['id_product'];
        $new_status = $_POST['set_active'];
        $stmt = $pdo->prepare("UPDATE ref_product SET IS_ACTIVE = ? WHERE ID_PRODUCT = ?");
        $stmt->execute([$new_status, $id_product]);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Product status updated'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Admin Toggle
    if (isset($_POST['toggle_admin'])) {
        $target_user_id = (int)$_POST['id_user'];
        $new_status = (int)$_POST['set_admin'];
        $stmt = $pdo->prepare("UPDATE users SET IS_ADMIN = ? WHERE ID_USER = ?");
        $stmt->execute([$new_status, $target_user_id]);
        $_SESSION['toast'] = [
            'type' => $new_status ? 'success' : 'success',
            'message' => $new_status
                ? 'Administrator rights granted'
                : 'Administrator rights revoked'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Linking
    if (isset($_POST['link_account'])) {
        $id_user = (int)$_POST['id_user'];
        $id_customer = (int)$_POST['id_customer'];
        $stmt = $pdo->prepare("INSERT INTO users_customers VALUES (?, ?)");
        $stmt->execute([$id_user, $id_customer]);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Account linked successfully'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Removing the Link
    if (isset($_POST['unlink_account'])) {
        $id_user = (int)$_POST['id_user'];
        $id_customer = (int)$_POST['id_customer'];
        $stmt = $pdo->prepare("DELETE FROM users_customers WHERE ID_USER = ? AND ID_CUSTOMER = ?");
        $stmt->execute([$id_user, $id_customer]);
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Account unlinked successfully'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Sell Action
    if (isset($_POST['sell'])) {
        // 1. Check if the token exists and matches
        if (isset($_POST['token']) && $_POST['token'] === $_SESSION['submit_token']) {
            
            $current_admin_id = $_SESSION['user_id'];
            $id_customers = $_POST['id_customer']; // now an array
            $id_product = (int)$_POST['id_product'];
            $qty = (int)$_POST['qty'];
            // Fetch product
            $prod_stmt = $pdo->prepare("SELECT NAME, PRICE FROM ref_product WHERE ID_PRODUCT = ?");
            $prod_stmt->execute([$id_product]);
            $product = $prod_stmt->fetch();
            $total_price = $product['PRICE'] * $qty;
            $pdo->beginTransaction();
            foreach ($id_customers as $id_customer) {
            $id_customer = (int) $id_customer; // sanitize each

            // Insert transaction
            $stmt1 = $pdo->prepare("
                INSERT INTO transactions 
                (ID_USER, ID_CUSTOMER, ID_PRODUCT, QUANTITY, TOTAL) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt1->execute([
                $current_admin_id,
                $id_customer,
                $id_product,
                $qty,
                $total_price
            ]);
            // Special customers "Externe"
            if (in_array($id_customer, [2, 3])) {
                // Choose topup type
                $id_type = ($id_customer == 2) ? 3 : 2;
                $stmt2 = $pdo->prepare("
                    INSERT INTO wallet_topup 
                    (ID_USER, ID_CUSTOMER, ID_TOPUP_TYPE, AMOUNT)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt2->execute([
                    $current_admin_id,
                    $id_customer,
                    $id_type,
                    $total_price
                ]);
            } else {
                // Deduct from customer balance
                $stmt2 = $pdo->prepare("
                    UPDATE customers 
                    SET BALANCE = BALANCE - ? 
                    WHERE ID_CUSTOMER = ?
                ");
                $stmt2->execute([
                    $total_price,
                    $id_customer
                ]);
            }
        }
            $pdo->commit();
            
            // 2. CRITICAL: Destroy the token so it can't be used again
            unset($_SESSION['submit_token']);
            
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Transaction completed successfully'
            ];
        } else {
            // This triggers if the button is clicked twice or the page is refreshed
            $_SESSION['toast'] = [
                'type' => 'info',
                'message' => 'Transaction has already been completed'
            ];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle Top-Up Action
    if (isset($_POST['do_topup'])) {
        // 1. Check if the token exists and matches
        if (isset($_POST['token']) && $_POST['token'] === $_SESSION['submit_token']) {
            
            $current_admin_id = $_SESSION['user_id'];
            $id_customer = (int)$_POST['id_customer'];
            $id_type = (int)$_POST['id_type'];
            $amount = (float)$_POST['amount'];
            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("INSERT INTO wallet_topup (ID_USER, ID_CUSTOMER, ID_TOPUP_TYPE, AMOUNT)
                                    VALUES (?, ?, ?, ?)");
            $stmt1->execute([$current_admin_id, $id_customer, $id_type, $amount]);
            $stmt2 = $pdo->prepare("UPDATE customers SET BALANCE = BALANCE + ?, IS_ACTIVE = 1 WHERE ID_CUSTOMER = ?");
            $stmt2->execute([$amount, $id_customer]);
            $pdo->commit();

            // 2. CRITICAL: Destroy the token so it can't be used again
            unset($_SESSION['submit_token']);

            // 3. Redirect to prevent "Refresh" resubmission
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Account topped up successfully'
            ];
        } else {
            // This triggers if the button is clicked twice or the page is refreshed
            $_SESSION['toast'] = [
                'type' => 'info',
                'message' => 'Account has already been topped up'
            ];
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    if (isset($_POST['do_purchase'])) {
        $upload_dir = 'receipts/';
        // 1. Check if the token exists and matches
        if (isset($_POST['token']) && $_POST['token'] === $_SESSION['submit_token']) {

            $id_user = $_SESSION['user_id']; // Or however you trackthe logged-in user 
            $comment = htmlspecialchars($_POST['comment']);
            $amount  = (float)$_POST['amount'];
            $receipt_path = null;

            // --- FILE UPLOAD LOGIC ---
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                
                $file_tmp_path = $_FILES['receipt']['tmp_name'];
                $file_name = $_FILES['receipt']['name'];
                $file_size = $_FILES['receipt']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Limit size to 5MB
                if ($file_size <= 5000000) {
                    // Generate a unique name: receipt_USERID_TIMESTAMP.ext
                    $new_file_name = "receipt_" . $id_user . "_" . time() . "." . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $receipt_path = $dest_path;
                    }
                } else {
                    $_SESSION['toast'] = [
                        'type' => 'error',
                        'message' => 'File is too large (max 5MB)'
                    ];
                    unset($_SESSION['submit_token']);
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit();
                } 
            }

            // --- DATABASE INSERT ---
            $sql = "INSERT INTO purchases (ID_USER, COMMENT, AMOUNT, RECEIPT_PATH) 
                    VALUES (:id_user, :comment, :amount, :receipt_path)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'id_user'      => $id_user,
                'comment'      => $comment,
                'amount'       => $amount,
                'receipt_path' => $receipt_path
            ]);
            
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Purchase recorded successfully'
            ];
            unset($_SESSION['submit_token']);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }
    
    // Contact Form Logic
    if (isset($_POST['contact_submit'])) {
        // 1. Sanitize inputs
        $name    = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
        $email   = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $message = filter_var(trim($_POST['message']), FILTER_SANITIZE_STRING);
  
        // 3. Send Email to contact@feuzanderie.fr
        $to      = "contact@feuzanderie.fr";
        $subject = "=?UTF-8?B?".base64_encode("New Message from $name")."?=";
        // Construct the email body
        $email_content = $message;

        // Headers (Important for deliverability)
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n"; // Force l'UTF-8
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        mail($to, $subject, $email_content, $headers);

        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Message sent successfully!'
        ];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

}
?>