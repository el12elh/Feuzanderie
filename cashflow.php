<?php
    $limit = 20;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $total_rows = $pdo->query("SELECT (
        (SELECT COUNT(*) FROM wallet_topup WHERE ID_TOPUP_TYPE IN (2, 3, 6)) 
        + 
        (SELECT COUNT(*) FROM purchases)
    ) AS total_transactions")->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT
        CONCAT(c.FIRST_NAME, ' ', c.LAST_NAME) AS CUSTOMER,
        t.AMOUNT AS AMOUNT,
        r.NAME AS LABEL,
        t.CREATED_AT,
        CONCAT(a.FIRST_NAME, ' ', a.LAST_NAME) AS BY_NAME,
        NULL AS RECEIPT_PATH
    FROM wallet_topup t
    JOIN customers c ON t.ID_CUSTOMER = c.ID_CUSTOMER
    LEFT JOIN users_customers uc ON t.ID_USER = uc.ID_USER
    LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
    LEFT JOIN ref_topup_type r ON t.ID_TOPUP_TYPE = r.ID_TOPUP_TYPE
    WHERE t.ID_TOPUP_TYPE IN (2, 3, 6)
    UNION
    SELECT
        ' -Amikale' AS CUSTOMER,
        -p.AMOUNT,
        p.COMMENT AS LABEL,
        p.CREATED_AT,
        CONCAT(a.FIRST_NAME, ' ', a.LAST_NAME) AS BY_NAME,
        RECEIPT_PATH
    FROM purchases p
    LEFT JOIN users_customers uc ON p.ID_USER = uc.ID_USER
    LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
    ORDER BY CREATED_AT DESC, CUSTOMER, LABEL
    LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cash_flow = $stmt->fetchAll();
?>

<article id="cashflow">
    <h2 class="major">Cash Flow</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Details</th>
                    <th>Date</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ($cash_flow as $tr):
                $color = $tr['AMOUNT'] > 0 ? 'rgb(42, 201, 134)' : 'rgb(255, 95, 109)';
                $sign = $tr['AMOUNT'] > 0 ? '+' : '';
            ?>
                <tr>
                    <td>
                        <?= $tr['CUSTOMER'] ?>
                        <?php if (!empty($tr['RECEIPT_PATH'])): ?>
                            <br>
                            <a href="javascript:void(0);" 
                            onclick="viewReceipt('<?= htmlspecialchars($tr['RECEIPT_PATH']) ?>')"
                            style="font-size: 0.8rem; color: <?= $color ?>; text-decoration: none; border-bottom: 1px dashed <?= $color ?>;">
                                <i class="fa fa-paperclip"></i> Receipt
                            </a>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                        <span style="color:<?= $color ?>; font-weight:bold;">
                            <?= $sign . number_format($tr['AMOUNT'], 0, ',', ''); ?>€
                        </span>
                        <br><?= $tr['LABEL'] ?>
                    </td>
                    <td><?= date('d/m/y H:i:s', strtotime($tr['CREATED_AT'])) ?></td>
                    <td><?= $tr['BY_NAME'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 20px; text-align: center;">
        <ul class="actions">
            <?php if ($page > 1): ?>
                <li><a href="?page=<?= $page - 1 ?>#cashflow" class="button small">Previous</a></li>
            <?php endif; ?>

            <li><span>Page <?= $page ?> / <?= $total_pages ?></span></li>

            <?php if ($page < $total_pages): ?>
                <li><a href="?page=<?= $page + 1 ?>#cashflow" class="button small">Next</a></li>
            <?php endif; ?>
        </ul>
    </div>
</article>