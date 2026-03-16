// Упрощенный Cookie Banner (без Google Analytics)
const CookieBanner = (function() {
    const config = {
        apiUrl: '/cookie-consent.php',
        cookieName: 'cookie_consent',
        bannerId: 'cookie-consent-banner'
    };

    let bannerElement = null;

    function init() {
        const savedConsent = localStorage.getItem(config.cookieName);
        if (savedConsent) {
            // Уже есть согласие, ничего не делаем
            return;
        }

        // Показываем баннер после загрузки DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createBanner);
        } else {
            createBanner();
        }
    }

    function createBanner() {
        if (document.getElementById(config.bannerId)) return;

        bannerElement = document.createElement('div');
        bannerElement.id = config.bannerId;
        bannerElement.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 25px;
            z-index: 9999;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e0e0e0;
        `;

        bannerElement.innerHTML = `
            <div style="text-align: center;">
                <h3 style="color: var(--primary); margin-bottom: 15px;">
                    <i class="fas fa-cookie-bite" style="margin-right: 10px;"></i>
                    Мы используем cookies
                </h3>
                <p style="color: var(--dark); line-height: 1.6; margin-bottom: 20px;">
                    Чтобы обеспечить вам наилучший опыт на нашем сайте, мы используем файлы cookie.
                    Продолжая использовать сайт, вы соглашаетесь с нашей
                    <a href="cookie-policy.html" style="color: var(--primary);">политикой использования cookies</a>
                    и <a href="privacy-policy.html" style="color: var(--primary);">политикой конфиденциальности</a>.
                </p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button id="cookie-accept" class="btn" style="min-width: 150px;">
                        <i class="fas fa-check"></i> Согласен
                    </button>
                    <button id="cookie-reject" class="btn btn-secondary" style="min-width: 150px;">
                        <i class="fas fa-times"></i> Отказать
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(bannerElement);

        document.getElementById('cookie-accept').addEventListener('click', () => saveConsent('accepted'));
        document.getElementById('cookie-reject').addEventListener('click', () => saveConsent('rejected'));
    }

    async function saveConsent(type) {
        // Скрываем баннер
        if (bannerElement) {
            bannerElement.remove();
        }

        // Сохраняем в localStorage
        const consentData = {
            type: type,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem(config.cookieName, JSON.stringify(consentData));

        // Отправляем на сервер
        try {
            await fetch(config.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_consent',
                    consent: type
                })
            });
        } catch (error) {
            console.error('Ошибка при отправке согласия:', error);
        }
    }

    return { init: init };
})();

// Автозапуск
document.addEventListener('DOMContentLoaded', () => CookieBanner.init());