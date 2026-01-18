<?php
?>
<div class="recommendations-view">
    <section class="search-area">
        <h2>今天想吃什麼呢？</h2>
        <div class="search-input-group">
            <input type="text" id="food-search-input" class="main-search-input" placeholder="例如：牛肉麵、日式料理...">
            <input type="text" id="location-search-input" class="location-search-input" placeholder="可選：輸入地區或地標 (如：台北車站)">
        </div>
        <button type="button" id="food-search-button" class="search-submit-button">幫我找餐廳</button>
    </section>

    <h2 id="recommendation-list-title" style="display:none;">美食推薦名單</h2>
    <div class="recommendation-grid" id="recommendation-grid-results">
        <p id="initial-recommendation-message" class="initial-recommendation-message">請在上方輸入您的需求，讓我們為您推薦美食！</p>
    </div>
</div>