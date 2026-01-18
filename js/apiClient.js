// js/apiClient.js

const BASE_URL = '.';

async function fetchData(urlOrPath, options = {}) {
    let finalUrl;
    if (urlOrPath.startsWith('http://') || urlOrPath.startsWith('https://') || urlOrPath.startsWith('/')) {
        finalUrl = urlOrPath;
    } else {
        const cleanBase = BASE_URL.endsWith('/') ? BASE_URL.slice(0, -1) : BASE_URL;
        const cleanPath = urlOrPath.startsWith('/') ? urlOrPath.slice(1) : urlOrPath;
        finalUrl = `${cleanBase}/${cleanPath}`;
    }

    try {
        const response = await fetch(finalUrl, options);

        if (!response.ok) {
            let errorMessage = `HTTP error! Status: ${response.status}`;
            try {
                const errData = await response.json();
                errorMessage = errData.message || errorMessage;
                if(errData.details) errorMessage += ` Details: ${errData.details}`;
            } catch (e) {
                // Response was not JSON or JSON parsing failed for error.
            }
            throw new Error(errorMessage);
        }

        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return await response.json();
        } else {
            return await response.text();
        }
    } catch (error) {
        console.error(`Fetch error for ${finalUrl}:`, error);
        throw error;
    }
}

export async function fetchContent(contentId, options = {}) {
    let targetPath = 'content_loader.php';
    let effectiveOptions = options;

    if (!options.method || options.method.toUpperCase() === 'GET') {
        targetPath = `content_loader.php?content=${contentId}`;
    } else if (options.method.toUpperCase() === 'POST') {
        if (options.body && options.headers && options.headers['Content-Type'] === 'application/json') {
            try {
                let bodyData = JSON.parse(options.body);
                bodyData.content = contentId;
                effectiveOptions = { ...options, body: JSON.stringify(bodyData) };
            } catch (e) {
                console.error("Could not parse/modify POST body for contentId in fetchContent");
            }
        }
    }
    return await fetchData(targetPath, effectiveOptions);
}


export async function fetchCalendarEvents() {
    return await fetchData('get_calendar_events.php');
}

export async function fetchCwaWeatherData() {
    return await fetchData('get_weather.php');
}

export async function savePreference(preferenceValue, preferenceType) {
    return await fetchData('save_preference.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_type: 'preference',
            preference: preferenceValue,
            type: preferenceType
        })
    });
}

export async function deleteItem(itemId, itemType) {
     return await fetchData('delete_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: itemId, type: itemType })
    });
}

export async function savePet(formData) {
     return await fetchData('save_pet.php', {
         method: 'POST',
         body: formData
     });
}

export async function saveGoogleCalendarEvent(eventData) {
    return await fetchData('save_gcal_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(eventData)
    });
}


export async function fetchSavedRouteForEvent(gcalEventId) {
    if (!gcalEventId) {
        return { success: false, message: '缺少 Google Calendar 事件 ID。' };
    }
    return await fetchData('get_saved_route.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ gcal_event_id: gcalEventId })
    });
}

export async function saveUserSetting(settingKey, settingValue) {
    return await fetchData('save_preference.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_type: 'user_setting',
            setting_key: settingKey,
            setting_value: settingValue
        })
    });
}