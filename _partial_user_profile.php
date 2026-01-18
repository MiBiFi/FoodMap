<?php
if (!function_exists('eh')) {
    function eh($string) {
        if (is_array($string) || is_object($string)) {
            return '';
        }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

$prefs_like = [];
$prefs_dislike = [];
if (!empty($user_prefs) && is_array($user_prefs)) {
    foreach ($user_prefs as $pref) {
        if (isset($pref['type'])) {
            if ($pref['type'] === 'like') {
                $prefs_like[] = $pref;
            } else {
                $prefs_dislike[] = $pref;
            }
        }
    }
}

$prefill_event_name        = $prefill_event_name ?? '';
$prefill_event_date        = $prefill_event_date ?? '';
$prefill_event_time        = $prefill_event_time ?? '';
$prefill_event_location    = $prefill_event_location ?? '';
$prefill_event_description = $prefill_event_description ?? '';
$user_schedule_items       = $user_schedule_items ?? [];
$user_travel_mode_preference = $user_travel_mode_preference ?? 'driving';
// $user_pets is assumed to be populated from index.php or controller
?>
<div class="user-profile-view">
    <h2>使用者頁面 - 編輯</h2>

    <section id="preference-section" class="profile-section preference-section">
        <div class="section-header-wrapper">
            <h3><i class="fas fa-utensils nav-icon"></i> 飲食偏好</h3>
            <button type="button" id="edit-prefs-button" class="edit-mode-button">編輯</button>
        </div>
        <div class="preference-display-area">
            <div class="pref-list-container like-list">
                <h4>喜歡:</h4>
                <ul id="preference-list-like">
                    <?php if (!empty($prefs_like)): foreach ($prefs_like as $pref): ?>
                        <li class="preference-tag like" data-pref-id="<?php echo eh($pref['id']); ?>">
                            <span class="pref-text"><?php echo eh($pref['preference_value']); ?></span>
                            <button type="button" class="delete-button" data-id="<?php echo eh($pref['id']); ?>" data-type="preference">✖</button>
                        </li>
                    <?php endforeach; else: ?>
                        <p class="no-prefs-message">尚無喜歡的偏好。</p>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="pref-list-container dislike-list">
                <h4>不喜歡:</h4>
                <ul id="preference-list-dislike">
                    <?php if (!empty($prefs_dislike)): foreach ($prefs_dislike as $pref): ?>
                        <li class="preference-tag dislike" data-pref-id="<?php echo eh($pref['id']); ?>">
                            <span class="pref-text"><?php echo eh($pref['preference_value']); ?></span>
                            <button type="button" class="delete-button" data-id="<?php echo eh($pref['id']); ?>" data-type="preference">✖</button>
                        </li>
                    <?php endforeach; else: ?>
                        <p class="no-prefs-message">尚無不喜歡的偏好。</p>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="add-edit-prefs-section">
            <hr class="section-divider">
            <form id="preference-form" class="profile-form add-pref-wrapper">
                <div class="add-pref-input-group">
                    <label for="new_preference">輸入偏好:</label>
                    <input type="text" id="new_preference" name="new_preference">
                </div>
                <div class="add-buttons-container">
                    <button type="button" class="save-button like-button" data-type="like">喜歡</button>
                    <button type="button" class="save-button dislike-button" data-type="dislike">不喜歡</button>
                </div>
            </form>
        </div>
    </section>

    <section id="travel-mode-section" class="profile-section travel-mode-section">
        <div class="section-header-wrapper">
            <h3><i class="fas fa-route nav-icon"></i> 交通方式偏好</h3>
        </div>
        <form id="travel-mode-form" class="profile-form">
            <div class="form-row" id="travel-mode-options-profile">
                <label class="radio-label">
                    <input type="radio" name="travel_mode" value="driving" <?php echo ($user_travel_mode_preference==='driving')?'checked':''; ?>>
                    <i class="fas fa-car"></i> 開車
                </label>
                <label class="radio-label">
                    <input type="radio" name="travel_mode" value="motorcycle" <?php echo ($user_travel_mode_preference==='motorcycle')?'checked':''; ?>>
                    <i class="fas fa-motorcycle"></i> 機車
                </label>
                <label class="radio-label">
                    <input type="radio" name="travel_mode" value="walking" <?php echo ($user_travel_mode_preference==='walking')?'checked':''; ?>>
                    <i class="fas fa-person-walking"></i> 步行
                </label>
                <label class="radio-label">
                    <input type="radio" name="travel_mode" value="bicycling" <?php echo ($user_travel_mode_preference==='bicycling')?'checked':''; ?>>
                    <i class="fas fa-bicycle"></i> 自行車
                </label>
                <label class="radio-label">
                    <input type="radio" name="travel_mode" value="transit" <?php echo ($user_travel_mode_preference==='transit')?'checked':''; ?>>
                    <i class="fas fa-bus-simple"></i> 大眾運輸
                </label>
            </div>
            <div class="form-row form-buttons">
                <button type="submit" class="save-button" id="save-travel-mode-button">儲存交通方式</button>
            </div>
        </form>
        <div id="travel-mode-save-message" class="form-message" style="display:none;"></div>
    </section>

    <section id="pet-section" class="profile-section pet-section">
        <h3><i class="fas fa-dog nav-icon"></i> 寵物資訊編輯</h3>

        <template id="pet-form-template">
            <form class="profile-form pet-form" data-pet-id="">
                <input type="hidden" name="pet_id" value="">
                <div class="form-row">
                    <label>名稱:</label>
                    <input type="text" name="pet_name" required>
                </div>
                <div class="form-row">
                    <label>詳細資料:</label>
                    <input type="text" name="pet_details">
                </div>
                <div class="form-row">
                    <label>狀態:</label>
                    <input type="text" name="pet_status">
                </div>
                <div class="form-row pet-image-upload-section">
                    <label>寵物圖片:</label>
                    <div class="pet-image-preview-container">
                        <img src="images/default_pet_placeholder.png"
                             alt="寵物預覽"
                             class="pet-image-preview"
                             data-current-url="">
                        <input type="file" name="pet_image_file" class="pet-image-file-input" accept="image/*" style="display:none;">
                        <input type="hidden" name="pet_image_action" value="keep">
                        <input type="hidden" name="current_image_url" value="">
                    </div>
                    <div class="pet-image-buttons">
                        <button type="button" class="upload-pet-image-button button-link-style"><i class="fas fa-upload"></i> 上傳新圖片</button>
                        <button type="button" class="remove-pet-image-button button-link-style" style="display:none;"><i class="fas fa-trash-alt"></i> 移除圖片</button>
                    </div>
                </div>
                <div class="form-row form-buttons">
                    <button type="submit" class="save-button">儲存寵物資料</button>
                    <button type="button" class="cancel-button">取消新增</button>
                    <button type="button" class="delete-button" data-type="pet" style="display:none;">刪除此寵物</button>
                </div>
            </form>
        </template>

        <div id="pet-forms-container">
            <?php if (!empty($user_pets) && is_array($user_pets)): ?>
                <?php foreach ($user_pets as $pet): ?>
                    <?php
                        $pet_id_val = eh($pet['id']);
                        $pet_name_val = eh($pet['name']);
                        $pet_details_val = eh($pet['details'] ?? '');
                        $pet_status_val = eh($pet['status'] ?? '');
                        $pet_image_url_val = eh($pet['image_url'] ?? '');
                        // 修正點：決定顯示圖片路徑
                        $final_image_url = $pet_image_url_val ?: 'images/default_pet_placeholder.png';
                    ?>
                    <form class="profile-form pet-form" data-pet-id="<?php echo $pet_id_val; ?>">
                        <input type="hidden" name="pet_id" value="<?php echo $pet_id_val; ?>">
                        <div class="form-row">
                            <label for="pet_name_<?php echo $pet_id_val; ?>">名稱:</label>
                            <input type="text" id="pet_name_<?php echo $pet_id_val; ?>" name="pet_name" value="<?php echo $pet_name_val; ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="pet_details_<?php echo $pet_id_val; ?>">詳細資料:</label>
                            <input type="text" id="pet_details_<?php echo $pet_id_val; ?>" name="pet_details" value="<?php echo $pet_details_val; ?>">
                        </div>
                        <div class="form-row">
                            <label for="pet_status_<?php echo $pet_id_val; ?>">狀態:</label>
                            <input type="text" id="pet_status_<?php echo $pet_id_val; ?>" name="pet_status" value="<?php echo $pet_status_val; ?>">
                        </div>
                        <div class="form-row pet-image-upload-section">
                            <label>寵物圖片:</label>
                            <div class="pet-image-preview-container">
                                <!-- 修正：src、data-current-url、input value 三者同步 -->
                                <img src="<?php echo $final_image_url; ?>"
                                     alt="<?php echo $pet_name_val; ?> 預覽"
                                     class="pet-image-preview"
                                     id="pet_image_preview_<?php echo $pet_id_val; ?>"
                                     data-current-url="<?php echo $pet_image_url_val; ?>">
                                <input type="file" name="pet_image_file" id="pet_image_file_<?php echo $pet_id_val; ?>" class="pet-image-file-input" accept="image/*" style="display: none;">
                                <input type="hidden" name="pet_image_action" id="pet_image_action_<?php echo $pet_id_val; ?>" value="keep">
                                <input type="hidden" name="current_image_url" value="<?php echo $pet_image_url_val; ?>">
                            </div>
                            <div class="pet-image-buttons">
                                <button type="button" class="upload-pet-image-button button-link-style" data-target-file-input="pet_image_file_<?php echo $pet_id_val; ?>">
                                    <i class="fas fa-upload"></i> 上傳新圖片
                                </button>
                                <?php if (!empty($pet_image_url_val)): ?>
                                    <button type="button" class="remove-pet-image-button button-link-style" data-preview-id="pet_image_preview_<?php echo $pet_id_val; ?>" data-action-id="pet_image_action_<?php echo $pet_id_val; ?>">
                                        <i class="fas fa-trash-alt"></i> 移除圖片
                                    </button>
                                <?php else: ?>
                                     <button type="button" class="remove-pet-image-button button-link-style" data-preview-id="pet_image_preview_<?php echo $pet_id_val; ?>" data-action-id="pet_image_action_<?php echo $pet_id_val; ?>" style="display:none;">
                                        <i class="fas fa-trash-alt"></i> 移除圖片
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-row form-buttons">
                            <button type="submit" class="save-button">儲存寵物資料</button>
                            <button type="button" class="delete-button" data-id="<?php echo $pet_id_val; ?>" data-type="pet">刪除此寵物</button>
                            <button type="button" class="cancel-button" style="display:none;">取消編輯</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php else: ?>
                <p id="no-pets-message" class="no-pets-message">尚無寵物資料。</p>
            <?php endif; ?>
        </div>

        <button type="button" class="add-button" id="add-pet-button">新增寵物</button>
    </section>

    <section id="schedule-section" class="profile-section schedule-section">
        <h3><i class="far fa-calendar-alt nav-icon"></i> 新增至 Google 行事曆</h3>
        <p>請確認或修改以下資訊，將用餐安排新增至您的 Google 行事曆。</p>
        <form id="save-schedule-form" class="profile-form">
            <div class="form-row">
                <label for="gcal_event_summary">事件名稱:</label>
                <input type="text" id="gcal_event_summary" name="gcal_event_summary" value="<?php echo eh($prefill_event_name); ?>" placeholder="例如：晚餐 - 義大利麵餐廳" required>
            </div>
            <div class="form-row">
                <label for="gcal_event_date">日期:</label>
                <input type="date" id="gcal_event_date" name="gcal_event_date" value="<?php echo eh($prefill_event_date); ?>" required>
            </div>
            <div class="form-row">
                <label for="gcal_event_start_time">開始時間:</label>
                <input type="time" id="gcal_event_start_time" name="gcal_event_start_time" value="<?php echo eh($prefill_event_time); ?>" required>
                <span class="form-field-note">(預計持續 1.5 小時)</span>
            </div>
            <div class="form-row">
                <label for="gcal_event_location">地點:</label>
                <input type="text" id="gcal_event_location" name="gcal_event_location" value="<?php echo eh($prefill_event_location); ?>" placeholder="例如：餐廳地址">
            </div>
            <div class="form-row">
                <label for="gcal_event_description">備註 (可選):</label>
                <textarea id="gcal_event_description" name="gcal_event_description" rows="5" placeholder="例如：預訂號碼 #12345, 與陳先生會面"><?php echo eh($prefill_event_description); ?></textarea>
            </div>
            <button type="submit" class="save-button" id="save-gcal-event-button">新增行程至 Google 行事曆</button>
        </form>
        <div id="schedule-save-message" class="form-message" style="display:none;"></div>
    </section>
</div>
