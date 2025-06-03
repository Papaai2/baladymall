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
    scrollTopButton.style.backgroundColor = '#3f51b5'; /* Indigo */
    scrollTopButton.style.color = 'white';
    scrollTopButton.style.border = 'none';
    scrollTopButton.style.borderRadius = '5px';
    scrollTopButton.style.cursor = 'pointer';
    scrollTopButton.style.display = 'none'; // Hidden by default
    scrollTopButton.style.zIndex = '1001';
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
    const adminMessages = document.querySelectorAll('.brand-admin-message.success, .brand-admin-message.info');
    adminMessages.forEach(function(messageDiv) {
        setTimeout(function() {
            messageDiv.style.transition = 'opacity 0.5s ease-out';
            messageDiv.style.opacity = '0';
            setTimeout(function() {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 550);
        }, 5000); // Dismiss after 5 seconds
    });

    console.log('Brand Admin script loaded and initialized.');
});
