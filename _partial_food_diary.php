<?php
global $is_logged_in;

if (!function_exists('eh')) {
    function eh($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="food-diary-view">
    <h2>美食日誌</h2>

    <?php if ($is_logged_in): ?>
    <section class="add-diary-entry-section">
        <h3>新增一筆日誌</h3>
        <form id="add-diary-form" action="save_food_diary_entry.php" method="POST" enctype="multipart/form-data">
            <div class="form-upper-section">
                <div class="image-upload-container">
                    <label for="diary_image_upload" class="image-placeholder">
                        <img id="image_preview" src="images/add_image_placeholder.png" alt="上傳圖片預覽">
                        <span>點擊此處上傳照片</span>
                    </label>
                    <input type="file" id="diary_image_upload" name="diary_image" accept="image/*" style="display: none;">
                </div>
                <div class="fields-container">
                    <div class="form-group">
                        <label for="diary_date">日期：</label>
                        <input type="date" id="diary_date" name="diary_date" required>
                    </div>
                    <div class="form-group">
                        <label for="diary_restaurant">餐廳名稱：</label>
                        <input type="text" id="diary_restaurant" name="diary_restaurant" placeholder="例如：巷口那間拉麵店" required>
                    </div>
                     <div class="form-group">
                        <label for="diary_caption">圖片說明 (可選)：</label>
                        <input type="text" id="diary_caption" name="diary_caption" placeholder="例如：狗狗吃得很開心！">
                    </div>
                </div>
            </div>
            <div class="form-group content-group">
                <label for="diary_content">心得感想：</label>
                <textarea id="diary_content" name="diary_content" rows="4" placeholder="記錄下這次美食體驗的點點滴滴..." required></textarea>
            </div>
            <div class="form-group form-buttons">
                <button type="submit" class="submit-button">儲存日誌</button>
                 <button type="reset" class="reset-button">重新填寫</button>
            </div>
        </form>
        <div id="add-diary-message" class="form-message" style="display:none;"></div>
    </section>
    <hr class="section-divider">
    <?php endif; ?>


    <section class="diary-entries-list-section">
        <h3><?php echo $is_logged_in ? "我的日誌列表" : "大家的日誌分享"; ?></h3>
        <div class="diary-entries-list">
            <?php if (!empty($foodDiaryEntries)): ?>
                <?php foreach ($foodDiaryEntries as $entry): ?>
                    <article class="diary-entry" data-entry-id="<?php echo eh($entry['id']); ?>">
                        <?php if (!empty($entry['image_path'])): ?>
                        <div class="entry-image-container">
                            <img src="<?php echo eh($entry['image_path']); ?>" alt="<?php echo eh($entry['restaurant_name']); ?>">
                            <?php if (!empty($entry['image_caption'])): ?>
                                <div class="image-caption"><?php echo eh($entry['image_caption']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="entry-text-content">
                            <span class="entry-date"><?php echo eh($entry['display_date'] ?? $entry['entry_date']); ?></span>
                            <?php if (!$is_logged_in && isset($entry['username'])): // 未登入時顯示作者 ?>
                                <span class="entry-author">由 <?php echo eh($entry['username']); ?> 分享</span>
                            <?php endif; ?>
                            <h3><?php echo eh($entry['restaurant_name']); ?></h3>
                            <p class="diary-full-content"><?php echo nl2br(eh($entry['content'])); ?></p>
                            <button type="button" class="toggle-content-button" aria-expanded="false">查看更多</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <?php if ($is_logged_in): ?>
                    <p class="no-entries-message">您還沒有任何美食日誌。馬上新增第一筆吧！</p>
                <?php else: ?>
                    <p class="no-entries-message">目前還沒有美食日誌分享。<?php /* 或者您可以引導他們登入新增： */ ?> <a href="#" id="login-trigger-from-diary-empty">登入</a>來新增您的第一筆日誌！</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>