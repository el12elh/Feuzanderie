<?php
    $limit = 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $total_rows = $pdo->query("SELECT (
        (SELECT COUNT(*) FROM wallet_topup WHERE ID_TOPUP_TYPE IN (2, 3, 6)) 
        + 
        (SELECT COUNT(*) FROM purchases)
    ) AS total_transactions")->fetchColumn();
    $total_pages = ceil($total_rows / $limit);
    $global_total = (int)$pdo->query("SELECT (
        (SELECT COALESCE(SUM(t.AMOUNT), 0)
        FROM wallet_topup t
        WHERE t.ID_TOPUP_TYPE IN (2, 3, 6))
        -
        (SELECT COALESCE(SUM(p.AMOUNT), 0)
        FROM purchases p)
    ) AS global_total")->fetchColumn();

    $sql = "SELECT
        c.FIRST_NAME AS FIRST_NAME,
        c.LAST_NAME AS LAST_NAME,
        t.AMOUNT AS AMOUNT,
        r.NAME AS LABEL,
        t.CREATED_AT,
        a.FIRST_NAME AS BY_FIRST_NAME,
        a.LAST_NAME AS BY_LAST_NAME,
        NULL AS RECEIPT_PATH
    FROM wallet_topup t
    JOIN customers c ON t.ID_CUSTOMER = c.ID_CUSTOMER
    LEFT JOIN users_customers uc ON t.ID_USER = uc.ID_USER
    LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
    LEFT JOIN ref_topup_type r ON t.ID_TOPUP_TYPE = r.ID_TOPUP_TYPE
    WHERE t.ID_TOPUP_TYPE IN (2, 3, 6)
    UNION
    SELECT
        '-Amikale' AS FIRST_NAME,
        '' AS LAST_NAME,
        -p.AMOUNT,
        p.COMMENT AS LABEL,
        p.CREATED_AT,
        a.FIRST_NAME AS BY_FIRST_NAME,
        a.LAST_NAME AS BY_LAST_NAME,
        RECEIPT_PATH
    FROM purchases p
    LEFT JOIN users_customers uc ON p.ID_USER = uc.ID_USER
    LEFT JOIN customers a ON uc.ID_CUSTOMER = a.ID_CUSTOMER
    ORDER BY CREATED_AT DESC, FIRST_NAME, LAST_NAME, LABEL
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
                    <td style="white-space: nowrap;">
                        <?= htmlspecialchars($tr['FIRST_NAME']) ?>
                        <?php if (!empty($tr['LAST_NAME'])): ?>
                            <br><?= htmlspecialchars($tr['LAST_NAME']) ?>
                        <?php endif; ?>
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
                    <td style="white-space: nowrap;">
                        <?= htmlspecialchars($tr['BY_FIRST_NAME'] ?? '') ?>
                        <?php if (!empty($tr['BY_LAST_NAME'])): ?>
                            <br><?= htmlspecialchars($tr['BY_LAST_NAME']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php
                    $is_global_positive = $global_total >= 0;
                    $global_total_color = $is_global_positive ? 'rgb(42, 201, 134)' : 'rgb(255, 95, 109)';
                    $global_sign = $global_total > 0 ? '+' : '';
                ?>
                <tr>
                    <th style="text-align:left;">Global Total</th>
                    <th style="color:<?= $global_total_color ?>; font-weight:bold;">
                        <?= $global_sign . number_format($global_total, 0, ',', ' ') ?>€
                    </th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
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
