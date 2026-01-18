// js/sidebar.js
import { loadDynamicContent } from './contentLoader.js';
import { showLoginModal } from './auth.js';
import { resetSidebarRoutePreview } from './uiUpdater.js';

let sidebarInitialized = false;

export function updateSidebarActiveState(contentId) {

    const sidebarLinks = document.querySelectorAll('.sidebar-nav .widget-header.sidebar-link');
    const headerLinks = document.querySelectorAll('.main-nav a');

    let effectiveContentId = contentId;
    if (contentId === 'recommendations' || contentId === 'food-diary') {
        effectiveContentId = 'recommendations';
    }

    sidebarLinks.forEach(link => {
        if (link.getAttribute('data-content') === effectiveContentId) {
            link.classList.add('active-link');
        } else {
            link.classList.remove('active-link');
        }
    });

    headerLinks.forEach(link => {
        const linkContent = link.getAttribute('data-content');
        const linkId = link.id;
        if (linkContent === contentId ||
            (linkId === 'home-link' && (contentId === 'recommendations' || contentId === 'food-diary')) ||
            (linkId === 'profile-link' && contentId === 'user-profile')
           ) {
            link.classList.add('active-link');
        } else {
            link.classList.remove('active-link');
        }
    });
}


export function initializeSidebar() {
    if (sidebarInitialized) {
        return;
    }

    const sidebarNavLinks = document.querySelectorAll('.sidebar-nav .widget-header.sidebar-link');
    const collapsibleHeaders = document.querySelectorAll('.sidebar-nav .widget-header.collapsible-header');

    const headerHomeLink = document.getElementById('home-link');
    const headerProfileLink = document.getElementById('profile-link');

    sidebarNavLinks.forEach(link => {
         link.addEventListener('click', function(event) {
            event.preventDefault();
            const contentId = this.getAttribute('data-content');
            const requiresLogin = this.classList.contains('requires-login');

            if (typeof IS_LOGGED_IN === 'undefined') {
                 console.error("[Sidebar] IS_LOGGED_IN is not defined globally!");
                 return;
            }
            if (requiresLogin && !IS_LOGGED_IN) {
                showLoginModal();
            } else if (contentId) {
                loadDynamicContent(contentId);
            }
        });
    });

    if(headerHomeLink) {
        headerHomeLink.addEventListener('click', function(event){
            event.preventDefault();
            const defaultContentId = (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN)
                                      ? 'recommendations'
                                      : 'food-diary';
            loadDynamicContent(defaultContentId);

            const foodSearchInput = document.getElementById('food-search-input');
            const locationSearchInput = document.getElementById('location-search-input');
            if (foodSearchInput) foodSearchInput.value = '';
            if (locationSearchInput) locationSearchInput.value = '';

            if (typeof resetSidebarRoutePreview === 'function') {
                resetSidebarRoutePreview();
            }
            const contentArea = document.getElementById('main-content-area');
            if (contentArea) contentArea.scrollTop = 0;
        });
    }

     if(headerProfileLink) {
        headerProfileLink.addEventListener('click', function(event){
            event.preventDefault();
            const contentId = this.getAttribute('data-content');
            const requiresLogin = this.classList.contains('requires-login');
            if (typeof IS_LOGGED_IN === 'undefined') {
                 console.error("[Sidebar] IS_LOGGED_IN is not defined globally!");
                 return;
            }
            if (requiresLogin && !IS_LOGGED_IN) {
                showLoginModal();
            } else if (contentId) {
                loadDynamicContent(contentId);
            }
        });
    }

    if (collapsibleHeaders.length === 0) {
        console.warn("[Sidebar] No collapsible headers found. Check selector '.sidebar-nav .widget-header.collapsible-header'.");
    }

    collapsibleHeaders.forEach(header => {
        if (!header || typeof header.getAttribute !== 'function') {
            console.error("[Sidebar] Invalid header element in collapsibleHeaders:", header);
            return;
        }

        const targetId = header.getAttribute('data-target');
        if (!targetId) {
            console.warn("[Sidebar] Collapsible header is missing data-target attribute:", header);
            return;
        }

        const contentElement = document.querySelector(targetId);
        const arrowElement = header.querySelector('.arrow');
        const requiresLogin = header.classList.contains('requires-login');

        if (!contentElement) {
            console.warn(`[Sidebar] Collapsible content element with selector "${targetId}" not found for header:`, header.textContent.trim());
        }
        if (!arrowElement) {
            console.warn("[Sidebar] Arrow element not found in header:", header.textContent.trim());
        }

        header.addEventListener('click', function(event) {

            if (typeof IS_LOGGED_IN === 'undefined') {
                 console.error("[Sidebar] IS_LOGGED_IN is not defined globally!");
                 return;
            }

            if (requiresLogin && !IS_LOGGED_IN) {
                showLoginModal();
            } else {
                if (contentElement) {
                    const isCurrentlyVisible = window.getComputedStyle(contentElement).display !== 'none';
                    contentElement.style.display = isCurrentlyVisible ? 'none' : 'block';

                    if (this.style.display === 'block' && contentElement.style.display === 'block') {
                        setTimeout(() => {
                            const currentDisplay = window.getComputedStyle(contentElement).display;
                            if (contentElement.style.display === 'block' && currentDisplay === 'none') {
                                console.error(`[Sidebar] Display was set to block, but became none shortly after for ${targetId}! Something else is hiding it.`);
                            }
                        }, 100);
                    }


                    if (arrowElement) {
                        arrowElement.innerHTML = isCurrentlyVisible ? '>' : 'V';
                    }
                } else {
                    console.log(`[Sidebar] Clicked collapsible header for ${targetId}, but content element was not found. No display change.`);
                }
            }
        });

        if (contentElement && arrowElement) {
            const isInitiallyVisible = window.getComputedStyle(contentElement).display !== 'none';
            arrowElement.innerHTML = isInitiallyVisible ? 'V' : '>';
            if (contentElement.style.display === 'block' && !isInitiallyVisible) {
                 arrowElement.innerHTML = 'V';
            } else if (contentElement.style.display === 'none' && isInitiallyVisible) {
                 arrowElement.innerHTML = '>';
            }
        }
    });

    sidebarInitialized = true; // 所有初始化完成後，才設定標誌位
}