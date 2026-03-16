// ==============================================
// main.js - Основные скрипты сайта
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Фиксированная шапка при скролле
    const header = document.getElementById('header');
    const scrollBtn = document.getElementById('scrollTop');
    
    window.addEventListener('scroll', function() {
        if(window.scrollY > 100) {
            header?.classList.add('scrolled');
            scrollBtn?.classList.add('show');
        } else {
            header?.classList.remove('scrolled');
            scrollBtn?.classList.remove('show');
        }
    });
    
    // Активный пункт меню
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    
    window.addEventListener('scroll', function() {
        let current = '';
        const scrollPos = window.scrollY + 100;
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if(scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if(link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    });
    
    // Плавная прокрутка
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if(href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                
                if(target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // Валидация телефона
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if(value.length > 0) {
                if(value.length === 1) value = '+7' + value;
                else if(value.length > 1) value = '+7' + value.substring(1);
                
                if(value.length > 2 && value.length <= 5) {
                    value = value.substring(0, 2) + ' (' + value.substring(2);
                } else if(value.length > 5 && value.length <= 8) {
                    value = value.substring(0, 6) + ') ' + value.substring(6);
                } else if(value.length > 8 && value.length <= 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10);
                } else if(value.length > 10) {
                    value = value.substring(0, 13) + '-' + value.substring(13);
                }
            }
            
            e.target.value = value;
        });
    });
});