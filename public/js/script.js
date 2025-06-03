/**
 * BaladyMall - Main JavaScript File
 * public/js/script.js
 */

document.addEventListener('DOMContentLoaded', function() {
    // This function runs once the entire HTML document is fully loaded and parsed.

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
            // Ensure it's not just a lone '#' or a link to something not on the page
            if (hrefAttribute.length > 1 && document.querySelector(hrefAttribute)) {
                e.preventDefault(); 
                document.querySelector(hrefAttribute).scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Back to Top Button (Reverted to First Design) ---
    let backToTopButton = document.getElementById('backToTopBtn');
    if (!backToTopButton) {
        backToTopButton = document.createElement('button');
        backToTopButton.id = 'backToTopBtn';
        backToTopButton.innerHTML = '&uarr; <span class="sr-only">Back to Top</span>'; // Up arrow, sr-only for accessibility
        backToTopButton.title = 'Go to top';
        // Basic styling - you should move these to your style.css for better management
        backToTopButton.style.position = 'fixed';
        backToTopButton.style.bottom = '20px';
        backToTopButton.style.right = '20px';
        backToTopButton.style.display = 'none'; // Hidden by default
        backToTopButton.style.padding = '10px 15px';
        backToTopButton.style.fontSize = '18px';
        backToTopButton.style.backgroundColor = '#007bff';
        backToTopButton.style.color = 'white';
        backToTopButton.style.border = 'none';
        backToTopButton.style.borderRadius = '5px'; // Rectangular with rounded corners
        backToTopButton.style.cursor = 'pointer';
        backToTopButton.style.zIndex = '999';
        // backToTopButton.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)'; // Removed for "first design"
        // backToTopButton.style.transition = 'opacity 0.3s ease, visibility 0.3s ease'; // Removed for "first design"
        document.body.appendChild(backToTopButton);
    }
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 200) { // Show button after scrolling 200px (as per first design)
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
    // CSS for .animate-on-scroll and .is-visible needs to be in your style.css:
    // .animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease-out, transform 0.6s ease-out; }
    // .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }

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
    window.showCustomMessage = showCustomMessage;

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

    // --- Mobile Menu Toggle (Example - requires HTML and CSS setup) ---
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

}); // End DOMContentLoaded
        