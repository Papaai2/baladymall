/**
 * BaladyMall - Main JavaScript File
 * public/js/script.js
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
        backToTopButton.style.backgroundColor = '#007bff';
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

    // --- Hero Section Text Typing Animation ---
    const heroTitle = document.querySelector('.hero-content h1');
    if (heroTitle && heroTitle.dataset.typingText) {
        const originalText = heroTitle.dataset.typingText;
        let i = 0;
        heroTitle.textContent = '';
        function typeWriter() {
            if (i < originalText.length) {
                heroTitle.textContent += originalText.charAt(i);
                i++;
                setTimeout(typeWriter, 100);
            }
        }
        setTimeout(typeWriter, 500);
    } else if (heroTitle) {
        // console.log("Hero title found, but no 'data-typing-text' attribute for animation.");
    }

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

    // --- Custom Alert/Message Box Handling ---
    function showCustomMessage(message, type = 'info', duration = 4000) {
        const messageContainerId = 'custom-message-box-container';
        let messageContainer = document.getElementById(messageContainerId);

        if (!messageContainer) {
            messageContainer = document.createElement('div');
            messageContainer.id = messageContainerId;
            document.body.appendChild(messageContainer);
        }

        messageContainer.className = 'custom-message-box';
        messageContainer.classList.add(`custom-message-${type}`);

        messageContainer.innerHTML = `<span>${message}</span><button class="close-custom-message" aria-label="Close message">&times;</button>`;
        messageContainer.style.display = 'block';

        setTimeout(() => {
            messageContainer.classList.add('visible');
        }, 10);

        const closeButton = messageContainer.querySelector('.close-custom-message');
        const hideMessage = () => {
            messageContainer.classList.remove('visible');
            setTimeout(() => {
                if (messageContainer) messageContainer.style.display = 'none';
            }, 300);
        };

        if(closeButton) {
            closeButton.onclick = hideMessage;
        }

        if (duration > 0) {
            setTimeout(hideMessage, duration);
        }
    }
    window.showCustomMessage = showCustomMessage; // Make it globally accessible

    const phpSuccessMsg = document.getElementById('phpGlobalSuccessMessage');
    if (phpSuccessMsg && phpSuccessMsg.textContent.trim() !== '') {
        showCustomMessage(phpSuccessMsg.textContent.trim(), 'success');
        phpSuccessMsg.style.display = 'none';
    }
    const phpErrorMsg = document.getElementById('phpGlobalErrorMessage');
    if (phpErrorMsg && phpErrorMsg.textContent.trim() !== '') {
        showCustomMessage(phpErrorMsg.textContent.trim(), 'error');
        phpErrorMsg.style.display = 'none';
    }

    // --- Mobile Menu Toggle ---
    const mobileMenuButton = document.getElementById('mobileMenuToggle');
    const mainNavUl = document.querySelector('.main-navigation ul');

    if (mobileMenuButton && mainNavUl) {
        mobileMenuButton.addEventListener('click', function() {
            mainNavUl.classList.toggle('active');
            mobileMenuButton.classList.toggle('is-active');
            const isExpanded = mainNavUl.classList.contains('active');
            mobileMenuButton.setAttribute('aria-expanded', isExpanded.toString());
        });
    }

    // --- AJAX Add to Cart Functionality (REVISED FIX) ---
    const ajaxAddToCartForms = document.querySelectorAll('.ajax-add-to-cart-form');
    const cartCountSpan = document.querySelector('.header-actions .cart-count');

    ajaxAddToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission (redirection)

            const formData = new FormData(this); // Get form data
            // *** CRUCIAL CHANGE HERE: Use getAttribute('action') for absolute certainty ***
            const formAction = this.getAttribute('action'); // Correctly get the string value of the action attribute

            // Disable button to prevent multiple clicks
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton ? submitButton.textContent : 'Add to Cart'; // Store original text
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Adding...'; // Optional: change button text
            }

            fetch(formAction, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
                }
            })
            .then(response => {
                if (!response.ok) { // Check if HTTP status is 2xx
                    // If response is not OK, try to read text for more info before throwing
                    return response.text().then(text => {
                        throw new Error(`HTTP error! status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json(); // Parse JSON response
            })
            .then(data => {
                // Re-enable button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText; // Revert to original text
                }

                if (data.success) {
                    window.showCustomMessage(data.message, data.type || 'success'); // Use data.type if provided
                    if (cartCountSpan && data.cart_item_count !== undefined) {
                        cartCountSpan.textContent = data.cart_item_count; // Update cart count in header
                    }
                } else {
                    window.showCustomMessage(data.message || 'Failed to add to cart. No specific error provided.', data.type || 'error');
                }
            })
            .catch(error => {
                console.error('AJAX add to cart error:', error);
                // The error message now already contains the raw response text if not 2xx
                window.showCustomMessage('An unexpected network or server error occurred. Please try again. Check console for details.', 'error');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            });
        });
    });

}); // End DOMContentLoaded