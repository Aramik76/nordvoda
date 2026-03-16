// ==============================================
// animations.js - Анимации сайта
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Анимация появления элементов при скролле
    const animateElements = document.querySelectorAll('.service-card, .project-card, .review-card, .about-content, .about-image');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    animateElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });
    
    // Параллакс эффект для hero
    const hero = document.querySelector('.hero');
    if (hero) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            hero.style.backgroundPositionY = scrolled * 0.5 + 'px';
        });
    }
    
    // Анимация цифр в статистике
    const statNumbers = document.querySelectorAll('.stat-number');
    
    function animateStats() {
        statNumbers.forEach(stat => {
            const target = parseInt(stat.getAttribute('data-target'));
            const current = parseInt(stat.innerText);
            const increment = target / 50;
            
            if (current < target) {
                stat.innerText = Math.ceil(current + increment);
                setTimeout(animateStats, 30);
            } else {
                stat.innerText = target;
            }
        });
    }
    
    // Запускаем анимацию статистики при появлении
    const aboutSection = document.querySelector('.about');
    if (aboutSection && statNumbers.length) {
        let animated = false;
        
        window.addEventListener('scroll', function() {
            if (!animated) {
                const aboutPosition = aboutSection.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (aboutPosition < windowHeight - 100) {
                    animateStats();
                    animated = true;
                }
            }
        });
    }
    
    // Анимация волны
    const heroWave = document.querySelector('.hero-wave svg path');
    if (heroWave) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const scale = 1 + scrolled * 0.0005;
            heroWave.style.transform = `scale(${scale})`;
        });
    }
    
    // Эффект печати для заголовка (только на десктопе)
    const heroTitle = document.querySelector('.hero-title');
    if (heroTitle && window.innerWidth > 768) {
        const originalText = heroTitle.textContent;
        heroTitle.textContent = '';
        
        let i = 0;
        const typeWriter = setInterval(() => {
            if (i < originalText.length) {
                heroTitle.textContent += originalText.charAt(i);
                i++;
            } else {
                clearInterval(typeWriter);
            }
        }, 100);
    }
    
    // Тултипы для социальных иконок
    const socialIcons = document.querySelectorAll('.social-icon');
    
    socialIcons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            const platform = this.classList[1];
            let tooltipText = '';
            
            switch(platform) {
                case 'vk': tooltipText = 'ВКонтакте'; break;
                case 'telegram': tooltipText = 'Telegram'; break;
                case 'whatsapp': tooltipText = 'WhatsApp'; break;
                case 'instagram': tooltipText = 'Instagram'; break;
            }
            
            const tooltip = document.createElement('span');
            tooltip.className = 'social-tooltip';
            tooltip.textContent = tooltipText;
            tooltip.style.cssText = `
                position: absolute;
                bottom: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: #1E293B;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 10;
                animation: fadeIn 0.3s;
            `;
            
            this.style.position = 'relative';
            this.appendChild(tooltip);
        });
        
        icon.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.social-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
});