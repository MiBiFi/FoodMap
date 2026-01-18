// js/contentLoader.js
import { fetchContent } from './apiClient.js';
import { updateSidebarActiveState } from './sidebar.js';
import { initializeProfilePage } from './profile.js';
import { initializeRecommendationHandlers } from './recommendation_handler.js';
import { initializeFoodDiaryHandlers } from './foodDiary.js';
// 從 main.js 導入新的函數
import { refreshCalendarAndRoutePreview } from './main.js';


const mainContentArea = document.getElementById('main-content-area');
const loadingHTML = `<div class="loading-placeholder"><p>正在載入內容...</p><i class="fas fa-spinner fa-spin"></i></div>`;

export async function loadDynamicContent(contentId, fetchOptions = {}) {
    let effectiveContentId = contentId;
    let activeStateContentId = contentId;

    if (!effectiveContentId) {
        effectiveContentId = typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN ? 'recommendations' : 'food-diary';
        activeStateContentId = effectiveContentId;
    }

    if (effectiveContentId === 'recommendations' && typeof IS_LOGGED_IN !== 'undefined' && !IS_LOGGED_IN) {
         effectiveContentId = 'food-diary';
         activeStateContentId = 'food-diary';
    }

    if (!mainContentArea) {
         console.error("主內容區域 #main-content-area 未找到!");
         return;
    }

    mainContentArea.innerHTML = loadingHTML;

    try {
        const html = await fetchContent(effectiveContentId, fetchOptions);

        mainContentArea.innerHTML = html;

        if (activeStateContentId === 'recommendations' || activeStateContentId === 'food-diary') {
            updateSidebarActiveState(activeStateContentId);
        } else {
            updateSidebarActiveState(activeStateContentId);
        }

        const baseContentIdForInit = effectiveContentId.split('/')[0];

        if (baseContentIdForInit === 'user-profile') {
            try { initializeProfilePage(); } catch (err) { console.error("初始化使用者設定檔模組時發生錯誤:", err); }
        } else if (baseContentIdForInit === 'recommendations') {
             try { initializeRecommendationHandlers(); } catch (err) { console.error("初始化推薦模組時發生錯誤:", err); }
        } else if (baseContentIdForInit === 'food-diary') {
            try { initializeFoodDiaryHandlers(); } catch (err) { console.error("初始化美食日誌模組時發生錯誤:", err); }
        }

        if ((baseContentIdForInit === 'recommendations' || baseContentIdForInit === 'food-diary') &&
            (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN)) {
            await refreshCalendarAndRoutePreview();
        }


    } catch (error) {
         console.error('載入內容錯誤:', error);
         mainContentArea.innerHTML = `<p style="color: red; text-align: center;">無法載入內容: ${error.message}</p>`;
    }
}