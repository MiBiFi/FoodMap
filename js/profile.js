import { savePreference, savePet, deleteItem, fetchCalendarEvents, saveUserSetting } from './apiClient.js';
import {
    updateSidebarPreference,
    updateSidebarPetDisplay,
    updateSidebarSchedule,
    displayScheduleError as displaySidebarScheduleError,
    updateSidebarTravelMode
} from './uiUpdater.js';

let sidebarPetsCache = (typeof USER_PETS_DATA !== 'undefined' && Array.isArray(USER_PETS_DATA)) ? [...USER_PETS_DATA] : [];

function hasNewPetForm() {
    return !!document.querySelector('.pet-form.new-pet-form');
}

function checkIfPetListEmpty() {
    const container = document.getElementById('pet-forms-container');
    const noPetsMsg = document.getElementById('no-pets-message');
    if (container && !container.querySelector('.pet-form')) {
        if (!noPetsMsg) {
            const p = document.createElement('p');
            p.id = 'no-pets-message';
            p.className = 'no-pets-message';
            p.textContent = '尚無寵物資料。';
            container.insertAdjacentElement('afterend', p);
        }
    } else if (container && container.querySelector('.pet-form') && noPetsMsg) {
        noPetsMsg.remove();
    }
    if (sidebarPetsCache.length === 0) {
        const petInfoContent = document.getElementById('pet-info-content');
        if (petInfoContent && !petInfoContent.querySelector('#sidebar-no-pets-message')) {
            petInfoContent.innerHTML = '<p id="sidebar-no-pets-message" class="no-pets-message">尚無寵物資訊</p>';
        }
    } else {
        const sidebarNoPetsMsg = document.getElementById('sidebar-no-pets-message');
        if (sidebarNoPetsMsg) sidebarNoPetsMsg.remove();
    }
}

async function refreshSidebarPetInfoAfterChange(changeInfo) {
    if (changeInfo.action === 'add' && changeInfo.pet) {
        sidebarPetsCache.push(changeInfo.pet);
    } else if (changeInfo.action === 'update' && changeInfo.pet) {
        const idx = sidebarPetsCache.findIndex(p => p.id == changeInfo.pet.id);
        if (idx > -1) sidebarPetsCache[idx] = { ...sidebarPetsCache[idx], ...changeInfo.pet };
        else sidebarPetsCache.push(changeInfo.pet);
    } else if (changeInfo.action === 'delete' && changeInfo.petId) {
        sidebarPetsCache = sidebarPetsCache.filter(p => p.id != changeInfo.petId);
    }
    updateSidebarPetDisplay(sidebarPetsCache);
    checkIfPetListEmpty();
}

function handleDeletePreference(button) {
    const itemId = parseInt(button.dataset.id, 10);
    if (!confirm(`您確定要刪除這個偏好嗎？ (ID: ${itemId})`)) return;
    deleteItem(itemId, 'preference')
        .then(data => {
            if (data.success && data.deletedItem) {
                const tag = button.closest('.preference-tag');
                tag.remove();
                updateSidebarPreference('delete', data.deletedItem);
            } else {
                alert('刪除偏好失敗：' + (data.message || '未知錯誤'));
            }
        })
        .catch(() => { alert('刪除偏好過程中發生錯誤。'); });
}

function handleAddPreference(form, type) {
    const input = form.querySelector('#new_preference');
    const value = input.value.trim();
    if (!value) { alert('請輸入要新增的偏好。'); return; }
    savePreference(value, type)
        .then(data => {
            if (data.success && data.newPreference) {
                updateSidebarPreference('add', data.newPreference);
                input.value = '';
            } else {
                alert('新增偏好失敗：' + (data.message || '無法儲存偏好。'));
            }
        })
        .catch(() => { alert('新增偏好過程中發生錯誤。'); });
}

async function handleDeletePet(button) {
    const petId = parseInt(button.dataset.id, 10);
    if (!confirm(`您確定要刪除這個寵物嗎？ (ID: ${petId})`)) return;
    try {
        const data = await deleteItem(petId, 'pet');
        if (data.success) {
            button.closest('.pet-form').remove();
            await refreshSidebarPetInfoAfterChange({ action: 'delete', petId });
        } else {
            alert('刪除寵物失敗：' + (data.message || '未知錯誤'));
        }
    } catch {
        alert('刪除寵物過程中發生錯誤。');
    }
}

async function handleSavePetForm(formElement) {
    const formData = new FormData(formElement);
    const isNewPet = formElement.classList.contains('new-pet-form');
    const originalPetId = formElement.dataset.petId ? parseInt(formElement.dataset.petId, 10) : null;

    // 確保有 pet_image_action 欄位
    if (!formData.has('pet_image_action')) {
        const actionInput = formElement.querySelector('input[name="pet_image_action"]');
        formData.set('pet_image_action', actionInput ? actionInput.value : 'keep');
    }
    // 確保有 current_image_url 欄位
    if (!formData.has('current_image_url')) {
        const urlInput = formElement.querySelector('input[name="current_image_url"]');
        formData.set('current_image_url', urlInput ? urlInput.value : '');
    }

    try {
        const result = await savePet(formData);
        if (!result.success) {
            alert('儲存寵物資料失敗：' + (result.message || '未知錯誤'));
            return;
        }
        alert('寵物資料儲存成功！');

        // 計算最終要顯示的影像 URL
        let finalImageUrl;
        if (result.new_image_url && result.new_image_url !== '') {
            finalImageUrl = result.new_image_url;
        } else if (!isNewPet) {
            // 保留原本舊影像
            finalImageUrl = formData.get('current_image_url') || 'images/default_pet_placeholder.png';
        } else {
            // 新增寵物但沒上傳圖片，預設圖
            finalImageUrl = 'images/default_pet_placeholder.png';
        }

        const petId = result.pet_id || originalPetId;
        const petDataForSidebar = {
            id: petId,
            name: formData.get('pet_name'),
            details: formData.get('pet_details'),
            status: formData.get('pet_status'),
            image_url: finalImageUrl
        };

        if (isNewPet) {
            formElement.dataset.petId = petId;
            const petIdInput = formElement.querySelector('input[name="pet_id"]');
            if (petIdInput) petIdInput.value = petId;

            // 移除「取消新增」按鈕
            const cancelBtn = formElement.querySelector('.cancel-add-pet');
            if (cancelBtn) cancelBtn.remove();

            // 新增「刪除此寵物」按鈕
            const btnContainer = formElement.querySelector('.form-row.form-buttons');
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete-button';
            deleteBtn.dataset.id = petId;
            deleteBtn.dataset.type = 'pet';
            deleteBtn.textContent = '刪除此寵物';
            btnContainer.appendChild(deleteBtn);

            formElement.classList.remove('new-pet-form');
            await refreshSidebarPetInfoAfterChange({ action: 'add', pet: petDataForSidebar });
        } else {
            await refreshSidebarPetInfoAfterChange({ action: 'update', pet: petDataForSidebar });
        }

        // 更新表單中的影像預覽
        const previewImg = formElement.querySelector('.pet-image-preview');
        const currentUrlInput = formElement.querySelector('input[name="current_image_url"]');
        const removeBtn = formElement.querySelector('.remove-pet-image-button');
        if (previewImg) {
            previewImg.src = finalImageUrl;
            previewImg.dataset.currentUrl = finalImageUrl;
        }
        if (currentUrlInput) currentUrlInput.value = finalImageUrl;
        if (removeBtn) removeBtn.style.display = finalImageUrl && finalImageUrl !== 'images/default_pet_placeholder.png' ? 'inline-block' : 'none';

    } catch (error) {
        console.error('儲存寵物請求失敗:', error);
        alert('儲存寵物過程中發生錯誤：' + error.message);
    }
}

function handleAddPetForm() {
    if (hasNewPetForm()) {
        alert('請先儲存或取消目前新增的寵物。');
        return;
    }
    const container = document.getElementById('pet-forms-container');
    const noPetsMsg = document.getElementById('no-pets-message');
    if (noPetsMsg) noPetsMsg.remove();

    const suffix = Date.now();
    const formHTML = `
    <form class="profile-form pet-form new-pet-form" data-pet-id="">
      <input type="hidden" name="pet_id" value="">
      <div class="form-row"><label for="pet_name_new_${suffix}">名稱:</label>
        <input type="text" id="pet_name_new_${suffix}" name="pet_name" required>
      </div>
      <div class="form-row"><label for="pet_details_new_${suffix}">詳細資料:</label>
        <input type="text" id="pet_details_new_${suffix}" name="pet_details">
      </div>
      <div class="form-row"><label for="pet_status_new_${suffix}">狀態:</label>
        <input type="text" id="pet_status_new_${suffix}" name="pet_status">
      </div>
      <div class="form-row pet-image-upload-section">
        <label>寵物圖片:</label>
        <div class="pet-image-preview-container">
          <img src="images/default_pet_placeholder.png"
               alt="新寵物預覽"
               class="pet-image-preview"
               id="pet_image_preview_new_${suffix}"
               data-current-url="">
          <input type="file" name="pet_image_file" id="pet_image_file_new_${suffix}"
                 class="pet-image-file-input" accept="image/*" style="display:none;">
          <input type="hidden" name="pet_image_action" id="pet_image_action_new_${suffix}" value="keep">
          <input type="hidden" name="current_image_url" value="">
        </div>
        <div class="pet-image-buttons">
          <button type="button" class="upload-pet-image-button button-link-style"
                  data-target-file-input="pet_image_file_new_${suffix}">
            <i class="fas fa-upload"></i> 上傳圖片
          </button>
          <button type="button" class="remove-pet-image-button button-link-style"
                  data-preview-id="pet_image_preview_new_${suffix}"
                  data-action-id="pet_image_action_new_${suffix}" style="display:none;">
            <i class="fas fa-trash-alt"></i> 移除圖片
          </button>
        </div>
      </div>
      <div class="form-row form-buttons">
        <button type="submit" class="save-button">儲存新寵物</button>
        <button type="button" class="delete-button cancel-add-pet">取消新增</button>
      </div>
    </form>`;
    container.insertAdjacentHTML('beforeend', formHTML);
    const newForm = container.querySelector('.new-pet-form');
    if (newForm) {
        const fileInput = newForm.querySelector('.pet-image-file-input');
        if (fileInput) attachFileInputChangeListener(fileInput);
    }
}

function attachFileInputChangeListener(fileInput) {
    fileInput.addEventListener('change', function(event) {
        const form = event.target.closest('.pet-form');
        const previewImg = form.querySelector('.pet-image-preview');
        const actionInput = form.querySelector('input[name="pet_image_action"]');
        const removeBtn = form.querySelector('.remove-pet-image-button');
        if (event.target.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                actionInput.value = 'new';
                removeBtn.style.display = 'inline-block';
            };
            reader.readAsDataURL(event.target.files[0]);
        } else {
            const currentUrl = previewImg.dataset.currentUrl || 'images/default_pet_placeholder.png';
            previewImg.src = currentUrl;
            actionInput.value = currentUrl ? 'keep' : 'remove';
            removeBtn.style.display = currentUrl && currentUrl !== 'images/default_pet_placeholder.png' ? 'inline-block' : 'none';
        }
    });
}

async function refreshSidebarCalendar() {
    const scheduleEventsList = document.getElementById('schedule-events-list');
    const loadingMsg = document.getElementById('schedule-loading-message');
    const errorEl = document.getElementById('schedule-error');
    const connectPrompt = document.getElementById('schedule-connect-prompt');
    if (scheduleEventsList) scheduleEventsList.style.display = 'none';
    if (errorEl) errorEl.style.display = 'none';
    if (connectPrompt) connectPrompt.style.display = 'none';
    if (loadingMsg) loadingMsg.style.display = 'block';
    try {
        const data = await fetchCalendarEvents();
        if (scheduleEventsList) scheduleEventsList.style.display = 'block';
        if (loadingMsg) loadingMsg.style.display = 'none';
        if (data.success) {
            if (typeof window.gFetchedCalendarEvents !== 'undefined') { window.gFetchedCalendarEvents = data.events; }
            updateSidebarSchedule(data.events);
        } else { displaySidebarScheduleError(data.message || '無法刷新行事曆資料'); }
    } catch (error) {
        console.error('Error fetching calendar events for refresh:', error);
        if (scheduleEventsList) scheduleEventsList.style.display = 'none';
        if (loadingMsg) loadingMsg.style.display = 'none';
        displaySidebarScheduleError('刷新行事曆時發生錯誤。');
    }
}

async function handleSaveGCalEvent(formElement) {
    const eventSummary = formElement.querySelector('#gcal_event_summary').value;
    const eventDate = formElement.querySelector('#gcal_event_date').value;
    const eventStartTime = formElement.querySelector('#gcal_event_start_time').value;
    const eventLocation = formElement.querySelector('#gcal_event_location').value;
    const eventDescription = formElement.querySelector('#gcal_event_description').value;
    const messageDiv = document.getElementById('schedule-save-message');
    messageDiv.style.display = 'none';
    if (!eventSummary || !eventDate || !eventStartTime) {
        messageDiv.textContent = '事件名稱、日期和開始時間為必填項。';
        messageDiv.style.color = 'red'; messageDiv.style.display = 'block'; return;
    }
    let routeDetailsForDb = null;
    if (typeof window.pendingRouteInfoForCalendar === 'object' && window.pendingRouteInfoForCalendar !== null) {
        routeDetailsForDb = window.pendingRouteInfoForCalendar;
        delete window.pendingRouteInfoForCalendar;
    } else if (sessionStorage.getItem('pendingRouteInfoForCalendar')) {
        try { routeDetailsForDb = JSON.parse(sessionStorage.getItem('pendingRouteInfoForCalendar')); sessionStorage.removeItem('pendingRouteInfoForCalendar'); }
        catch(e) { console.error("Error parsing routeInfo from sessionStorage:", e); }
    }
    const eventData = {
        summary: eventSummary, date: eventDate, startTime: eventStartTime,
        location: eventLocation, description: eventDescription,
        routeDetails: routeDetailsForDb,
        restaurantName: eventSummary.startsWith('用餐：') ? eventSummary.substring(3).trim() : eventSummary
    };
    messageDiv.textContent = '正在儲存至 Google 行事曆並記錄安排...';
    messageDiv.style.color = '#5D4037'; messageDiv.style.display = 'block';
    try {
        const response = await fetch('save_gcal_event.php', {
             method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(eventData)
         });
        if (!response.ok) {
            const errData = await response.json().catch(() => ({message: "儲存行事曆事件時發生網路錯誤"}));
            throw new Error(errData.message || `HTTP Error: ${response.status}`);
        }
        const result = await response.json();
        if (result.success) {
            messageDiv.innerHTML = '行程已成功新增！' + (result.htmlLink ? ` <a href="${result.htmlLink}" target="_blank" rel="noopener noreferrer">在 Google 日曆中查看</a>` : '') + (result.dbMessage ? `<br>${result.dbMessage}` : '');
            messageDiv.style.color = 'green';
            await refreshSidebarCalendar();
        } else {
            messageDiv.textContent = '儲存行程失敗：' + (result.message || '未知錯誤');
            messageDiv.style.color = 'red';
        }
    } catch (error) {
        console.error('儲存 Google 行事曆事件或記錄安排失敗:', error);
        messageDiv.textContent = '儲存行程過程中發生錯誤：' + error.message;
        messageDiv.style.color = 'red';
    }
}

async function handleSaveTravelMode(formElement) {
    const formData = new FormData(formElement);
    const selectedMode = formData.get('travel_mode');
    const messageDiv = document.getElementById('travel-mode-save-message');
    messageDiv.style.display = 'none';

    if (!selectedMode) {
        messageDiv.textContent = '請選擇一種交通方式。';
        messageDiv.style.color = 'red';
        messageDiv.style.display = 'block';
        return;
    }

    console.log("PROFILE.JS: Saving travel mode. Selected mode from form:", selectedMode);
    messageDiv.textContent = '正在儲存交通方式偏好...';
    messageDiv.style.color = '#5D4037';
    messageDiv.style.display = 'block';

    try {
        const result = await saveUserSetting('travel_mode_preference', selectedMode);

        if (result.success) {
            messageDiv.textContent = '交通方式偏好已儲存！';
            messageDiv.style.color = 'green';
            if (typeof updateSidebarTravelMode === 'function') {
                updateSidebarTravelMode(selectedMode);
            }
            if (typeof window.gUserTravelPreference !== 'undefined') {
                window.gUserTravelPreference = selectedMode;
                console.log("PROFILE.JS: Global gUserTravelPreference updated to:", window.gUserTravelPreference);
            }

        } else {
            messageDiv.textContent = '儲存交通方式偏好失敗：' + (result.message || '未知錯誤');
            messageDiv.style.color = 'red';
        }
    } catch (error) {
        console.error('儲存交通方式偏好請求失敗:', error);
        messageDiv.textContent = '儲存過程中發生錯誤：' + error.message;
        messageDiv.style.color = 'red';
    }
}

export function initializeProfilePage() {
    sidebarPetsCache = (typeof USER_PETS_DATA !== 'undefined' && Array.isArray(USER_PETS_DATA)) ? [...USER_PETS_DATA] : [];
    const preferenceSection = document.getElementById('preference-section');
    const editPrefsButton = document.getElementById('edit-prefs-button');
    if (preferenceSection && editPrefsButton) {
        preferenceSection.classList.remove('editing-mode');
        editPrefsButton.textContent = '編輯'; editPrefsButton.classList.remove('is-editing');
        editPrefsButton.addEventListener('click', () => {
            preferenceSection.classList.toggle('editing-mode');
            const isEditing = preferenceSection.classList.contains('editing-mode');
            editPrefsButton.textContent = isEditing ? '完成' : '編輯';
            editPrefsButton.classList.toggle('is-editing', isEditing);
        });
        preferenceSection.addEventListener('click', function(event) {
            if (!preferenceSection.classList.contains('editing-mode')) return;
            const deleteButton = event.target.closest('.preference-tag .delete-button');
            const addPrefButton = event.target.closest('.add-buttons-container .save-button');
            if (deleteButton) { event.preventDefault(); handleDeletePreference(deleteButton); }
            else if (addPrefButton) {
                event.preventDefault();
                const form = addPrefButton.closest('#preference-form');
                const preferenceType = addPrefButton.dataset.type;
                if (form && preferenceType) handleAddPreference(form, preferenceType);
            }
        });
    }

    const petSection = document.getElementById('pet-section');
    if (petSection) {
        checkIfPetListEmpty();
        const existingFileInputs = petSection.querySelectorAll('.pet-form:not(.new-pet-form) .pet-image-file-input');
        existingFileInputs.forEach(attachFileInputChangeListener);
        petSection.addEventListener('click', function(event){
            const target = event.target;
            const petDeleteButton = target.closest('.pet-form .delete-button:not(.cancel-add-pet)');
            const addPetButton = target.closest('#add-pet-button');
            const cancelAddPetButton = target.closest('.cancel-add-pet');
            const savePetButton = target.closest('.pet-form .save-button');
            const uploadImageButton = target.closest('.upload-pet-image-button');
            const removeImageButton = target.closest('.remove-pet-image-button');
            if (petDeleteButton) { event.preventDefault(); handleDeletePet(petDeleteButton); }
            else if (addPetButton) { event.preventDefault(); handleAddPetForm(); }
            else if (cancelAddPetButton) { event.preventDefault(); const formToRemove = cancelAddPetButton.closest('.new-pet-form'); if (formToRemove) formToRemove.remove(); checkIfPetListEmpty(); }
            else if (savePetButton) { event.preventDefault(); const form = savePetButton.closest('.pet-form'); if (form) handleSavePetForm(form); }
            else if (uploadImageButton) { event.preventDefault(); const targetFileId = uploadImageButton.dataset.targetFileInput; const fileInput = document.getElementById(targetFileId); if (fileInput) fileInput.click(); }
            else if (removeImageButton) {
                event.preventDefault(); const petForm = removeImageButton.closest('.pet-form');
                if(petForm){
                    const previewImg = petForm.querySelector('.pet-image-preview');
                    const imageActionInput = petForm.querySelector('input[name="pet_image_action"]');
                    const currentUrlInput = petForm.querySelector('input[name="current_image_url"]');
                    if (previewImg && imageActionInput) {
                        previewImg.src = 'images/default_pet_placeholder.png'; imageActionInput.value = 'remove';
                        if(currentUrlInput) currentUrlInput.value = ''; removeImageButton.style.display = 'none';
                    }
                }
            }
        });
    }

    const travelModeForm = document.getElementById('travel-mode-form');
    if (travelModeForm) {
        travelModeForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleSaveTravelMode(travelModeForm);
        });
    }

    const scheduleForm = document.getElementById('save-schedule-form');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function(event) { event.preventDefault(); handleSaveGCalEvent(scheduleForm); });
    }
}