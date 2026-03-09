<?php
    include 'security.php';
    $id_customer = $_GET['id'];

    // 2. Fetch Current Balance
    $stmt_bal = $pdo->prepare("SELECT FIRST_NAME, LAST_NAME, BALANCE FROM customers WHERE ID_CUSTOMER = ?");
    $stmt_bal->execute([$id_customer]);
    $customer = $stmt_bal->fetch();

    // 3. Fetch Top-up History (Using PREPARE instead of query for the placeholder)
    $stmt_activity = $pdo->prepare("
        SELECT 
            'TOPUP' AS TYPE,
            t.AMOUNT AS AMOUNT,
            r.NAME AS LABEL,
            t.CREATED_AT,
            CONCAT(a.FIRST_NAME, ' ', a.LAST_NAME) AS BY_NAME
        FROM wallet_topup t
        JOIN customers c ON t.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN users_customers uc ON t.ID_USER = uc.ID_USER
        LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
        LEFT JOIN ref_topup_type r ON t.ID_TOPUP_TYPE = r.ID_TOPUP_TYPE
        WHERE c.ID_CUSTOMER = ?

        UNION ALL

        SELECT 
            'PURCHASE' AS TYPE,
            -(p.PRICE * tr.QUANTITY) AS AMOUNT,
            CONCAT(tr.QUANTITY, 'x', p.NAME) AS LABEL,
            tr.CREATED_AT,
            CONCAT(a.FIRST_NAME, ' ', a.LAST_NAME) AS BY_NAME
        FROM transactions tr
        JOIN customers c ON tr.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN users_customers uc ON tr.ID_USER = uc.ID_USER
        LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
        LEFT JOIN ref_product p ON tr.ID_PRODUCT = p.ID_PRODUCT
        WHERE c.ID_CUSTOMER = ?

        ORDER BY CREATED_AT DESC
    ");

    $stmt_activity->execute([$id_customer, $id_customer]);
    $activities = $stmt_activity->fetchAll();

    $total_activity = 0;

    foreach ($activities as $a) {
        $total_activity += $a['AMOUNT'];
    }
    // Determine if balance is negative
    $is_negative = $customer['BALANCE'] < 0;
    $balance_color = $is_negative ? 'rgb(227, 125, 125)' : 'rgb(125, 227, 211)';
    $plus = $customer['BALANCE'] > 0 ? '+' : '';
?>

<article id="member">
    <h2 class="major">Member Profile</h2>

    <div style="text-align: center; margin-bottom: 3rem;">
        <h4><?php echo htmlspecialchars($customer['FIRST_NAME'] . " " . $customer['LAST_NAME']); ?></h4>
        <h4 style="font-size: 3rem; color: <?php echo $balance_color; ?>; margin-bottom: 0.5rem;">
            <?php echo $plus . number_format($customer['BALANCE'], 0); ?>€
        </h4>
    </div>
    
    <h3>Transaction History</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">
                                No activity yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $a): ?>
                            <?php
                                $is_topup = $a['AMOUNT'] > 0;
                                $color = $is_topup ? 'rgb(125,227,211)' : 'rgb(227,125,125)';
                                $sign = $is_topup ? '+' : '';
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($a['LABEL']) ?>
                                </td>
                                <td style="color:<?= $color ?>; font-weight:bold;">
                                    <?= $sign . number_format($a['AMOUNT'], 0) ?>€
                                </td>
                                <td><?= date('d/m/y H:i:s', strtotime($a['CREATED_AT'])) ?></td>
                                <td><?= htmlspecialchars($a['BY_NAME']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                        $is_positive = $total_activity >= 0;
                        $total_color = $is_positive ? 'rgb(125,227,211)' : 'rgb(227,125,125)';
                        $sign = $total_activity > 0 ? '+' : '';
                    ?>
                    <tr>
                        <th style="text-align:left;">Total</th>
                        <th colspan="1" style="color:<?= $total_color ?>; font-weight:bold;">
                            <?= $sign . number_format($total_activity, 0) ?>€
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
</article>