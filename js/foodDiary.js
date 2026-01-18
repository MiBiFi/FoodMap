// js/foodDiary.js

function createDiaryEntryElement(entryData, isUserLoggedIn) { // 添加 isUserLoggedIn 參數
    const article = document.createElement('article');
    article.className = 'diary-entry';
    article.dataset.entryId = entryData.id;
    let imageHTML = '';
    if (entryData.image_path) {
        imageHTML = `
        <div class="entry-image-container">
            <img src="${entryData.image_path}" alt="${entryData.restaurant_name}">
            ${entryData.image_caption ? `<div class="image-caption">${entryData.image_caption}</div>` : ''}
        </div>`;
    }

    let authorHTML = '';
    // 如果未登入，且 entryData 中有 username，則顯示作者
    // 注意：IS_LOGGED_IN 是全域變數，應從外部傳入或獲取
    if (!isUserLoggedIn && entryData.username) {
        authorHTML = `<span class="entry-author">由 ${entryData.username} 分享</span>`;
    }


    article.innerHTML = `
        ${imageHTML}
        <div class="entry-text-content">
            <span class="entry-date">${entryData.display_date}</span>
            ${authorHTML}
            <h3>${entryData.restaurant_name}</h3>
            <p class="diary-full-content">${entryData.content}</p>
            <button type="button" class="toggle-content-button" aria-expanded="false">查看更多</button>
        </div>
    `;
    const contentP = article.querySelector('p.diary-full-content');
    const toggleBtn = article.querySelector('.toggle-content-button');
    if (contentP && toggleBtn) {
        initializeToggleContent(contentP, toggleBtn);
    }
    return article;
}

function initializeToggleContent(contentElement, buttonElement) {
    const isTextOverflowing = contentElement.scrollHeight > contentElement.clientHeight || contentElement.offsetHeight < contentElement.scrollHeight;

    if (isTextOverflowing || contentElement.classList.contains('truncated')) {
        buttonElement.style.display = 'inline';
        contentElement.classList.add('truncated');
        buttonElement.textContent = '查看更多';
        buttonElement.setAttribute('aria-expanded', 'false');
    } else {
        buttonElement.style.display = 'none';
        contentElement.classList.remove('truncated');
        return;
    }

    buttonElement.addEventListener('click', () => {
        const isCurrentlyTruncated = contentElement.classList.contains('truncated');
        contentElement.classList.toggle('truncated');
        buttonElement.textContent = isCurrentlyTruncated ? '收起內容' : '查看更多';
        buttonElement.setAttribute('aria-expanded', String(!isCurrentlyTruncated));
    });
}


export function initializeFoodDiaryHandlers() {

    const imageUploadInput = document.getElementById('diary_image_upload');
    const imagePreview = document.getElementById('image_preview');
    const defaultPreviewSrc = 'images/add_image_placeholder.png';

    if (imageUploadInput && imagePreview) {
        imageUploadInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                imagePreview.src = defaultPreviewSrc;
            }
        });
    }

    const addDiaryForm = document.getElementById('add-diary-form');
    const addDiaryMessage = document.getElementById('add-diary-message');

    if (addDiaryForm && addDiaryMessage) {
        addDiaryForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            addDiaryMessage.style.display = 'none';
            addDiaryMessage.textContent = '';
            addDiaryMessage.className = 'form-message';

            const formData = new FormData(addDiaryForm);
            const submitButton = addDiaryForm.querySelector('button[type="submit"]');
            if (!submitButton) return;

            submitButton.disabled = true;
            submitButton.textContent = '儲存中...';

            try {
                const response = await fetch(addDiaryForm.action, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    let errorMsg = `HTTP error! status: ${response.status}`;
                    try { const errData = await response.json(); errorMsg = errData.message || errorMsg; }
                    catch (e) { /* ignore */ }
                    throw new Error(errorMsg);
                }

                const result = await response.json();

                if (result.success) {
                    addDiaryMessage.textContent = result.message;
                    addDiaryMessage.classList.add('success');
                    addDiaryForm.reset();
                    if(imagePreview) imagePreview.src = defaultPreviewSrc;

                    if (result.new_entry) {
                        const list = document.querySelector('.diary-entries-list');
                        if (list) {
                            const noEntriesMsg = list.querySelector('.no-entries-message');
                            if (noEntriesMsg) noEntriesMsg.remove();
                            // 傳遞 IS_LOGGED_IN 給 createDiaryEntryElement
                            const newEntryElement = createDiaryEntryElement(result.new_entry, typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN);
                            list.insertBefore(newEntryElement, list.firstChild);
                        }
                    }
                } else {
                    addDiaryMessage.textContent = result.message || '新增失敗，請檢查輸入內容。';
                    addDiaryMessage.classList.add('error');
                }
            } catch (error) {
                console.error('新增日誌錯誤:', error);
                addDiaryMessage.textContent = error.message || '發生客戶端錯誤，請稍後再試。';
                addDiaryMessage.classList.add('error');
            } finally {
                addDiaryMessage.style.display = 'block';
                submitButton.disabled = false;
                submitButton.textContent = '儲存日誌';
            }
        });
    }

    const diaryEntries = document.querySelectorAll('.diary-entry');
    diaryEntries.forEach(entry => {
        const contentP = entry.querySelector('p.diary-full-content');
        const toggleBtn = entry.querySelector('.toggle-content-button');
        if (contentP && toggleBtn) {
            initializeToggleContent(contentP, toggleBtn);
        }
    });

    const loginTriggers = document.querySelectorAll('#login-trigger-from-diary, #login-trigger-from-diary-empty');
    loginTriggers.forEach(trigger => {
        if (trigger) {
            trigger.addEventListener('click', async function(e) {
                e.preventDefault();
                try {
                    const authModule = await import('./auth.js');
                    if (authModule && typeof authModule.showLoginModal === 'function') {
                        authModule.showLoginModal();
                    } else {
                        console.warn('showLoginModal function not found in auth.js');
                    }
                } catch (err) {
                    console.error('Failed to load auth.js module:', err);
                }
            });
        }
    });
}