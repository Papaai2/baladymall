// admin/js/admin_script.js

document.addEventListener('DOMContentLoaded', function() {

    // 1. Smooth scroll to top button (appears when scrolled down)
    const scrollTopButton = document.createElement('button');
    scrollTopButton.innerHTML = '&uarr;'; // Up arrow
    scrollTopButton.setAttribute('id', 'scrollTopBtn');
    scrollTopButton.style.position = 'fixed';
    scrollTopButton.style.bottom = '20px';
    scrollTopButton.style.right = '20px';
    scrollTopButton.style.padding = '10px 15px';
    scrollTopButton.style.fontSize = '18px';
    scrollTopButton.style.backgroundColor = '#007bff';
    scrollTopButton.style.color = 'white';
    scrollTopButton.style.border = 'none';
    scrollTopButton.style.borderRadius = '5px';
    scrollTopButton.style.cursor = 'pointer';
    scrollTopButton.style.display = 'none'; // Hidden by default
    scrollTopButton.style.zIndex = '1001'; // Above admin header if it's sticky
    scrollTopButton.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
    document.body.appendChild(scrollTopButton);

    window.onscroll = function() {
        if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
            scrollTopButton.style.display = 'block';
        } else {
            scrollTopButton.style.display = 'none';
        }
    };

    scrollTopButton.addEventListener('click', function() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    // 2. Confirm before submitting delete forms (already in PHP, but can be enhanced)
    // This is a generic enhancement if you want to apply it to more forms.
    // The existing onsubmit="return confirm(...)" in PHP is often sufficient.
    // Example: Add a class 'confirm-delete-form' to forms needing this.
    // const deleteForms = document.querySelectorAll('.confirm-delete-form'); // Example class
    // deleteForms.forEach(form => {
    //     form.addEventListener('submit', function(event) {
    //         if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
    //             event.preventDefault();
    //         }
    //     });
    // });

    // 3. Active state for navigation links (already handled by PHP, but JS can be an alternative)
    // The PHP method is generally better as it works even if JS is disabled.

    // 4. Simple fade-in effect for the main content area on page load
    const adminMainContainer = document.querySelector('.admin-main .admin-container');
    if (adminMainContainer) {
        adminMainContainer.style.opacity = '0';
        adminMainContainer.style.transition = 'opacity 0.5s ease-in-out';
        setTimeout(() => {
            adminMainContainer.style.opacity = '1';
        }, 50); // Small delay to ensure transition applies
    }
    
    // 5. Toggle visibility for password fields (if you add any user edit forms with passwords)
    // Example: Add a class 'toggle-password-visibility' to a button/icon next to a password field
    // const togglePasswordButtons = document.querySelectorAll('.toggle-password-visibility');
    // togglePasswordButtons.forEach(button => {
    //     button.addEventListener('click', function() {
    //         const passwordInput = this.previousElementSibling; // Assuming input is right before button
    //         if (passwordInput && passwordInput.type === 'password') {
    //             passwordInput.type = 'text';
    //             this.textContent = 'Hide'; // Or change icon
    //         } else if (passwordInput) {
    //             passwordInput.type = 'password';
    //             this.textContent = 'Show'; // Or change icon
    //         }
    //     });
    // });

    // 6. Auto-dismiss success/error messages after a few seconds
    const adminMessages = document.querySelectorAll('.admin-message.success, .admin-message.error, .admin-message.warning, .admin-message.info');
    adminMessages.forEach(function(messageDiv) {
        // Only auto-dismiss if it's not an error message that requires attention,
        // or if it doesn't contain form validation errors (which often have lists).
        // For simplicity, let's dismiss success and info messages.
        if (messageDiv.classList.contains('success') || messageDiv.classList.contains('info')) {
            setTimeout(function() {
                messageDiv.style.transition = 'opacity 0.5s ease-out';
                messageDiv.style.opacity = '0';
                setTimeout(function() {
                    if (messageDiv.parentNode) { // Check if still in DOM
                        messageDiv.remove();
                    }
                }, 550); // Wait for fade out to complete
            }, 5000); // Dismiss after 5 seconds
        }
    });
    
    // 7. "Select All" checkbox functionality for tables (like in products.php)
    // This is already implemented in products.php, but if you want a generic version:
    // const selectAllCheckboxes = document.querySelectorAll('.select-all-table-rows');
    // selectAllCheckboxes.forEach(selectAll => {
    //     const table = selectAll.closest('table');
    //     if (table) {
    //         const rowCheckboxes = table.querySelectorAll('.row-checkbox'); // Add 'row-checkbox' to your item checkboxes
    //         selectAll.addEventListener('change', function() {
    //             rowCheckboxes.forEach(checkbox => {
    //                 checkbox.checked = selectAll.checked;
    //             });
    //         });
    //         rowCheckboxes.forEach(checkbox => {
    //             checkbox.addEventListener('change', function() {
    //                 if (!checkbox.checked) {
    //                     selectAll.checked = false;
    //                 } else {
    //                     let allChecked = true;
    //                     rowCheckboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
    //                     selectAll.checked = allChecked;
    //                 }
    //             });
    //         });
    //     }
    // });


    console.log('Admin script loaded and initialized.');
    // Add more general admin panel JS interactions here
});

// You can add more specific functions outside the DOMContentLoaded if needed
// e.g., function openModal(modalId) { ... }
