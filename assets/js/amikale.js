document.getElementById('product').addEventListener('change', function () {
    if (this.value !== '') {
        document.getElementById('qty').focus();
    }
});

document.addEventListener('DOMContentLoaded', function () {

    /* ===============================
       MEMBERS (Choices.js)
       =============================== */

    const membersEl = document.getElementById('members');

    if (membersEl) {
        const membersChoices = new Choices(membersEl, {
            removeItemButton: true,
            searchEnabled: true,
            placeholderValue: '-- Select Members --',
            shouldShowPlaceholder: false,
            shouldSort: true, // Enable sorting
            sorter: function(a, b) {
                // Standard Alphabetical Sort (A to Z)
                return a.label.localeCompare(b.label);
            },
        });

        function syncMembersPlaceholder() {
            const selectedCount = membersChoices.getValue(true).length;
            const inputField = membersChoices.input.element;

            if (selectedCount > 0) {
                inputField.placeholder = '';
                inputField.style.width = '0px';
            } else {
                inputField.placeholder = '-- Select Members --';
                inputField.style.width = '100%';
            }
        }

        // Fix the initial render and keep it in sync afterward.
        syncMembersPlaceholder();
        membersEl.addEventListener('change', syncMembersPlaceholder);
    }

    /* ===============================
       SELL WARNING
       =============================== */

    const sellForm = document.getElementById('sellForm');
    const productEl = document.getElementById('product');
    const qtyEl = document.getElementById('qty');

    if (sellForm && membersEl && productEl && qtyEl) {
        sellForm.addEventListener('submit', function (e) {
            const productOption = productEl.selectedOptions[0];
            const productPrice = productOption ? parseFloat(productOption.dataset.price || '0') : 0;
            const quantity = parseInt(qtyEl.value || '0', 10);
            const total = productPrice * quantity;

            if (!total) {
                return;
            }

            const riskyMembers = Array.from(membersEl.selectedOptions).filter(function (option) {
                const idCustomer = parseInt(option.value, 10);
                const balance = parseFloat(option.dataset.balance || '0');
                const isTrusted = option.dataset.trusted === '1';
                const balanceAfterTransaction = balance - total;

                return idCustomer > 3 && !isTrusted && balanceAfterTransaction < 0;
            });

            if (riskyMembers.length === 0) {
                return;
            }

            const memberNames = riskyMembers.map(function (option) {
                return '• ' + option.text.trim();
            }).join('\n');

            const confirmed = window.confirm(
                '🚨 Alert: This transaction is for a untrusted member and will result in a negative balance after the transaction.\n\n' +
                memberNames +
                '\n\nContinue anyway?'
            );

            if (!confirmed) {
                e.preventDefault();
            }
        });
    }

    /* ===============================
       TOP-UP FLOW (Tom Select)
       Member -> Method -> Amount -> Button
       =============================== */

    tsMember = new TomSelect('.js-member', {
    create: false,
    sortField: { field: 'text', direction: 'asc' },
    onChange: function (value) {
            if (value) {
                document.getElementById('type').focus();
            }
        }
    });

    document.getElementById('type').addEventListener('change', function () {
        if (this.value) {
            document.getElementById('amount').focus();
        }
    });

    document.getElementById('amount').addEventListener('change', function () {
        if (this.value) {
            document.querySelector('button[name="do_topup"]').focus();
        }
    });

});

function viewReceipt(path) {
    const frame = document.getElementById('receipt-frame');
    const preview = document.getElementById('receipt-preview');
    
    frame.src = path; // Charge le fichier (PDF ou Image)
    preview.style.display = 'block'; // Affiche la fenêtre
}

function closeReceipt(event) {
    if (event) event.stopPropagation();
    const preview = document.getElementById('receipt-preview');
    const frame = document.getElementById('receipt-frame');
    preview.style.display = 'none';
    frame.src = '';
}
