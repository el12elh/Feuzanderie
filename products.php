<?php
    include 'security.php';
?>

<article id="products">
    <h2 class="major">Products</h2>
    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>
    <form method="post" action="">
        <div class="fields">
            <div class="field">
                <input type="text" name="prod_name" placeholder="Beer, Cocktail, etc." required />
            </div>

            <div class="field">
                <input type="number" name="prod_price" step="0.01" placeholder="Amount (€)" min="1" max="1000" required />
            </div>

            <div class="field">
                <button type="submit" name="add_product" class="primary fit">
                    <i class="fa fa-plus-circle"></i> New Product
                </button>
            </div>
        </div>
    </form>
    
    <hr />
    <h3>Inventory</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_prods as $p): ?>
                <tr style="<?php echo $p['IS_ACTIVE'] == 0 ? 'opacity: 0.5; background: rgba(255,0,0,0.05);' : ''; ?>">
                    <td><?php echo $p['NAME']; ?></td>
                    <td><?php echo number_format($p['PRICE'], 0); ?> €</td>
                    <td>
                        <?php if ($p['IS_ACTIVE'] == 1): ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="id_product" value="<?= $p['ID_PRODUCT'] ?>">
                            <input type="hidden" name="set_active" value="0">
                            <button type="submit" name="toggle_prod" 
                                    class="icon solid fa-eye-slash"
                                    title="Hide Product"
                                    style="background:none; box-shadow:none; border:0; cursor:pointer;font-size: 1.2rem;">
                                <span class="label">Hide</span>
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="id_product" value="<?= $p['ID_PRODUCT'] ?>">
                            <input type="hidden" name="set_active" value="1">
                            <button type="submit" name="toggle_prod" 
                                    class="icon solid fa-eye"
                                    title="Show Product"
                                    style="background:none; box-shadow:none; border:0; cursor:pointer;font-size: 1.2rem;">
                                <span class="label">Show</span>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>