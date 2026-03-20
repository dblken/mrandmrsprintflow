/**
 * Product Form Validation - Real-time validation for Add/Edit Product modal
 * PrintFlow - Admin Products Management
 */
(function() {
    const ERRORS = {
        nameRequired: 'Product name is required.',
        nameMinLength: 'Product name must be at least 2 characters.',
        nameMaxLength: 'Product name must not exceed 100 characters.',
        nameOnlyNumbers: 'Product name cannot contain only numbers.',
        nameLeadingSpace: 'Leading spaces are not allowed.',
        categoryRequired: 'Please select a category.',
        priceRequired: 'Price is required.',
        priceInvalid: 'Price must be a valid number.',
        priceMin: 'Price must be greater than 0.',
        priceRange: 'Price must be between ₱1.00 and ₱1,000,000.00.',
        descriptionMax: 'Description must not exceed 500 characters.',
        photoRequired: 'Product photo is required.',
        photoType: 'Invalid file type. Only JPG, PNG and GIF are allowed.',
        photoSize: 'File size must not exceed 5MB.',
        quantityRequired: 'Quantity is required.',
        quantityWhole: 'Quantity must be a whole number.',
        quantityNegative: 'Quantity must be a non-negative number.',
        lowStockExceed: 'Low stock level cannot exceed quantity.',
        lowStockWhole: 'Low stock level must be a whole number.',
        lowStockNegative: 'Low stock level must be a non-negative number.'
    };

    const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5MB

    let touchedFields = new Set();

    function el(id) { return document.getElementById(id); }
    function showError(fieldId, msg) {
        const mid = fieldId === 'low-stock' ? 'modal-low-stock' : 'modal-' + fieldId;
        const err = el('err-' + fieldId);
        const group = el(mid)?.closest('.form-group');
        if (err) { 
            const isTouched = touchedFields.has(fieldId);
            err.textContent = msg || ''; 
            err.style.display = (msg && isTouched) ? 'block' : 'none'; 
        }
        if (group) {
            const isTouched = touchedFields.has(fieldId);
            group.classList.toggle('has-error', !!msg && isTouched);
            group.classList.toggle('has-success', !msg && isTouched && el('modal-' + fieldId)?.value?.trim());
        }
    }
    function getVal(fieldId) { return (el('modal-' + fieldId)?.value || '').trim(); }
    function getPhoto() { return el('modal-photo'); }
    function isCreateMode() { return el('modal-mode-input')?.name === 'create_product'; }
    function hasExistingPhoto() { return el('photo-preview-img')?.style?.display === 'block'; }

    function formatProductName(val) {
        if (!val) return '';
        // Collapse multiple spaces to one
        val = val.replace(/\s+/g, ' ');
        // If it still starts with space (should be handled by input listener but for safety)
        if (val.startsWith(' ')) val = val.trimStart();
        return val.replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function validateName() {
        const raw = (el('modal-name')?.value || '');
        const val = raw.trim();
        if (val.startsWith(' ') || raw !== val && raw.startsWith(' ')) return ERRORS.nameLeadingSpace;
        if (!val) return ERRORS.nameRequired;
        if (val.length < 2) return ERRORS.nameMinLength;
        if (val.length > 100) return ERRORS.nameMaxLength;
        if (/^\d+$/.test(val)) return ERRORS.nameOnlyNumbers;
        return '';
    }

    function validateCategory() {
        const v = getVal('category');
        if (!v || v === '-- Select Category --') return ERRORS.categoryRequired;
        return '';
    }

    function validatePrice() {
        const v = getVal('price');
        if (!v) return ERRORS.priceRequired;
        const num = parseFloat(v);
        if (isNaN(num)) return ERRORS.priceInvalid;
        if (num < 1) return ERRORS.priceMin;
        if (num > 1000000) return ERRORS.priceRange;
        const dec = v.split('.')[1];
        if (dec && dec.length > 2) return ERRORS.priceRange;
        return '';
    }

    function validateDescription() {
        const v = (el('modal-description')?.value || '').trim();
        if (v.length > 500) return ERRORS.descriptionMax;
        return '';
    }

    function validatePhoto() {
        if (!isCreateMode() && hasExistingPhoto()) return '';
        const file = getPhoto()?.files?.[0];
        if (!file) return ERRORS.photoRequired;
        if (!ALLOWED_PHOTO_TYPES.includes(file.type)) return ERRORS.photoType;
        if (file.size > MAX_PHOTO_SIZE) return ERRORS.photoSize;
        return '';
    }

    function validateQuantity() {
        const v = getVal('stock');
        if (!v && v !== '0') return ERRORS.quantityRequired;
        const num = parseFloat(v);
        if (isNaN(num) || num < 0) return ERRORS.quantityNegative;
        if (num !== Math.floor(num)) return ERRORS.quantityWhole;
        return '';
    }

    function validateLowStock() {
        const v = getVal('low-stock');
        const qty = parseInt((el('modal-stock')?.value || '0'), 10);
        const num = parseFloat(v);
        if (isNaN(num) || num < 0) return ERRORS.lowStockNegative;
        if (num !== Math.floor(num)) return ERRORS.lowStockWhole;
        if (num > qty) return ERRORS.lowStockExceed;
        return '';
    }

    function runValidation() {
        const errors = {
            name: validateName(),
            category: validateCategory(),
            price: validatePrice(),
            description: validateDescription(),
            photo: validatePhoto(),
            stock: validateQuantity(),
            'low-stock': validateLowStock()
        };
        Object.keys(errors).forEach(function(k) { showError(k, errors[k]); });
        const valid = Object.values(errors).every(function(e) { return !e; });
        const btn = el('modal-submit-btn');
        if (btn) btn.disabled = !valid;
        return valid;
    }

    function setupProductNameInput() {
        const inp = el('modal-name');
        if (!inp) return;
        inp.addEventListener('input', function() {
            let v = this.value;
            // Block leading space
            if (v.startsWith(' ')) v = v.trimStart();
            // Collapse multiple spaces
            v = v.replace(/\s+/g, ' ');
            
            v = formatProductName(v);
            if (v !== this.value) {
                this.value = v;
            }
            runValidation();
        });
        inp.addEventListener('keydown', function(e) {
            if (e.key === ' ' && (this.selectionStart === 0 || this.value.trim() === '' && this.value === ' ')) {
                e.preventDefault();
            }
        });
        inp.addEventListener('blur', function() {
            this.value = formatProductName(this.value);
            runValidation();
        });
    }

    function setupValidation() {
        ['modal-name', 'modal-category', 'modal-price', 'modal-description', 'modal-stock', 'modal-low-stock'].forEach(function(id) {
            const elm = el(id);
            if (elm) {
                elm.addEventListener('input', runValidation);
                elm.addEventListener('change', function() {
                    touchedFields.add(id.replace('modal-', ''));
                    runValidation();
                });
                elm.addEventListener('blur', function() {
                    touchedFields.add(id.replace('modal-', ''));
                    runValidation();
                });
            }
        });
        const photoInput = getPhoto();
        if (photoInput) photoInput.addEventListener('change', runValidation);
        setupProductNameInput();
    }

    function initProductFormValidation() {
        const form = document.getElementById('product-form');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            // Mark all as touched on submit
            ['name', 'category', 'price', 'description', 'photo', 'stock', 'low-stock'].forEach(k => touchedFields.add(k));
            if (!runValidation()) e.preventDefault();
        });
        setupValidation();
        runValidation();

        var origOpen = window.openProductModal;
        if (origOpen) {
            window.openProductModal = function(mode, product) {
                origOpen(mode, product);
                setTimeout(function() {
                    touchedFields.clear();
                    ['name', 'category', 'price', 'description', 'photo', 'stock', 'low-stock'].forEach(function(k) {
                        showError(k, '');
                    });
                    runValidation();
                }, 50);
            };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProductFormValidation);
    } else {
        initProductFormValidation();
    }
})();
