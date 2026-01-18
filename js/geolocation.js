// geolocation.js (或 main.js)

/**
 * Asynchronously gets the current geolocation of the user.
 * @param {object} options - Optional PositionOptions for getCurrentPosition.
 *                           Defaults to enableHighAccuracy: true, timeout: 7000, maximumAge: 0.
 * @returns {Promise<{latitude: number, longitude: number}>}
 *          A promise that resolves with an object containing latitude and longitude,
 *          or rejects with an error object containing a user-friendly message.
 */
export async function getCurrentLocation(
    options = { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }
) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            // It's better to reject with an Error object
            return reject(new Error('您的瀏覽器不支援地理位置功能。'));
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            (error) => {
                let message = '無法取得您的地理位置。';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = '您已拒絕地理位置存取請求。請檢查瀏覽器設定。';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = '目前無法偵測您的位置資訊，請稍後再試或檢查您的網路連線。';
                        break;
                    case error.TIMEOUT:
                        message = '取得地理位置請求超時。';
                        break;
                    default:
                        message = `發生未知錯誤 (Code: ${error.code})。`;
                        break;
                }
                reject(new Error(message)); // Reject with an Error object
            },
            options // Pass the provided options
        );
    });
}

// 如果您還想在 main.js 中保留一個初始載入的 currentUserPosition
export let initialCurrentUserPosition = null;
export let initialPositionError = null;

export async function initializeMainUserPosition() {
    try {
        initialCurrentUserPosition = await getCurrentLocation({ enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }); // Longer maximumAge for initial load
    } catch (error) {
        initialPositionError = error.message;
        console.warn("Failed to load initial user position in main.js:", initialPositionError);
    }
}

// 在 main.js 的 DOMContentLoaded 或類似地方呼叫 initializeMainUserPosition()
// document.addEventListener('DOMContentLoaded', () => {
//     if (IS_LOGGED_IN) {
//         initializeMainUserPosition();
//     }
// });