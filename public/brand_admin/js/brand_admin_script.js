// brand_admin/js/brand_admin_script.js

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
    scrollTopButton.style.backgroundColor = '#3f51b5'; // Brand Admin primary color
    scrollTopButton.style.color = 'white';
    scrollTopButton.style.border = 'none';
    scrollTopButton.style.borderRadius = '5px';
    scrollTopButton.style.cursor = 'pointer';
    scrollTopButton.style.display = 'none'; // Hidden by default
    scrollTopButton.style.zIndex = '1001'; // Above header if it's sticky
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

    // 2. Auto-dismiss success/error messages after a few seconds
    const adminMessages = document.querySelectorAll('.brand-admin-message.success, .brand-admin-message.error, .brand-admin-message.warning, .brand-admin-message.info');
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

    // 3. Select All checkbox functionality for tables (e.g., products.php)
    const selectAllCheckbox = document.getElementById('select-all-products');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            productCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!checkbox.checked) {
                selectAllCheckbox.checked = false;
            } else {
                // Check if all are checked
                let allChecked = true;
                productCheckboxes.forEach(cb => {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                selectAllCheckbox.checked = allChecked;
            }
        });
    });

    console.log('Brand Admin script loaded and initialized.');
});