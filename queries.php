<?php
    // Fetch products
    $all_prods = $pdo
        ->query("SELECT * FROM ref_product ORDER BY IS_ACTIVE, ID_PRODUCT")
        ->fetchAll();

    // GET only below
    $customers = $pdo
        ->query("SELECT * FROM customers WHERE ID_CUSTOMER > 3 ORDER BY IS_ACTIVE, BALANCE, FIRST_NAME, LAST_NAME")
        ->fetchAll();

    // Fetch Users NOT yet linked to any customer
    $unlinked_users = $pdo->query("
        SELECT u.ID_USER, u.EMAIL 
        FROM users u 
        LEFT JOIN users_customers uc ON u.ID_USER = uc.ID_USER
        WHERE uc.ID_USER IS NULL
        ORDER BY u.EMAIL
    ")->fetchAll();

    // Fetch Customers and their linked Email (if any)
    $all_customers = $pdo->query("
        SELECT c.ID_CUSTOMER, c.FIRST_NAME, c.LAST_NAME, u.ID_USER AS LINKED_USER_ID, u.EMAIL, u.IS_ADMIN 
        FROM customers c
        LEFT JOIN users_customers uc ON c.ID_CUSTOMER = uc.ID_CUSTOMER
        LEFT JOIN users u ON uc.ID_USER = u.ID_USER
        WHERE c.ID_CUSTOMER >3
        ORDER BY u.IS_ADMIN, c.FIRST_NAME, c.LAST_NAME
    ")->fetchAll();

    // Fetch data for dropdowns and tables
    $customers_1 = $pdo->query("SELECT ID_CUSTOMER, FIRST_NAME, LAST_NAME, BALANCE FROM customers WHERE IS_ACTIVE = 1 ORDER BY FIRST_NAME, LAST_NAME")->fetchAll();
    $customers_2 = $pdo->query("SELECT ID_CUSTOMER, FIRST_NAME, LAST_NAME, BALANCE FROM customers WHERE (IS_ACTIVE = 1 OR BALANCE < 0) AND ID_CUSTOMER > 3  ORDER BY FIRST_NAME, LAST_NAME")->fetchAll();
    $products = $pdo->query("SELECT * FROM ref_product WHERE IS_ACTIVE = 1 ORDER BY ID_PRODUCT")->fetchAll();
    $topup_types = $pdo->query("SELECT * FROM ref_topup_type WHERE ID_TOPUP_TYPE != 1 ORDER BY ID_TOPUP_TYPE")->fetchAll();
    $transactions = $pdo->query("SELECT * FROM v_master_transactions LIMIT 20")->fetchAll();
    $all_transactions = $pdo->query("SELECT * FROM v_master_transactions")->fetchAll();
    $cash_flow = $pdo->query("SELECT * FROM v_cash_flow")->fetchAll();
?>