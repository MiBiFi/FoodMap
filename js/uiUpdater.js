// js/uiUpdater.js
import { calculateDistance } from './utils.js';

export function updateSidebarPreference(action, data) {
    const sidebarList = document.getElementById('sidebar-preference-list');
    const noPrefsMessageElement = document.getElementById('sidebar-no-prefs-message');
    if (!sidebarList) { console.warn("Sidebar preference list '#sidebar-preference-list' not found."); return; }

    if (action === 'add' && data && data.id && data.value && data.type) {
        if (noPrefsMessageElement) noPrefsMessageElement.remove();
        const newLi = document.createElement('li');
        newLi.className = data.type;
        newLi.dataset.prefId = data.id;
        const textSpan = document.createElement('span');
        textSpan.className = 'pref-text';
        textSpan.textContent = data.value;
        const symbolSpan = document.createElement('span');
        const symbol = (data.type === 'like') ? '✔' : '✖';
        const symbolClass = (data.type === 'like') ? 'pref-like-symbol' : 'pref-dislike-symbol';
        symbolSpan.className = `pref-symbol ${symbolClass}`;
        symbolSpan.textContent = symbol;
        newLi.appendChild(textSpan); newLi.appendChild(symbolSpan);
        sidebarList.appendChild(newLi);
    } else if (action === 'delete' && data && data.id) {
        const itemToRemove = sidebarList.querySelector(`li[data-pref-id="${data.id}"]`);
        if (itemToRemove) itemToRemove.remove();
        if (sidebarList.children.length === 0 && !document.getElementById('sidebar-no-prefs-message')) {
             const p = document.createElement('p');
             p.id = 'sidebar-no-prefs-message';
             p.className = 'no-prefs-message';
             p.textContent = '尚無飲食偏好設定';
             const preferencesDiv = sidebarList.closest('.preferences');
             if (preferencesDiv) preferencesDiv.appendChild(p);
             else sidebarList.parentElement.appendChild(p);
        }
    }
}

export function updateCwaWeatherUI(nearestStationData) {
    const tempDisplayEl = document.getElementById('weather-temp-display');
    const iconSpan = document.querySelector('.weather-info .icon i.weather-status-icon');
    const errorEl = document.getElementById('weather-error');
    if (errorEl) errorEl.style.display = 'none';
    if (tempDisplayEl && nearestStationData) {
        const temp = nearestStationData.WeatherElement?.AirTemperature;
        const weatherDesc = nearestStationData.WeatherElement?.Weather;
        if (temp !== undefined && temp !== null && temp > -99) tempDisplayEl.textContent = `${Math.round(temp)}°C`;
        else tempDisplayEl.textContent = '--°C';
        if (iconSpan && weatherDesc) {
             let newIconClass = 'fa-sun';
             if (weatherDesc.includes('雲') || weatherDesc.includes('陰')) newIconClass = 'fa-cloud';
             else if (weatherDesc.includes('雨')) newIconClass = 'fa-cloud-showers-heavy';
             else if (weatherDesc.includes('晴')) newIconClass = 'fa-sun';
             iconSpan.className = `fas ${newIconClass} weather-status-icon`;
        } else if (iconSpan) iconSpan.className = 'fas fa-question-circle weather-status-icon';
    } else displayWeatherError('無法更新天氣顯示。');
}

export function displayWeatherError(message) {
     const errorEl = document.getElementById('weather-error');
     const tempDisplayEl = document.getElementById('weather-temp-display');
     const iconSpan = document.querySelector('.weather-info .icon i.weather-status-icon');
     if(tempDisplayEl) tempDisplayEl.textContent = '--°C';
     if(iconSpan) iconSpan.className = 'fas fa-question-circle weather-status-icon';
     if (errorEl) { errorEl.textContent = message; errorEl.style.display = 'block'; }
}

export function processCwaWeatherData(userLat, userLon, cwaData) {
    if (!cwaData || !cwaData.records || !Array.isArray(cwaData.records.Station) || cwaData.records.Station.length === 0) {
        displayWeatherError('無法取得有效的測站資料。'); return;
    }
    let nearestStation = null; let minDistance = Infinity;
    cwaData.records.Station.forEach(station => {
        const wgs84Coord = station.GeoInfo?.Coordinates?.find(coord => coord.CoordinateName === 'WGS84');
        if (wgs84Coord && typeof wgs84Coord.StationLatitude === 'string' && typeof wgs84Coord.StationLongitude === 'string') {
            const stationLat = parseFloat(wgs84Coord.StationLatitude);
            const stationLon = parseFloat(wgs84Coord.StationLongitude);
            if (!isNaN(stationLat) && !isNaN(stationLon)) {
                const distance = calculateDistance(userLat, userLon, stationLat, stationLon);
                if (distance < minDistance) { minDistance = distance; nearestStation = station; }
            }
        }
    });
    if (nearestStation) updateCwaWeatherUI(nearestStation);
    else displayWeatherError('找不到鄰近的氣象測站。');
}

export function updateSidebarSchedule(events) {
    const listElement = document.getElementById('schedule-events-list');
    const noScheduleMsgElement = document.getElementById('no-schedule-message');
    const errorElement = document.getElementById('schedule-error');
    const loadingMsg = document.getElementById('schedule-loading-message');
    if (!listElement) { console.warn("Schedule list element 'schedule-events-list' not found."); return; }
    listElement.innerHTML = '';
    if (errorElement) errorElement.style.display = 'none';
    if (noScheduleMsgElement) noScheduleMsgElement.remove();
    if (loadingMsg) loadingMsg.style.display = 'none';
    if (events && events.length > 0) {
        events.forEach(event => {
            const eventDiv = document.createElement('div'); eventDiv.className = 'event';
            const iconSpan = document.createElement('span'); iconSpan.className = 'icon';
            const icon = document.createElement('i'); icon.className = event.isAllDay ? 'fas fa-calendar-day' : 'fas fa-clock';
            iconSpan.appendChild(icon);
            const textContainer = document.createElement('div'); textContainer.className = 'event-text-container';
            const nameSpan = document.createElement('div'); nameSpan.className = 'event-name';
            nameSpan.textContent = event.summary.length > 20 ? event.summary.substring(0, 18) + '...' : event.summary;
            nameSpan.title = event.summary;
            const timeInfoSpan = document.createElement('div'); timeInfoSpan.className = 'event-time-info';
            const daySpan = document.createElement('span'); daySpan.className = 'event-day'; daySpan.textContent = '今天';
            const timeDetailSpan = document.createElement('span'); timeDetailSpan.className = 'event-time';
            timeDetailSpan.textContent = event.startTime + (event.endTime && !event.isAllDay ? ` - ${event.endTime}` : (event.isAllDay ? '' : ''));
            timeInfoSpan.appendChild(daySpan); timeInfoSpan.appendChild(timeDetailSpan);
            textContainer.appendChild(nameSpan); textContainer.appendChild(timeInfoSpan);
            if (event.location && event.location.trim() !== '') {
                const locationSpan = document.createElement('div'); locationSpan.className = 'event-location';
                locationSpan.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${event.location.length > 20 ? event.location.substring(0, 18) + '...' : event.location}`;
                locationSpan.title = event.location;
                textContainer.appendChild(locationSpan);
            }
            eventDiv.appendChild(iconSpan); eventDiv.appendChild(textContainer);
            listElement.appendChild(eventDiv);
        });
    } else {
        const p = document.createElement('p'); p.id = 'no-schedule-message';
        p.className = 'no-schedule-message'; p.textContent = '今天沒有行程。';
        listElement.appendChild(p);
    }
}

export function displayScheduleError(message) {
    const listElement = document.getElementById('schedule-events-list');
    const errorElement = document.getElementById('schedule-error');
    const noScheduleMsg = document.getElementById('no-schedule-message');
    const loadingMsg = document.getElementById('schedule-loading-message');
    if (listElement) listElement.innerHTML = '';
    if (noScheduleMsg) noScheduleMsg.remove();
    if (loadingMsg) loadingMsg.style.display = 'none';
    if(errorElement) { errorElement.textContent = message; errorElement.style.display = 'block'; }
}

export function resetSidebarRoutePreview() {
    const routeWidget = document.getElementById('left-sidebar-route-widget');
    if (!routeWidget) return;
    const modeIconElement = document.getElementById('route-preview-mode-icon');
    const totalTimeWrapperElement = document.getElementById('route-preview-total-time-wrapper');
    const totalTimeElement = document.getElementById('route-preview-total-time');
    const summaryDetailsContainer = document.getElementById('route-preview-summary-details');
    const stepsContainer = document.getElementById('sidebar-route-steps-container');
    const errorElement = document.getElementById('route-error');
    const defaultPlaceholder = document.getElementById('route-preview-placeholder-default');

    if (modeIconElement) modeIconElement.innerHTML = '<i class="fas fa-car"></i>';
    if (totalTimeElement) totalTimeElement.textContent = '--:--';
    if (totalTimeWrapperElement) totalTimeWrapperElement.style.display = 'none';
    if (summaryDetailsContainer) summaryDetailsContainer.style.display = 'none';
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }

    if (stepsContainer) {
        stepsContainer.innerHTML = '';
        if (!stepsContainer.querySelector('#sidebar-route-initial-message')) {
            const p = document.createElement('p');
            p.className = 'no-steps-message';
            p.id = 'sidebar-route-initial-message';
            p.textContent = '選擇餐廳並規劃行程後，此處將顯示詳細路線步驟。';
            stepsContainer.appendChild(p);
        }
        const existingInitialMessage = stepsContainer.querySelector('#sidebar-route-initial-message');
        if (existingInitialMessage) {
            existingInitialMessage.style.display = 'block';
        }
    }
    if (defaultPlaceholder) defaultPlaceholder.style.display = 'block';
    if (routeWidget) routeWidget.style.display = 'block';
}

function getManeuverIcon(maneuver, instructions, mode) {
    let iconClass = 'fa-solid fa-arrow-up';
    if (maneuver) {
        if (maneuver.includes('turn-left')) iconClass = 'fa-solid fa-arrow-left';
        else if (maneuver.includes('turn-right')) iconClass = 'fa-solid fa-arrow-right';
        else if (maneuver.includes('roundabout')) iconClass = 'fa-solid fa-circle-notch';
        else if (maneuver.includes('merge')) iconClass = 'fa-solid fa-code-merge';
        else if (maneuver.includes('fork-left')) iconClass = 'fa-solid fa-code-fork fa-rotate-270';
        else if (maneuver.includes('fork-right')) iconClass = 'fa-solid fa-code-fork fa-rotate-90';
        else if (maneuver.includes('ramp-left') || maneuver.includes('on-ramp-left')) iconClass = 'fa-solid fa-arrow-turn-up fa-flip-horizontal';
        else if (maneuver.includes('ramp-right') || maneuver.includes('on-ramp-right')) iconClass = 'fa-solid fa-arrow-turn-up';
        else if (maneuver.includes('keep-left')) iconClass = 'fa-solid fa-arrows-up-to-line fa-flip-horizontal';
        else if (maneuver.includes('keep-right')) iconClass = 'fa-solid fa-arrows-up-to-line';
        else if (maneuver.includes('straight') || maneuver.includes('continue')) iconClass = 'fa-solid fa-arrow-up';
        else if (maneuver.includes('uturn')) iconClass = 'fa-solid fa-u-turn';
    } else if (instructions) {
        const lowerInstructions = instructions.toLowerCase();
        if (lowerInstructions.includes("向左轉")) iconClass = 'fa-solid fa-arrow-left';
        else if (lowerInstructions.includes("向右轉")) iconClass = 'fa-solid fa-arrow-right';
    }
    return `<i class="${iconClass}"></i>`;
}

export function displaySidebarDetailedRoute(routeData) {
    const { totalTime, totalDistance, mapsLink, steps, mode = 'driving' } = routeData;
    const routeWidget = document.getElementById('left-sidebar-route-widget');
    const modeIconElement = document.getElementById('route-preview-mode-icon');
    const totalTimeWrapperElement = document.getElementById('route-preview-total-time-wrapper');
    const totalTimeElement = document.getElementById('route-preview-total-time');
    const totalDistanceElement = document.getElementById('route-preview-total-distance');
    const mapsLinkElement = document.getElementById('route-preview-maps-link');
    const stepsContainer = document.getElementById('sidebar-route-steps-container');
    const errorElement = document.getElementById('route-error');
    const defaultPlaceholder = document.getElementById('route-preview-placeholder-default');
    const initialMessage = document.getElementById('sidebar-route-initial-message');
    const summaryDetailsContainer = document.getElementById('route-preview-summary-details');

    if (!routeWidget || !modeIconElement || !totalTimeWrapperElement || !totalTimeElement || !totalDistanceElement || !mapsLinkElement || !stepsContainer || !errorElement || !defaultPlaceholder || !summaryDetailsContainer) {
        if(errorElement) {
            errorElement.textContent = "UI 元素缺失，無法顯示路線。";
            errorElement.style.display = 'block';
        }
        return;
    }

    if (errorElement) { errorElement.style.display = 'none'; errorElement.textContent = ''; }
    if (defaultPlaceholder) defaultPlaceholder.style.display = 'none';
    if (initialMessage) initialMessage.style.display = 'none';

    let travelModeIconClass = 'fa-car';
    if (mode === 'walking') travelModeIconClass = 'fa-person-walking';
    else if (mode === 'bicycling') travelModeIconClass = 'fa-bicycle';
    else if (mode === 'transit') travelModeIconClass = 'fa-bus-simple';
    else if (mode === 'motorcycle') travelModeIconClass = 'fa-motorcycle';
    modeIconElement.innerHTML = `<i class="fas ${travelModeIconClass}"></i>`;

    if (totalTime) { totalTimeElement.textContent = totalTime; totalTimeWrapperElement.style.display = 'inline'; }
    else { totalTimeWrapperElement.style.display = 'none'; }

    if (totalDistance) { totalDistanceElement.textContent = `總距離：${totalDistance}`; totalDistanceElement.style.display = 'block'; }
    else { totalDistanceElement.style.display = 'none'; }

    if (mapsLink && mapsLink !== '#') { mapsLinkElement.href = mapsLink; mapsLinkElement.style.display = 'inline-block'; }
    else { mapsLinkElement.style.display = 'none'; }

    summaryDetailsContainer.style.display = 'block';

    stepsContainer.innerHTML = '';
    if (steps && steps.length > 0) {
        const ol = document.createElement('ol'); ol.className = 'route-steps-list-sidebar';
        steps.forEach(step => {
            const li = document.createElement('li'); li.className = 'route-step-item-sidebar';

            const iconSpan = document.createElement('span'); iconSpan.className = 'route-step-icon-sidebar';
            iconSpan.innerHTML = getManeuverIcon(step.maneuver, step.html_instructions, mode);

            const instructionDiv = document.createElement('div'); instructionDiv.className = 'route-step-instruction-sidebar';
            let cleanedInstructions = (step.html_instructions || '').replace(/<b>\s*<b>/gi, '<b>').replace(/<\/b>\s*<\/b>/gi, '</b>');
            instructionDiv.innerHTML = cleanedInstructions;

            if (step.distance_text) {
                const distanceSpan = document.createElement('span');
                distanceSpan.className = 'route-step-distance-sidebar';
                distanceSpan.textContent = ` - ${step.distance_text}`;
                instructionDiv.appendChild(distanceSpan);
            }
            if (step.duration_text) {
                const durationSpan = document.createElement('span');
                durationSpan.className = 'route-step-duration-sidebar';
                durationSpan.textContent = ` (${step.duration_text})`;
                instructionDiv.appendChild(durationSpan);
            }

            li.appendChild(iconSpan);
            li.appendChild(instructionDiv);
            ol.appendChild(li);
        });
        stepsContainer.appendChild(ol);
    } else {
        stepsContainer.innerHTML = '<p class="no-steps-message">無法獲取詳細路線步驟。</p>';
    }
    if (routeWidget) routeWidget.style.display = 'block';
}

export function displayRoutePreviewError(message) {
    const routeWidget = document.getElementById('left-sidebar-route-widget');
    const errorElement = document.getElementById('route-error');
    const stepsContainer = document.getElementById('sidebar-route-steps-container');
    const summaryContainer = document.getElementById('route-preview-summary-details');
    const defaultPlaceholder = document.getElementById('route-preview-placeholder-default');
    const totalTimeWrapperElement = document.getElementById('route-preview-total-time-wrapper');
    const initialMessage = document.getElementById('sidebar-route-initial-message');

    if (!routeWidget || !errorElement ) { return; }

    if (stepsContainer) stepsContainer.innerHTML = '';
    if (summaryContainer) summaryContainer.style.display = 'none';
    if (totalTimeWrapperElement) totalTimeWrapperElement.style.display = 'none';
    if (initialMessage) initialMessage.style.display = 'none';

    errorElement.textContent = message;
    errorElement.style.display = 'block';

    if (defaultPlaceholder) defaultPlaceholder.style.display = 'none';
    if (routeWidget) routeWidget.style.display = 'block';
}

export function displayGeneratedPrompt(dataToShow, isFinalAiResponse = false) {
    const responseWidget = document.querySelector('.right-sidebar .chatgpt-response');
    if (!responseWidget) {
        console.warn("AI response widget container not found.");
        return;
    }

    let preElement = responseWidget.querySelector('pre#chatgpt-response-content');
    if (!preElement) {
        let header = responseWidget.querySelector('h3');
        if (!header) {
             header = document.createElement('h3');
             header.innerHTML = '<span><i class="fas fa-robot nav-icon"></i> CHATGPT 回應</span>';
             responseWidget.insertBefore(header, responseWidget.firstChild);
        }
        preElement = document.createElement('pre');
        preElement.id = 'chatgpt-response-content';
        responseWidget.appendChild(preElement);
    }

    let formattedText = "";

    if (isFinalAiResponse && typeof dataToShow === 'object' && dataToShow !== null) {
        if (dataToShow.success === false && dataToShow.message) {
            formattedText = "--- AI 服務錯誤 ---\n";
            formattedText += dataToShow.message;
            if (dataToShow.final_prompt_to_openai_hash) {
                formattedText += `\nPrompt Hash: ${dataToShow.final_prompt_to_openai_hash}`;
            }
             if (dataToShow.openai_response_text) {
                formattedText += `\n原始 AI 回應 (若有): ${dataToShow.openai_response_text}`;
            }
        } else if (dataToShow.recommendations && Array.isArray(dataToShow.recommendations)) {
            if (dataToShow.recommendations.length > 0) {
                const firstRec = dataToShow.recommendations[0];
                if (firstRec && firstRec.status === 'NO_RESULTS') {
                    formattedText = "--- AI 回應 (無結果) ---\n";
                    formattedText += firstRec.message || '目前沒有找到符合條件的餐廳。';
                } else {
                    formattedText = "";
                    dataToShow.recommendations.forEach((resto, index) => {
                        formattedText += `推薦 ${index + 1}:\n`;
                        formattedText += `  名稱：${resto.name || '未提供'}\n`;
                        formattedText += `  地址：${resto.address || '未提供'}\n`;
                        if (resto.rating !== null && resto.rating !== undefined) {
                            let ratingDisplay = Number(resto.rating).toFixed(1);
                            if (resto.user_ratings_total) {
                                ratingDisplay += ` (${resto.user_ratings_total} 則評論)`;
                            }
                            formattedText += `  評分：${ratingDisplay}\n`;
                        } else {
                            formattedText += `  評分：N/A\n`;
                        }
                        formattedText += `  理由：${resto.reason || '未提供'}\n`;
                        formattedText += `  成本：${resto.cost || '未提供'}\n\n`;
                    });
                }
            } else {
                formattedText = "--- AI 回應 ---\nAI 助手表示：目前沒有找到可推薦的餐廳。";
            }
        } else {
            formattedText = "--- AI 最終回應 (未知結構) ---\n" + JSON.stringify(dataToShow, null, 2);
        }
    } else if (typeof dataToShow === 'string') {
        formattedText = dataToShow;
    } else {
        formattedText = "--- 調試資訊 ---\n" + JSON.stringify(dataToShow, null, 2);
    }

    preElement.textContent = formattedText;
    responseWidget.style.display = 'block';
}


export function displayRecommendations(restaurants, defaultEventTime = null) {
    const recGridResults = document.getElementById('recommendation-grid-results');
    const recListTitle = document.getElementById('recommendation-list-title');
    const initialMessage = document.getElementById('initial-recommendation-message');

    if (!recGridResults) {
        console.error("Recommendation grid results element not found.");
        return;
    }
    recGridResults.innerHTML = '';
    if (initialMessage) initialMessage.style.display = 'none';

    recGridResults.style.justifyContent = 'flex-start';
    recGridResults.style.alignItems = 'stretch';

    if (!restaurants || (Array.isArray(restaurants) && restaurants.length === 0)) {
        let message = '抱歉，目前沒有找到符合條件的推薦。';
        if (restaurants && restaurants[0] && restaurants[0].status === 'NO_RESULTS' && restaurants[0].message) {
            message = restaurants[0].message;
        }
        recGridResults.innerHTML = `<p class="info-message">${message}</p>`;
        if (recListTitle) recListTitle.style.display = 'none';
        return;
    }

    if (!Array.isArray(restaurants)) {
        console.error("Recommendations data is not an array:", restaurants);
        recGridResults.innerHTML = '<p class="error-message">推薦資料格式錯誤。</p>';
        if (recListTitle) recListTitle.style.display = 'none';
        return;
    }

    if (restaurants[0] && restaurants[0].status === 'NO_RESULTS') {
         recGridResults.innerHTML = `<p class="info-message">${restaurants[0].message || '抱歉，目前沒有找到符合條件的推薦。'}</p>`;
         if (recListTitle) recListTitle.style.display = 'none';
         return;
    }


    if (recListTitle) {
        recListTitle.textContent = `為您找到 ${restaurants.length} 個美食選擇：`;
        recListTitle.style.display = 'block';
    }

    const defaultImageUrl = './assets/images/default-restaurant.webp';

    restaurants.forEach(resto => {
         const article = document.createElement('article');
         article.className = 'food-card-list-item';
         article.dataset.address = resto.address || '';
         article.dataset.name = resto.name || '未知餐廳';
         article.dataset.placeId = resto.place_id || '';
         if (defaultEventTime) article.dataset.defaultEventTime = defaultEventTime;

         const imageContainer = document.createElement('div');
         imageContainer.className = 'food-card-image-container';

         const img = document.createElement('img');
         img.className = 'food-card-image';
         img.alt = resto.name || '餐廳圖片';

         if (resto.google_image_url) {
             img.src = resto.google_image_url;
         } else if (resto.ai_image_url) {
             img.src = resto.ai_image_url;
         } else {
             img.src = defaultImageUrl;
         }
         img.onerror = function() {
             this.onerror = null;
             this.src = defaultImageUrl;
             const placeholder = document.createElement('div');
             placeholder.className = 'food-card-placeholder-img-icon';
             placeholder.innerHTML = '<i class="fas fa-utensils"></i>';
             if (this.parentElement) {
                this.parentElement.insertBefore(placeholder, this);
                this.style.display = 'none';
             }
         };
         imageContainer.appendChild(img);

         const textContentDiv = document.createElement('div');
         textContentDiv.className = 'food-card-text-content';

         const nameH3 = document.createElement('h3');
         nameH3.className = 'food-card-title';
         nameH3.textContent = resto.name || '未知餐廳';

         const addressP = document.createElement('p');
         addressP.className = 'food-card-address';
         const addressIcon = document.createElement('i');
         addressIcon.className = 'fas fa-map-marker-alt address-icon';
         addressP.appendChild(addressIcon);
         addressP.appendChild(document.createTextNode(` ${resto.address || '地址未提供'}`));

         const ratingP = document.createElement('p');
         ratingP.className = 'food-card-rating';
         let ratingText = '評分：N/A';
         if (resto.rating !== null && resto.rating !== undefined) {
             ratingText = `評分：${Number(resto.rating).toFixed(1)}`;
             if (resto.user_ratings_total) {
                 ratingText += ` (${resto.user_ratings_total} 則評論)`;
             }
         }
         ratingP.textContent = ratingText;


         const reasonP = document.createElement('p');
         reasonP.className = 'food-card-reason';
         reasonP.textContent = resto.reason || '推薦理由未提供。';

         const costP = document.createElement('p');
         costP.className = 'food-card-cost';
         costP.textContent = `人均消費：${resto.cost || '未提供'}`;
         
         const sourceP = document.createElement('p');
         sourceP.className = 'food-card-source';
         let sourceText = 'AI 推薦';
         if (resto.source === 'google_places_api_enhanced') {
            sourceText = 'Google & AI 推薦';
         } else if (resto.source === 'ai_only_fallback') {
            sourceText = 'AI 推薦 (備援)';
         }
         sourceP.textContent = `來源：${sourceText}`;


         textContentDiv.appendChild(nameH3);
         textContentDiv.appendChild(addressP);
         textContentDiv.appendChild(ratingP);
         textContentDiv.appendChild(reasonP);
         textContentDiv.appendChild(costP);
         textContentDiv.appendChild(sourceP);

         article.appendChild(imageContainer);
         article.appendChild(textContentDiv);
         recGridResults.appendChild(article);
    });
}

export function updateRoutePreviewDecorative(timeString, distanceString = null, mapsUrl = null, mode = 'driving') {
    const routeWidget = document.getElementById('left-sidebar-route-widget');
    if (!routeWidget) { console.warn("Route preview widget not found."); return; }

    const modeIconElement = document.getElementById('route-preview-mode-icon');
    const totalTimeWrapperElement = document.getElementById('route-preview-total-time-wrapper');
    const totalTimeElement = document.getElementById('route-preview-total-time');
    const totalDistanceElement = document.getElementById('route-preview-total-distance');
    const mapsLinkElement = document.getElementById('route-preview-maps-link');
    const defaultPlaceholder = document.getElementById('route-preview-placeholder-default');
    const initialMessage = document.getElementById('sidebar-route-initial-message');
    const errorElement = document.getElementById('route-error');
    const summaryContainer = document.getElementById('route-preview-summary-details');

    if (errorElement) errorElement.style.display = 'none';

    if (timeString && timeString !== "--:--") {
        if (defaultPlaceholder) defaultPlaceholder.style.display = 'none';
        if (initialMessage) initialMessage.style.display = 'none';

        let travelModeIconClass = 'fa-car';
        if (mode === 'walking') travelModeIconClass = 'fa-person-walking';
        else if (mode === 'bicycling') travelModeIconClass = 'fa-bicycle';
        else if (mode === 'transit') travelModeIconClass = 'fa-bus-simple';
        else if (mode === 'motorcycle') travelModeIconClass = 'fa-motorcycle';
        if (modeIconElement) modeIconElement.innerHTML = `<i class="fas ${travelModeIconClass}"></i>`;

        if (totalTimeElement) totalTimeElement.textContent = timeString;
        if (totalTimeWrapperElement) totalTimeWrapperElement.style.display = 'inline';

        if (totalDistanceElement) {
            if (distanceString) {
                totalDistanceElement.textContent = `總距離：${distanceString}`;
                totalDistanceElement.style.display = 'block';
            } else { totalDistanceElement.style.display = 'none'; }
        }
        if (mapsLinkElement) {
            if (mapsUrl && mapsUrl !== '#') {
                mapsLinkElement.href = mapsUrl;
                mapsLinkElement.style.display = 'inline-block';
            } else { mapsLinkElement.style.display = 'none'; }
        }
        if(summaryContainer) summaryContainer.style.display = 'block';

    } else {
        resetSidebarRoutePreview();
    }
    routeWidget.style.display = 'block';
}

export function updateSidebarPetDisplay(pets = []) {
    const petInfoContent       = document.getElementById('pet-info-content');
    const noPetsMessageElement = document.getElementById('sidebar-no-pets-message');
    const petArrow             = document.querySelector('h3.collapsible-header[data-target="#pet-info-content"] .arrow');

    if (!petInfoContent) {
        console.warn("Sidebar pet info content area '#pet-info-content' not found.");
        return;
    }

    petInfoContent.innerHTML = '';

    if (pets && pets.length > 0) {
        if (noPetsMessageElement) noPetsMessageElement.remove();

        const petsContainer = document.createElement('div');
        petsContainer.className = 'pets-container';

        pets.forEach(pet => {
            const petCard = document.createElement('div');
            petCard.className = 'pet-card';
            petCard.dataset.petId = pet.id ? pet.id.toString() : '';

            const nameH4 = document.createElement('h4');
            nameH4.textContent = pet.name || '未知名稱';
            petCard.appendChild(nameH4);

            if (pet.details) {
                const detailsP = document.createElement('p');
                detailsP.textContent = pet.details;
                petCard.appendChild(detailsP);
            }

            if (pet.status) {
                const statusP = document.createElement('p');
                statusP.className = 'pet-status';
                statusP.textContent = pet.status;
                petCard.appendChild(statusP);
            }

            const imageUrl = pet.image_url || pet.image;
            if (imageUrl) {
                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = pet.name || '寵物影像';
                img.className = 'pet-image';
                petCard.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'pet-image-placeholder';
                placeholder.innerHTML = '<i class="fas fa-paw"></i>';
                petCard.appendChild(placeholder);
            }

            petsContainer.appendChild(petCard);
        });

        petInfoContent.appendChild(petsContainer);

        if (petArrow) {
            petArrow.innerHTML = 'V';
            petArrow.dataset.petCount = pets.length.toString();
        }
        petInfoContent.style.display = 'block';
    } else {
        if (!noPetsMessageElement) {
            const p = document.createElement('p');
            p.id = 'sidebar-no-pets-message';
            p.className = 'no-prefs-message';
            p.textContent = '尚無寵物資訊';
            petInfoContent.appendChild(p);
        }
        if (petArrow) {
            petArrow.innerHTML = '>';
            petArrow.dataset.petCount = '0';
        }
        petInfoContent.style.display = 'block';
    }
}

function getTravelModeDisplayData(modeValue) {
    let display = { icon: 'fa-question-circle', text: '未知' };
    switch (modeValue) {
        case 'driving':
            display = { icon: 'fa-car', text: '開車' };
            break;
        case 'motorcycle':
            display = { icon: 'fa-motorcycle', text: '機車' };
            break;
        case 'walking':
            display = { icon: 'fa-person-walking', text: '步行' };
            break;
        case 'bicycling':
            display = { icon: 'fa-bicycle', text: '自行車' };
            break;
        case 'transit':
            display = { icon: 'fa-bus-simple', text: '大眾運輸' };
            break;
    }
    return display;
}

export function updateSidebarTravelMode(selectedMode) {
    const displayElement = document.getElementById('sidebar-travel-mode-display');
    if (displayElement) {
        const displayData = getTravelModeDisplayData(selectedMode);
        displayElement.innerHTML = `<i class="fas ${displayData.icon}"></i> ${displayData.text}`;
    } else {
        console.warn("Sidebar travel mode display element ('sidebar-travel-mode-display') not found for update.");
    }
}

