/**
 * BaladyMall - Main JavaScript File
 * public/js/script.js
 *
 * REVISION: All animated text functionality removed.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('BaladyMall JavaScript Initialized!');

    // --- Sticky Header Functionality ---
    const header = document.querySelector('.site-header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // --- Smooth Scroll for Anchor Links ---
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const hrefAttribute = anchor.getAttribute('href');
            if (hrefAttribute.length > 1 && document.querySelector(hrefAttribute)) {
                e.preventDefault();
                document.querySelector(hrefAttribute).scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Back to Top Button ---
    let backToTopButton = document.getElementById('backToTopBtn');
    if (!backToTopButton) {
        backToTopButton = document.createElement('button');
        backToTopButton.id = 'backToTopBtn';
        backToTopButton.innerHTML = '&uarr; <span class="sr-only">Back to Top</span>';
        backToTopButton.title = 'Go to top';
        backToTopButton.style.position = 'fixed';
        backToTopButton.style.bottom = '20px';
        backToTopButton.style.right = '20px';
        backToTopButton.style.display = 'none';
        backToTopButton.style.padding = '10px 15px';
        backToTopButton.style.fontSize = '18px';
        backToTopButton.style.backgroundColor = '#007bff'; // This color is temporary, should use CSS var
        backToTopButton.style.color = 'white';
        backToTopButton.style.border = 'none';
        backToTopButton.style.borderRadius = '5px';
        backToTopButton.style.cursor = 'pointer';
        backToTopButton.style.zIndex = '999';
        document.body.appendChild(backToTopButton);
    }

    window.addEventListener('scroll', function() {
        if (window.scrollY > 200) {
            if(backToTopButton) backToTopButton.style.display = 'block';
        } else {
            if(backToTopButton) backToTopButton.style.display = 'none';
        }
    });

    if(backToTopButton) {
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- REMOVED: Hero Section Text Typing Animation ---
    // The code for the animated text has been removed from this file.


    // --- Scroll-triggered Animations (Fade-in) ---
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    if (animatedElements.length > 0) {
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observerInstance) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observerInstance.unobserve(entry.target);
                }
            });
        }, observerOptions);

        animatedElements.forEach(el => {
            observer.observe(el);
        });
    }

    // --- Form Validations ---
    const formsToValidate = document.querySelectorAll('.auth-form, .profile-edit-form, .change-password-form');
    formsToValidate.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                // HTML5 validation messages will show by default.
            }
            form.classList.add('submitted');
        });
    });

    // --- Custom Alert/Message Box Handling (Improved version) ---
    window.showCustomMessage = function(message, type = 'info', duration = 4000) {
        const existingBox = document.querySelector('.custom-message-box');
        if(existingBox) existingBox.remove();
        
        const messageBox = document.createElement('div');
        messageBox.className = `custom-message-box custom-message-${type}`;
        messageBox.setAttribute('role', 'alert');
        messageBox.innerHTML = `<span>${message}</span><button class="close-custom-message" aria-label="Close message">&times;</button>`;
        document.body.appendChild(messageBox);
        
        requestAnimationFrame(() => {
            messageBox.classList.add('visible');
        });

        const closeMessage = () => {
            messageBox.classList.remove('visible');
            messageBox.addEventListener('transitionend', () => messageBox.remove(), { once: true });
        };

        const closeButton = messageBox.querySelector('.close-custom-message');
        if (closeButton) {
            closeButton.addEventListener('click', closeMessage);
        }
        
        if (duration > 0) {
            setTimeout(closeMessage, duration);
        }
    };

    // Display any PHP-generated global messages that might be present on initial page load
    const phpGlobalMessage = document.getElementById('phpGlobalSuccessMessage');
    if (phpGlobalMessage) {
        window.showCustomMessage(phpGlobalMessage.textContent.trim(), 'success');
        phpGlobalMessage.remove();
    }
    const phpGlobalErrorMessage = document.getElementById('phpGlobalErrorMessage');
    if (phpGlobalErrorMessage) {
        window.showCustomMessage(phpGlobalErrorMessage.textContent.trim(), 'error');
        phpGlobalErrorMessage.remove();
    }


    // --- Mobile Menu Toggle ---
    const mobileMenuButton = document.getElementById('mobile-menu-toggle'); // **FIXED LINE**
    const mainNav = document.querySelector('.main-navigation');

    if (mobileMenuButton && mainNav) {
        mobileMenuButton.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            mobileMenuButton.classList.toggle('is-active');
            const isExpanded = mainNav.classList.contains('active');
            mobileMenuButton.setAttribute('aria-expanded', isExpanded.toString());
        });

        // Close menu when clicking outside of it
        document.addEventListener('click', function(event) {
            // Check if the click was outside the nav and the toggle button
            if (!mainNav.contains(event.target) && !mobileMenuButton.contains(event.target) && mainNav.classList.contains('active')) {
                mainNav.classList.remove('active');
                mobileMenuButton.classList.remove('is-active');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
        // Optional: Close menu when a navigation link is clicked (common mobile menu behavior)
        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mainNav.classList.remove('active');
                mobileMenuButton.classList.remove('is-active');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            });
        });
    }


    // --- AJAX Add to Cart Functionality ---
    const ajaxAddToCartForms = document.querySelectorAll('.ajax-add-to-cart-form');
    const cartCountSpan = document.querySelector('.header-actions .cart-count');

    ajaxAddToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const formData = new FormData(this); // Get form data
            const formActionUrl = this.getAttribute('action'); 
            
            if (typeof formActionUrl !== 'string' || !formActionUrl) {
                console.error("AJAX Add to Cart: Form action URL is invalid or empty. Aborting fetch.");
                window.showCustomMessage("Configuration error: Cart action URL is missing.", 'error');
                return;
            }

            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton ? submitButton.innerHTML : 'Add to Cart';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = 'Adding...';
            }

            const currentMessages = document.querySelectorAll('.custom-message-box');
            currentMessages.forEach(msg => msg.remove());

            fetch(formActionUrl, { // Send AJAX request to form's action URL
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest', // Identify as AJAX request
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Server responded with non-OK status:', response.status, text);
                        throw new Error(`HTTP error! status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (typeof data !== 'object' || data === null || !('success' in data)) {
                    throw new TypeError('Invalid JSON response format from server.');
                }

                if (data.success) {
                    window.showCustomMessage(data.message || 'Item added to cart!', data.type || 'success');
                    
                    if (cartCountSpan && data.cart_item_count !== undefined) {
                        cartCountSpan.textContent = data.cart_item_count;
                    }
                    
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }

                } else {
                    window.showCustomMessage(data.message || 'Failed to add item to cart.', data.type || 'error');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                }
            })
            .catch(error => {
                console.error('AJAX Add to Cart Request Error:', error);
                let errorMessage = 'An unexpected error occurred. Please try again.';
                if (error instanceof TypeError && error.message.includes('Invalid JSON response')) {
                    errorMessage = 'Server response error: Could not process data.';
                } else if (error.message.includes('HTTP error!')) {
                    errorMessage = `Server error: ${error.message.split(',')[0]}`;
                }
                window.showCustomMessage(errorMessage, 'error');
            })
            .finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            });
        });
    });

}); // End of DOMContentLoaded