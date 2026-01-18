// js/auth.js

const loginModal = document.getElementById('login-modal');
const loginTriggerButton = document.getElementById('login-trigger-button');
const closeButton = loginModal ? loginModal.querySelector('.close-button') : null;
const googleLoginButton = document.getElementById('google-login-button');

export function showLoginModal() {
    if (loginModal) {
        loginModal.style.display = 'flex';
    }
}

export function hideLoginModal() {
    if (loginModal) {
        loginModal.style.display = 'none';
    }
}

export function initializeAuth() {
    if (loginTriggerButton) {
        loginTriggerButton.addEventListener('click', function(event) {
            event.preventDefault();
            showLoginModal();
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', hideLoginModal);
    }

    if (loginModal) {
        loginModal.addEventListener('click', function(event) {
            if (event.target === loginModal) {
                hideLoginModal();
            }
        });
    }

    if (googleLoginButton) {
        googleLoginButton.addEventListener('click', () => {
            window.location.href = 'google_auth_redirect.php';
        });
    }

     // Check for login errors passed via URL on initial load
     const urlParams = new URLSearchParams(window.location.search);
     if (urlParams.has('login_error')) {
         showLoginModal();
         // Optionally clear the error from the URL to prevent reshowing on refresh
         // window.history.replaceState({}, document.title, window.location.pathname);
     }
}