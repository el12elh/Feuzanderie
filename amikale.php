<?php
    include 'security.php'
?>

<article id="amikale">
    <h2 class="major">Amikale</h2>
    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>
    <!-- =================== Sell Product =================== -->
    <section id="sell-product">
        <form method="post">
            <div class="fields">
                <div class="field">
                    <select id="members" name="id_customer[]" multiple required>
                        <?php foreach ($customers_1 as $c): 
                            $plus = $c['BALANCE'] > 0 ? '+' : '';
                        ?>
                        <option value="<?= $c['ID_CUSTOMER']; ?>">
                            <?= $c['FIRST_NAME'] . ' ' . $c['LAST_NAME'] . " " . $plus . number_format($c['BALANCE'],0); ?>€
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <select name="id_product" id="product" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['ID_PRODUCT']; ?>">
                            <?= $p['NAME'] . " (" . number_format($p['PRICE'],0) . "€)"; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <select name="qty" id="qty" required>
                        <option value="">-- Select Quantity --</option>
                        <?php for ($i=1; $i<=25; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                <div class="field">
                    <button type="submit" name="sell" class="primary fit">
                        <i class="fa fa-shopping-cart"></i> Sell
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- =================== Top-Up Wallet =================== -->
    <section id="topup-wallet">
        <form method="post">
            <div class="fields">
                <div class="field">
                    <select name="id_customer" class="js-member" required>
                        <option value="">-- Select Member --</option>
                        <?php foreach ($customers_2 as $c): 
                            $plus = $c['BALANCE'] > 0 ? '+' : '';
                        ?>
                        <option value="<?= $c['ID_CUSTOMER']; ?>">
                            <?= $c['FIRST_NAME'] . ' ' . $c['LAST_NAME'] . " " . $plus . number_format($c['BALANCE'], 0); ?>€
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <select name="id_type" id="type" required>
                        <option value="">-- Select Method --</option>
                        <?php foreach ($topup_types as $t): ?>
                        <option value="<?= $t['ID_TOPUP_TYPE']; ?>"><?= $t['NAME']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <select name="amount" id="amount" required>
                        <option value="">-- Select Amount --</option>
                        <?php for ($i=1;$i<=200;$i++): ?>
                        <option value="<?= $i ?>">+<?= $i ?>€</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                <div class="field">
                    <button type="submit" name="do_topup" class="primary fit">
                        <i class="fa fa-wallet"></i> Top-Up
                    </button>
                </div>
            </div>
        </form>
    </section> 
    
    <!-- =================== Purchase =================== -->
    <section id="purchase">
        <form method="post" enctype="multipart/form-data">
            <div class="fields">
                <div class="field">
                    <input type="text" name="comment" maxlength="100" placeholder="Stock, Resto, Bar, etc." required />
                </div>
                
                <div class="field">
                    <input type="number" name="amount" step="0.01" placeholder="Amount (€)" min="1" required />
                </div>

                <div class="field">
                    <label for="receipt">Attach Receipt</label>
                    <input type="file" name="receipt" id="receipt" accept="image/*,.pdf" />
                </div>

                <input type="hidden" name="token" value="<?= $_SESSION['submit_token']; ?>">
                
                <div class="field">
                    <button type="submit" name="do_purchase" class="primary fit">
                        <i class="fa fa-receipt"></i> Purchase
                    </button>
                </div>
            </div>
        </form>
    </section>
    <hr />
    
    <!-- =================== Latest Transactions =================== -->
    <section id="latest-transactions">                     
        <h3>Latest Transactions</h3>
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
                foreach ($transactions as $tr):
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
                        <td>
                            <span style="color:<?= $color ?>;font-weight:bold;"><?= $sign . number_format($tr['AMOUNT'], 0, ',', ' '); ?>€</span>
                            <br><?= $tr['LABEL']?> 
                        </td>
                        <td><?= date('d/m/y H:i:s', strtotime($tr['CREATED_AT'])) ?></td>
                        <td><?= $tr['BY_NAME'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>          
        <ul class="actions">
            <li><a href="export_transactions" class="button primary icon solid fa-download">Download All Transactions</a></li>
        </ul>
    </section>
</article>