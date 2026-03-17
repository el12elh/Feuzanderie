<?php
    include 'security.php';
?>

<article id="admin">
    <h2 class="major">Admin</h2>
    <!-- Toast -->
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Member / Email</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_customers as $ac):
                    $isLinked = !empty($ac['LINKED_USER_ID']);
                    $isAdmin  = ($ac['IS_ADMIN'] === 1);
                ?>
                <tr>
                    <?php if (!$isLinked): ?>
                    <form method="post" action="" style="margin:0;">
                        <td>
                            <?php echo htmlspecialchars($ac['FIRST_NAME'] . " " . $ac['LAST_NAME']); ?><br />
                            <select name="id_user" required style="height: 2.5rem; font-size: 0.8rem;">
                                <option value="">-- Select Email --</option>
                                <?php foreach ($unlinked_users as $uu): ?>
                                    <option value="<?php echo $uu['ID_USER']; ?>"><?php echo $uu['EMAIL']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="text-align:center;">
                            <input type="hidden" name="id_customer" value="<?php echo $ac['ID_CUSTOMER']; ?>">   
                            <button type="submit" name="link_account" style="background:none; box-shadow:none; padding:0; border:0; cursor:pointer;">
                                <span class="icon solid fa-link" style="color:rgb(42, 201, 134); font-size: 1rem;" title="Link Account"></span>
                            </button>     
                        </td>
                    </form>            
                    <?php else: ?>
                        <td>
                            <?php echo htmlspecialchars($ac['FIRST_NAME'] . " " . $ac['LAST_NAME']); ?><br />
                            <?php echo htmlspecialchars($ac['EMAIL']); ?>
                        </td>
                        <td style="text-align:center;">
                            <form method="post" action="" style="margin:0;">
                                <input type="hidden" name="id_customer" value="<?php echo $ac['ID_CUSTOMER']; ?>">
                                <input type="hidden" name="id_user" value="<?php echo $ac['LINKED_USER_ID']; ?>">
                                <button type="submit" name="unlink_account" style="background:none; box-shadow:none; padding:0; border:0; cursor:pointer;">
                                    <span class="icon solid fa-unlink" style="color:rgb(255, 95, 109); font-size: 1rem;" title="Unlink Account"></span>
                                </button>
                            </form>
                            <?php if ($isAdmin): ?>
                            <form method="post" action="" style="margin:0; display:inline;">
                                <input type="hidden" name="id_user" value="<?php echo $ac['LINKED_USER_ID']; ?>">
                                <input type="hidden" name="set_admin" value="0">
                                <button type="submit" name="toggle_admin"
                                        style="background:none; box-shadow:none; border:0; cursor:pointer;">
                                    <span class="icon solid fa-user-minus"
                                        style="color:rgb(255, 95, 109); font-size: 1rem;"
                                        title="Remove Admin"></span>
                                </button>
                            </form>
                            <?php elseif (!$isAdmin): ?>
                            <form method="post" action="" style="margin:0; display:inline;">
                                <input type="hidden" name="id_user" value="<?php echo $ac['LINKED_USER_ID']; ?>">
                                <input type="hidden" name="set_admin" value="1">
                                <button type="submit" name="toggle_admin"
                                        style="background:none; box-shadow:none; border:0; cursor:pointer;">
                                    <span class="icon solid fa-user-plus"
                                        style="color:rgb(42, 201, 134); font-size: 1rem;"
                                        title="Make Admin"></span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>