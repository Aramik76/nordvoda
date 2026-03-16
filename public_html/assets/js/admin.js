// ==============================================
// admin.js - Скрипты админ-панели (исправленная версия с маской телефона)
// ==============================================
const ADMIN_SECRET_KEY = 'pos_xcz_wiL_!23';

document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. Маска для телефона во всех полях с type="tel" ---
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                // Если начинается с 8, заменяем на 7 (российский формат)
                if (value[0] === '8') {
                    value = '7' + value.substring(1);
                }
                // Добавляем +7 в начало
                if (value.length > 0) {
                    value = '+7' + value.substring(1);
                }
                
                // Форматирование +7(###)###-##-##
                let formatted = '';
                if (value.length > 2) {
                    formatted = value.substring(0, 2) + '(' + value.substring(2, 5);
                } else {
                    formatted = value;
                }
                if (value.length > 5) {
                    formatted += ')' + value.substring(5, 8);
                }
                if (value.length > 8) {
                    formatted += '-' + value.substring(8, 10);
                }
                if (value.length > 10) {
                    formatted += '-' + value.substring(10, 12);
                }
                e.target.value = formatted;
            } else {
                e.target.value = '';
            }
        });

        // Дополнительно: при потере фокуса можно проверить длину (опционально)
        input.addEventListener('blur', function() {
            let digits = this.value.replace(/\D/g, '');
            if (digits.length > 0 && digits.length !== 11) {
                // Можно подсветить ошибку, но пока просто предупреждение
                console.warn('Номер телефона должен содержать 11 цифр');
            }
        });
    });

    // --- 2. Подтверждение удаления ---
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите удалить этот элемент? Это действие нельзя отменить.')) {
                e.preventDefault();
            }
        });
    });

    // --- 3. Предпросмотр изображений перед загрузкой ---
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const preview = this.closest('form').querySelector('.image-preview');
            
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // --- 4. Валидация формы (required поля) ---
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                    
                    // Показываем ошибку
                    let errorDiv = field.nextElementSibling;
                    if (!errorDiv || !errorDiv.classList.contains('error-message')) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.textContent = 'Это поле обязательно для заполнения';
                        field.parentNode.insertBefore(errorDiv, field.nextSibling);
                    }
                } else {
                    field.classList.remove('error');
                    const errorDiv = field.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('error-message')) {
                        errorDiv.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // --- 5. Авто-генерация slug из title ---
    const titleInput = document.querySelector('input[name="title"]');
    const slugInput = document.querySelector('input[name="slug"]');
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('blur', function() {
            if (!slugInput.value.trim()) {
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                
                slugInput.value = slug;
            }
        });
    }

    // --- 6. Чекбокс "Выбрать все" ---
    const selectAllCheckbox = document.querySelector('.select-all');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.select-item');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    // --- 7. Таймер для уведомлений (скрытие через 5 сек) ---
    const alerts = document.querySelectorAll('.alert:not(.persistent)');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });

    // --- 8. Активное меню (подсветка текущего пункта) ---
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.admin-sidebar a');
    
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href) && href !== '') {
            link.classList.add('active');
        }
    });

    // --- 9. CKEditor для текстовых полей (если подключен) ---
    if (typeof CKEDITOR !== 'undefined') {
        document.querySelectorAll('textarea.editor').forEach(textarea => {
            CKEDITOR.replace(textarea, {
                language: 'ru',
                height: 300,
                toolbar: [
                    { name: 'document', items: ['Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates'] },
                    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
                    { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll', '-', 'Scayt'] },
                    { name: 'forms', items: ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'] },
                    '/',
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language'] },
                    { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
                    { name: 'insert', items: ['Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe'] },
                    '/',
                    { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
                    { name: 'colors', items: ['TextColor', 'BGColor'] },
                    { name: 'tools', items: ['Maximize', 'ShowBlocks'] },
                    { name: 'about', items: ['About'] }
                ]
            });
        });
    }
});