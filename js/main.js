// js/main.js
import { initializeAuth, showLoginModal } from './auth.js';
import { initializeSidebar } from './sidebar.js';
import { loadDynamicContent } from './contentLoader.js';
import {
    fetchCwaWeatherData,
    fetchCalendarEvents,
    fetchSavedRouteForEvent
} from './apiClient.js';
import {
    displayWeatherError,
    displayScheduleError,
    updateCwaWeatherUI,
    updateSidebarSchedule,
    processCwaWeatherData,
    resetSidebarRoutePreview,
    displaySidebarDetailedRoute,
    displayRoutePreviewError
} from './uiUpdater.js';
import {
    initializeMainUserPosition,
    initialCurrentUserPosition as importedUserPosition,
    initialPositionError as importedPositionError
} from './geolocation.js';

window.gFetchedCalendarEvents = [];
window.currentUserPosition = null;
let weatherIntervalId = null;

function fetchWeather() {
    if (window.currentUserPosition &&
        typeof window.currentUserPosition.latitude === 'number' &&
        typeof window.currentUserPosition.longitude === 'number') {

        fetchCwaWeatherData()
            .then(data => {
                if (data.success && data.records) {
                    processCwaWeatherData(
                        window.currentUserPosition.latitude,
                        window.currentUserPosition.longitude,
                        data
                    );
                } else {
                    displayWeatherError(data.message || '無法取得天氣列表');
                }
            })
            .catch(error => {
                console.error('取得 CWA 天氣資料錯誤:', error);
                displayWeatherError('無法連接天氣服務');
            });

    } else {
        displayWeatherError('無法獲取您的位置以顯示當地天氣。');
    }
}

function startWeatherInterval() {
    if (weatherIntervalId) clearInterval(weatherIntervalId);
    weatherIntervalId = setInterval(() => {
        if (window.currentUserPosition) {
            fetchWeather();
        }
    }, 600000);
}

export async function updateSidebarRouteFromCalendar() {
    if (!window.gFetchedCalendarEvents || window.gFetchedCalendarEvents.length === 0) {
        resetSidebarRoutePreview();
        return;
    }

    const now = new Date();
    let relevantEvent = null;

    const todayEvents = window.gFetchedCalendarEvents
        .filter(ev => !ev.isAllDay && ev.startTime && ev.id)
        .map(ev => {
            const [hours, minutes] = ev.startTime.split(':').map(n => parseInt(n, 10));
            if (!isNaN(hours) && !isNaN(minutes)) {
                ev.eventDateTime = new Date(
                    now.getFullYear(),
                    now.getMonth(),
                    now.getDate(),
                    hours,
                    minutes
                );
                return ev;
            }
            return null;
        })
        .filter(ev => ev !== null)
        .sort((a, b) => a.eventDateTime - b.eventDateTime);

    if (todayEvents.length) {
        if (todayEvents.length === 1) {
            relevantEvent = todayEvents[0];
        } else {
            relevantEvent = todayEvents.find(ev => ev.eventDateTime > now) || todayEvents[0];
        }
    }

    if (relevantEvent && relevantEvent.id) {
        try {
            const savedRouteData = await fetchSavedRouteForEvent(relevantEvent.id);
            if (savedRouteData.success && savedRouteData.route) {
                displaySidebarDetailedRoute({
                    totalTime: savedRouteData.route.route_duration_text,
                    totalDistance: savedRouteData.route.route_distance_text,
                    mapsLink: savedRouteData.route.route_google_maps_url,
                    steps: JSON.parse(savedRouteData.route.route_steps_json || '[]'),
                    mode: savedRouteData.route.route_mode || 'driving'
                });
                return;
            }
        } catch (error) {
            console.error("[main.js] 取得或顯示已儲存路線時發生錯誤:", error);
        }
    }

    resetSidebarRoutePreview();
}

export async function refreshCalendarAndRoutePreview() {
    const scheduleEventsList      = document.getElementById('schedule-events-list');
    const scheduleLoadingMessage  = document.getElementById('schedule-loading-message');
    const scheduleConnectPrompt   = document.getElementById('schedule-connect-prompt');
    const scheduleErrorElement    = document.getElementById('schedule-error');

    // Not logged in → clear preview
    if (typeof IS_LOGGED_IN === 'undefined' || !IS_LOGGED_IN) {
        resetSidebarRoutePreview();
        return;
    }

    // Not connected to Google → show connect prompt
    if (typeof IS_GOOGLE_CONNECTED === 'undefined' || !IS_GOOGLE_CONNECTED) {
        if (scheduleLoadingMessage)  scheduleLoadingMessage.style.display = 'none';
        if (scheduleEventsList)      scheduleEventsList.style.display = 'none';
        if (scheduleErrorElement)    scheduleErrorElement.style.display = 'none';
        if (scheduleConnectPrompt)   scheduleConnectPrompt.style.display = 'block';
        resetSidebarRoutePreview();
        return;
    }

    // Connected → hide prompt and load events
    if (scheduleConnectPrompt)   scheduleConnectPrompt.style.display = 'none';
    if (scheduleEventsList)      scheduleEventsList.style.display = 'none';
    if (scheduleErrorElement)    scheduleErrorElement.style.display = 'none';
    if (scheduleLoadingMessage)  scheduleLoadingMessage.style.display = 'block';

    try {
        const calendarData = await fetchCalendarEvents();
        if (scheduleLoadingMessage) scheduleLoadingMessage.style.display = 'none';
        if (scheduleEventsList)     scheduleEventsList.style.display = 'block';

        if (calendarData.success) {
            window.gFetchedCalendarEvents = calendarData.events || [];
            updateSidebarSchedule(window.gFetchedCalendarEvents);
            await updateSidebarRouteFromCalendar();
        } else {
            displayScheduleError(calendarData.message || '無法取得行事曆資料');
            resetSidebarRoutePreview();
        }
    } catch (error) {
        if (scheduleLoadingMessage) scheduleLoadingMessage.style.display = 'none';
        if (scheduleEventsList)     scheduleEventsList.style.display = 'block';
        console.error('取得行事曆事件錯誤:', error);
        displayScheduleError('無法連接行事曆服務');
        resetSidebarRoutePreview();
    }
}

async function initializeUserData() {
    await initializeMainUserPosition();
    window.currentUserPosition = importedUserPosition;

    if (importedPositionError) {
        console.warn("在 main.js 初始化使用者位置時發生錯誤:", importedPositionError);
    }

    fetchWeather();
    startWeatherInterval();
    await refreshCalendarAndRoutePreview();
}

document.addEventListener('DOMContentLoaded', function() {
    initializeAuth();
    initializeSidebar();

    const initialContent = (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN)
        ? 'recommendations'
        : 'food-diary';

    loadDynamicContent(initialContent)
        .then(() => {
            if (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN) {
                return initializeUserData();
            }
        })
        .catch(error => {
            console.error("[Main] Error during initial content load or user data initialization:", error);
        });

    const connectGoogleButton = document.getElementById('connect-google-button');
    if (connectGoogleButton) {
        connectGoogleButton.addEventListener('click', () => {
            window.location.href = 'google_auth_redirect.php';
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('login_error')) {
        showLoginModal();
    }

    window.refreshSidebarCalendar = refreshCalendarAndRoutePreview;
});
