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

        // Listen for changes
        membersEl.addEventListener('change', function() {
            const selectedCount = membersChoices.getValue(true).length;
            const inputField = membersChoices.input.element;

            if (selectedCount > 0) {
                // Remove the placeholder attribute to hide it
                inputField.placeholder = "";
                inputField.style.width = "0px";
            } else {
                // Restore it if the list becomes empty
                inputField.placeholder = "-- Select Members --";
                inputField.style.width = "auto";
            }
        });
    }

    /* ===============================
       FORM SUBMIT HANDLER
       =============================== */

    const form = document.getElementById('searchForm');

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            const selectedMembers = formData.getAll('id_customer[]');

            // Debug / usage example
            console.log('Selected members:', selectedMembers);
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