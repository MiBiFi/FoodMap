// js/recommendation_handler.js
import { displayGeneratedPrompt, displayRecommendations, displaySidebarDetailedRoute, displayRoutePreviewError, resetSidebarRoutePreview } from './uiUpdater.js';
import { loadDynamicContent } from './contentLoader.js';
import { getCurrentLocation } from './geolocation.js';
import { debounce } from './utils.js';

function gatherFrontendDataForPrompt(userInput, userSpecifiedLocation, currentGeoPosition = null) {
     const promptData = {
        userInput: userInput,
        userSpecifiedLocation: userSpecifiedLocation,
        currentGeoPosition: currentGeoPosition,
        initialGeoPosition: (typeof currentUserPosition !== 'undefined' && currentUserPosition !== null) ? currentUserPosition : null,
        calendarEvents: [],
        weather: { description: '未知', temperature: null },
        preferences: { likes: [], dislikes: [] },
        nextEventTime: null,
        travelModePreference: (typeof window.gUserTravelPreference !== 'undefined' && window.gUserTravelPreference) ? window.gUserTravelPreference : 'driving'
    };

    if (typeof gFetchedCalendarEvents !== 'undefined' && gFetchedCalendarEvents.length > 0) {
        const firstEvent = gFetchedCalendarEvents[0];
        if (firstEvent && firstEvent.summary) {
            promptData.calendarEvents.push({
                summary: firstEvent.summary,
                startTime: firstEvent.startTime,
                endTime: (firstEvent.endTime && !firstEvent.isAllDay) ? firstEvent.endTime : '',
                location: (firstEvent.location && firstEvent.location.trim() !== '') ? firstEvent.location.trim() : null,
                isAllDay: firstEvent.isAllDay || false
            });
            const timeMatch = firstEvent.startTime.match(/(\d{2}:\d{2})/);
            if (timeMatch) promptData.nextEventTime = timeMatch[1];
        }
    }
    const weatherTempElement = document.getElementById('weather-temp-display');
    const weatherIconElement = document.querySelector('.weather-info .icon i.weather-status-icon');
    const weatherTemp = weatherTempElement ? weatherTempElement.textContent.trim() : null;
    const weatherIconClass = weatherIconElement ? weatherIconElement.className : "";
    if (weatherIconClass.includes('cloud')) promptData.weather.description = '多雲';
    else if (weatherIconClass.includes('sun')) promptData.weather.description = '晴朗';
    else if (weatherIconClass.includes('rain') || weatherIconClass.includes('showers')) promptData.weather.description = '有雨';
    if (weatherTemp && weatherTemp !== '--°C') promptData.weather.temperature = weatherTemp;

    const prefList = document.getElementById('sidebar-preference-list');
    if (prefList) {
        prefList.querySelectorAll('li').forEach(li => {
            const textEl = li.querySelector('.pref-text');
            const text = textEl ? textEl.textContent.trim() : null;
            if (text) {
                if (li.classList.contains('like')) promptData.preferences.likes.push(text);
                else if (li.classList.contains('dislike')) promptData.preferences.dislikes.push(text);
            }
        });
    }
     return {
        frontendContext: promptData,
        nextEventTime: promptData.nextEventTime
    };
}

async function handleFoodSearch() {
    const searchInput = document.getElementById('food-search-input');
    const locationInput = document.getElementById('location-search-input');
    const searchButton = document.getElementById('food-search-button');
    const recListTitle = document.getElementById('recommendation-list-title');
    const recGridResults = document.getElementById('recommendation-grid-results');
    const initialMessage = document.getElementById('initial-recommendation-message');

    if (searchButton) searchButton.disabled = true;
    if (searchInput) searchInput.disabled = true;
    if (locationInput) locationInput.disabled = true;

    try {
        if (!searchInput) {
            console.warn("Search input not found.");
            return;
        }
        const userQuery = searchInput.value.trim();
        const userSpecifiedLocation = locationInput ? locationInput.value.trim() : "";

        if (userQuery === "") {
            alert("請輸入您的餐飲需求！");
            if (searchInput) searchInput.focus();
            return;
        }

        if (typeof IS_LOGGED_IN === 'undefined' || !IS_LOGGED_IN) {
             try {
                 const { showLoginModal } = await import('./auth.js');
                 showLoginModal();
             } catch(err) {
                 console.error("Failed to load auth module for login modal:", err);
                 alert("請先登入以使用推薦功能。");
             }
             return;
        }

        if (recListTitle) recListTitle.style.display = 'none';
        if (initialMessage) initialMessage.style.display = 'none';
        if (recGridResults) recGridResults.innerHTML = '<div class="loading-placeholder"><p>正在為您搜尋絕佳美食...</p><i class="fas fa-spinner fa-spin"></i></div>';
        resetSidebarRoutePreview();

        let fetchedPositionForPrompt = null;
        if (!userSpecifiedLocation) {
            try {
                fetchedPositionForPrompt = await getCurrentLocation({
                    enableHighAccuracy: true, timeout: 7000, maximumAge: 0
                });
            } catch (locationError) {
                console.warn("Could not get current location for context:", locationError.message);
            }
        }

        const { frontendContext, nextEventTime } = gatherFrontendDataForPrompt(userQuery, userSpecifiedLocation, fetchedPositionForPrompt);

        const response = await fetch('call_AI.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ context: frontendContext })
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({ message: `請求 AI 服務失敗 (HTTP ${response.status})。伺服器回應非JSON或解析失敗。` }));
            throw new Error(errData.message || `請求 AI 服務失敗 (HTTP ${response.status})`);
        }

        const data = await response.json();

        if (data.success) {
             if (data.recommendations && Array.isArray(data.recommendations)) {
                if (data.recommendations.length > 0) {
                    if (data.recommendations[0] && data.recommendations[0].status === 'NO_RESULTS') {
                        const noResultsMessage = data.recommendations[0].message || 'AI 助手表示：目前沒有推薦的餐廳。';
                        if (recGridResults) recGridResults.innerHTML = `<p class="info-message">${noResultsMessage}</p>`;
                         if (typeof displayGeneratedPrompt === 'function') {
                            displayGeneratedPrompt(data, true);
                        }
                    } else {
                        displayRecommendations(data.recommendations, nextEventTime);
                         if (typeof displayGeneratedPrompt === 'function') {
                            displayGeneratedPrompt(data, true);
                        }
                    }
                } else {
                     if (recGridResults) recGridResults.innerHTML = '<p class="info-message">AI 助手表示：在目前條件下沒有找到可推薦的餐廳。</p>';
                     if (typeof displayGeneratedPrompt === 'function') {
                       displayGeneratedPrompt(data, true);
                    }
                }
            } else if (data.openai_response_text && !data.recommendations) {
                 console.warn("Received openai_response_text without structured recommendations. Attempting to parse.");
                try {
                    const recommendationsFromText = JSON.parse(data.openai_response_text);
                     if (Array.isArray(recommendationsFromText)) {
                        if (recommendationsFromText.length > 0) {
                             if (recommendationsFromText[0] && recommendationsFromText[0].status === 'NO_RESULTS') {
                                const noResultsMessage = recommendationsFromText[0].message || 'AI 助手表示：目前沒有推薦的餐廳。';
                                if (recGridResults) recGridResults.innerHTML = `<p class="info-message">${noResultsMessage}</p>`;
                             } else {
                                displayRecommendations(recommendationsFromText, nextEventTime);
                             }
                        } else {
                            if (recGridResults) recGridResults.innerHTML = '<p class="info-message">AI 助手表示：在目前條件下沒有找到可推薦的餐廳 (from text)。</p>';
                        }
                         if (typeof displayGeneratedPrompt === 'function') displayGeneratedPrompt(data, true);
                    } else if (typeof recommendationsFromText === 'object' && recommendationsFromText !== null && recommendationsFromText.status === 'NO_RESULTS') {
                         if (recGridResults) recGridResults.innerHTML = `<p class="info-message">${recommendationsFromText.message || 'AI 助手表示：目前沒有推薦的餐廳 (from text NO_RESULTS object)。'}</p>`;
                         if (typeof displayGeneratedPrompt === 'function') displayGeneratedPrompt(data, true);
                    } else {
                        throw new Error("AI的回應文本內容不是預期的格式。");
                    }
                } catch (e) {
                    console.error("解析 AI 推薦 JSON 時發生錯誤 (from openai_response_text):", e, "\n原始 AI 回應文本:", data.openai_response_text);
                    if(recGridResults) recGridResults.innerHTML = '<p class="error-message">抱歉，解析 AI 推薦結果時發生錯誤。</p>';
                    if (typeof displayGeneratedPrompt === 'function') displayGeneratedPrompt(data, true);
                }
            }
             else {
                console.error("AI 服務成功，但缺少有效的推薦內容 (data.recommendations is missing or not an array).", data);
                 if (recGridResults) recGridResults.innerHTML = '<p class="error-message">AI 服務回應成功，但未提供有效的推薦資料。</p>';
                 if (typeof displayGeneratedPrompt === 'function') {
                    displayGeneratedPrompt(data, true);
                }
            }
        } else {
            let errorMessage = data.message || '從 AI 服務獲取推薦失敗。';
             if (typeof displayGeneratedPrompt === 'function') {
                 displayGeneratedPrompt(data, true);
            }
            throw new Error(errorMessage);
        }
    } catch (error) {
        console.error("處理美食搜尋時發生錯誤:", error);
        if (recGridResults) recGridResults.innerHTML = `<p class="error-message">抱歉，搜尋過程中發生錯誤：${error.message}</p>`;
        if (initialMessage && initialMessage.style.display === 'none') initialMessage.style.display = 'block';
    } finally {
        if (searchButton) searchButton.disabled = false;
        if (searchInput) searchInput.disabled = false;
        if (locationInput) locationInput.disabled = false;
    }
}

async function handleRecommendationClick(event) {
    const clickedCard = event.target.closest('.food-card-list-item');
    if (!clickedCard) return;
    const restaurantName = clickedCard.dataset.name || "選定的餐廳";
    const restaurantAddress = clickedCard.dataset.address || "";
    const defaultEventTimeFromCard = clickedCard.dataset.defaultEventTime || "";

    if (!confirm(`您選擇了「${restaurantName}」。\n是否要為此安排規劃路線並加入行事曆？`)) {
        resetSidebarRoutePreview();
        return;
    }

    const recGridResults = document.getElementById('recommendation-grid-results');
    if (recGridResults) {
        recGridResults.innerHTML = '<div class="loading-placeholder"><p>正在規劃路線與準備行事曆...</p><i class="fas fa-spinner fa-spin"></i></div>';
    }
     resetSidebarRoutePreview();

    let origin = null;
     await (async () => {
        try {
            if ('geolocation' in navigator) {
                const position = await getCurrentLocation({ enableHighAccuracy: true, timeout: 7000, maximumAge: 0 });
                origin = `${position.latitude},${position.longitude}`;
            }
        } catch (geoError) { console.warn("Geolocation failed for directions:", geoError.message); }
        if (!origin && typeof gFetchedCalendarEvents !== 'undefined' && gFetchedCalendarEvents.length > 0) {
            const firstEvent = gFetchedCalendarEvents[0];
            if (firstEvent && firstEvent.location && firstEvent.location.trim() !== '') origin = firstEvent.location;
        }
        if (!origin) {
            origin = prompt("無法自動獲取您的位置，請輸入出發點地址：", "台北車站");
            if (origin === null) { if (recGridResults) recGridResults.innerHTML = '<p>已取消行程規劃。</p>'; resetSidebarRoutePreview(); return; }
            origin = origin.trim() || "台北車站";
        }

        let routeInfo = {};

        try {
            console.log("RECOMMENDATION_HANDLER.JS: Requesting route from backend (backend will read DB for mode).");
            const routeResponse = await fetch('get_route_directions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    origin,
                    destination: restaurantAddress
                })
            });
             if (!routeResponse.ok) {
                const errData = await routeResponse.json().catch(() => ({message: `路線規劃請求失敗 (HTTP ${routeResponse.status})`}));
                throw new Error(errData.message);
            }
            const routeResult = await routeResponse.json();
            console.log("RECOMMENDATION_HANDLER.JS: Route result from backend:", routeResult);

            if (routeResult.success) {
                routeInfo = routeResult; // 使用後端返回的完整資訊
                displaySidebarDetailedRoute({
                    totalTime: routeInfo.duration_text,
                    totalDistance: routeInfo.distance_text,
                    mapsLink: routeInfo.google_maps_url,
                    steps: routeInfo.steps,
                    mode: routeInfo.mode
                });
            } else {
                 displayRoutePreviewError(`路線規劃失敗：${routeResult.message}`);
                 // 即使規劃失敗，也設定一個預設的 routeInfo 供後續使用
                 routeInfo = {
                    duration_text: "未知", distance_text: "未知",
                    google_maps_url: `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(restaurantAddress)}`,
                    summary: "無法自動規劃詳細路線", steps: [],
                    mode: 'driving' // 失敗時回退
                 };
            }
        } catch (error) {
            console.error("Error fetching route directions:", error);
            displayRoutePreviewError(`路線規劃時發生錯誤：${error.message}`);
             routeInfo = {
                    duration_text: "未知", distance_text: "未知",
                    google_maps_url: `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(restaurantAddress)}`,
                    summary: "無法自動規劃詳細路線", steps: [],
                    mode: 'driving'
                 };
        }
        
        window.pendingRouteInfoForCalendar = routeInfo;

        const today = new Date(); let eventDateObj = new Date(today); let eventTime = "";
        if (defaultEventTimeFromCard) {
             if (/^\d{2}:\d{2}$/.test(defaultEventTimeFromCard)) {
                eventTime = defaultEventTimeFromCard;
            }
        }

        if (!eventTime && routeInfo.duration_value) {
            let arrivalTime = new Date(); arrivalTime.setSeconds(arrivalTime.getSeconds() + parseInt(routeInfo.duration_value, 10)); arrivalTime.setMinutes(arrivalTime.getMinutes() + 15); eventDateObj = arrivalTime;
            let hours = arrivalTime.getHours(); let minutes = arrivalTime.getMinutes(); const remainder = minutes % 15;
            if (remainder !== 0) minutes += (15 - remainder); if (minutes >= 60) { hours += 1; minutes = 0; } hours %= 24;
            eventTime = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        } else if (!eventTime) {
             let hours = today.getHours() < 17 ? 19 : (today.getHours() + 2) % 24; let minutes = 0;
             eventTime = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }
        const eventDate = `${eventDateObj.getFullYear()}-${String(eventDateObj.getMonth() + 1).padStart(2, '0')}-${String(eventDateObj.getDate()).padStart(2, '0')}`;
        
        let travelModeText = '開車';
        switch(routeInfo.mode) {
            case 'walking': travelModeText = '步行'; break;
            case 'bicycling': travelModeText = '自行車'; break;
            case 'transit': travelModeText = '大眾運輸'; break;
            case 'motorcycle': travelModeText = '機車'; break;
            case 'driving':
            default:
                 travelModeText = '開車';
        }

        const eventDescription = `餐廳：${restaurantName}\n地址：${restaurantAddress}\n\n路線資訊：\n從：${origin}\n交通方式：${travelModeText}\n預計路程：${routeInfo.duration_text} (${routeInfo.distance_text})\n路線詳情：${routeInfo.google_maps_url}\n${routeInfo.summary ? '摘要：' + routeInfo.summary : ''}`;
        const eventDataForPost = { event_name: restaurantName, event_location: restaurantAddress, event_date: eventDate, event_time: eventTime, event_description: eventDescription };
        
        try {
            await loadDynamicContent('user-profile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(eventDataForPost) });
             setTimeout(() => {
                const scheduleSection = document.getElementById('schedule-section');
                if (scheduleSection) {
                    scheduleSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    scheduleSection.classList.add('highlight-focus');
                    setTimeout(() => scheduleSection.classList.remove('highlight-focus'), 1500);
                } else { console.warn("#schedule-section not found after loading user-profile."); }
            }, 100);
        } catch (error) {
            console.error("Error loading user-profile for calendar prefill:", error);
             if (recGridResults) recGridResults.innerHTML = '<p class="error-message">載入使用者資料頁面失敗。</p>';
        }
    })();
}

export function initializeRecommendationHandlers() {
    const searchInput = document.getElementById('food-search-input');
    const locationInput = document.getElementById('location-search-input');
    const searchButton = document.getElementById('food-search-button');
    const resultsGrid = document.getElementById('recommendation-grid-results');
    if (searchInput && searchButton) {
        const debouncedActualSearch = debounce(() => { handleFoodSearch(); }, 700);
        const triggerSearchHandler = (event) => { event.preventDefault(); debouncedActualSearch(); };
        searchButton.addEventListener('click', triggerSearchHandler);
        if (searchInput) { searchInput.addEventListener('keypress', (event) => { if (event.key === 'Enter') triggerSearchHandler(event); }); }
        if (locationInput) { locationInput.addEventListener('keypress', (event) => { if (event.key === 'Enter') triggerSearchHandler(event); }); }
    } else { console.warn("Search input or button not found."); }
    if (resultsGrid) { resultsGrid.addEventListener('click', handleRecommendationClick); }
    else { console.warn("Recommendation results grid not found."); }
}