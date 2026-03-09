<?php
    include 'security.php';
    // Split customers into two arrays
    $positives = array_filter($customers, fn($c) => $c['BALANCE'] >= 0);
    $negatives = array_filter($customers, fn($c) => $c['BALANCE'] < 0);
    
    // Calculate Subtotals
    $posTotal = array_sum(array_column($positives, 'BALANCE'));
    $negTotal = array_sum(array_column($negatives, 'BALANCE'));

    // Helper function to render table rows to keep code DRY (Don't Repeat Yourself)
    function renderRows($data) {
        foreach ($data as $c): 
            $balance = (float) $c['BALANCE'];
            $color = $balance >= 0 ? 'rgb(125,227,211)' : 'rgb(227, 125, 125);';
            $plus = $balance > 0 ? '+' : '';
            $rowClass = $c['IS_ACTIVE'] == 0 ? 'inactive-row' : '';
            // Create the URL string
            $memberUrl = "./?id=" . $c['ID_CUSTOMER'] . "#member";
            ?>
            <tr class="member-row <?= $rowClass ?>" data-name="<?= strtolower($c['FIRST_NAME'] . ' ' . $c['LAST_NAME']); ?>">
                <td>
                    <a href="<?= $memberUrl ?>" style="text-decoration: none; color: inherit; font-weight: 500;">
                        <?= $c['FIRST_NAME'] . ' ' . $c['LAST_NAME']; ?>
                    </a>
                </td>
                <td style="color: <?= $color; ?>;font-weight: bold;"><?= $plus . number_format($balance, 0); ?> €</td>
                <td>
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="id_customer" value="<?= $c['ID_CUSTOMER'] ?>">
                        <input type="hidden" name="set_active" value="<?= $c['IS_ACTIVE'] == 1 ? 0 : 1 ?>">
                        <button type="submit" name="toggle_cust" 
                                class="icon solid <?= $c['IS_ACTIVE'] == 1 ? 'fa-eye' : 'fa-eye-slash' ?>"
                                title="<?= $c['IS_ACTIVE'] == 1 ? 'Hide' : 'Show' ?>"
                                style="background:none; box-shadow:none; border:0; cursor:pointer;font-size: 1.2rem;">
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach;
    }
?>

<article id="members">
    <h2 class="major">Members</h2>
    <div class="toast-container toast">
        <span class="toast-message"></span>
        <div class="toast-progress"></div>
    </div>
    <form method="post" action="">
        <div class="fields">
            <div class="field">
                <input type="text" name="first_name" placeholder="First Name" required />
            </div>
            <div class="field">
                <input type="text" name="last_name" placeholder="Last Name" required />
            </div>
            <div class="field">
                <button type="submit" name="add_customer" class="primary fit">
                    <i class="fa fa-plus-circle"></i> New Member
                </button>
            </div>
        </div>
    </form>

    <hr  />

    <div style="display: flex; gap: 20px; margin-bottom: 30px; text-align: center;">
        <div style="flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
            <small>Wallet Credit</small>
            <div style="font-size: 1.5rem; color: rgb(125,227,211); font-weight: bold;">
                +<?= number_format($posTotal, 0); ?> €
            </div>
        </div>
        <div style="flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">
            <small>Wallet Debt</small>
            <div style="font-size: 1.5rem; color: rgb(227, 125, 125); font-weight: bold;">
                <?= number_format($negTotal, 0); ?> €
            </div>
        </div>
    </div>

        <div style="margin-bottom: 20px;">
        <input type="text" id="memberSearch" placeholder="Search members by name..." 
            style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
    </div>

    <div class="table-section">
        <h3 style="color: rgb(227, 125, 125);">Negative Balances</h3>
        <div class="table-wrapper">
            <table>
                <tbody id="negTable">
                    <?php renderRows($negatives); ?>
                </tbody>
                </table>
        </div>
    </div>

    <hr class="table-divider">

    <div class="table-section">
        <h3 style="color: rgb(125, 227, 211);">Positive Balances</h3>
        <div class="table-wrapper">
            <table>
                <tbody id="posTable">
                    <?php renderRows($positives); ?>
                </tbody>
                </table>
        </div>
    </div>
</article>

<script>
document.getElementById('memberSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const sections = document.querySelectorAll('.table-section');
    const divider = document.querySelector('.table-divider');
    let visibleSections = 0;

    sections.forEach(section => {
        const rows = section.querySelectorAll('.member-row');
        let hasMatch = false;

        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            if (name.includes(filter)) {
                row.style.display = "";
                hasMatch = true;
            } else {
                row.style.display = "none";
            }
        });

        // Hide the entire section (Title + Table + Subtotal) if no matches found
        if (hasMatch) {
            section.style.display = "block";
            visibleSections++;
        } else {
            section.style.display = "none";
        }
    });

    // Hide the horizontal divider if one or zero tables are showing
    if (divider) {
        divider.style.display = (visibleSections > 1) ? "block" : "none";
    }
});
</script>

<style>
    .inactive-row { opacity: 0.5; background: rgba(255,0,0,0.05); }
    .tables-container hr { margin: 40px 0; border: 0; border-top: 1px solid #eee; }
</style>