import Plugin from 'src/plugin-system/plugin.class';

export default class GiftCardPlugin extends Plugin {
    init() {
        this.form = this.el.querySelector('form');
        this.firstTemplate = this.el.querySelector('[data-gift-card-template]');
        this.amountSelect = this.el.querySelector('[data-gift-card-amount]');

        // Initialize unique line item key (also sets value of product ID field to the key)
        this._updateLineItemKey(this._generateUuid());

        if (this.firstTemplate) {
            this._selectTemplate(this.firstTemplate);
        }

        if (this.amountSelect) {
            this._updateAmount(this.amountSelect);
            this.amountSelect.addEventListener('change', () => this._updateAmount(this.amountSelect));
        }

        this.el.querySelectorAll('[data-gift-card-payload-field]').forEach((field) => {
            field.addEventListener('input', () => this._updatePayload());
            field.addEventListener('change', () => this._updatePayload());
        });

        this.el.querySelectorAll('[data-gift-card-template]').forEach((button) => {
            button.addEventListener('click', () => this._selectTemplate(button));
        });

        this.el.querySelectorAll('[data-gift-card-filter]').forEach((button) => {
            button.addEventListener('click', () => this._filterTemplates(button.dataset.giftCardFilter));
        });

        this._enforceMinDate();
        this._initDeliveryToggle();

        const previewBtn = this.el.querySelector('[data-gift-card-preview]');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this._openPreview(previewBtn.dataset.previewUrl));
        }

        if (this.form) {
            this.form.addEventListener('submit', (event) => {
                if (!this.form.checkValidity()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    this.form.classList.add('was-validated');
                }
            }, true);

            this.form.addEventListener('submit', () => {
                setTimeout(() => {
                    this._updateLineItemKey(this._generateUuid());
                    this._updatePayload();
                }, 100);
            });
        }

        // Do initial sync
        this._updatePayload();
    }

    _generateUuid() {
        return 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    _updateLineItemKey(key) {
        this.el.querySelectorAll('[data-gift-card-line-item-input]').forEach((input) => {
            const type = input.dataset.giftCardLineItemInput;
            input.name = `lineItems[${key}][${type}]`;
        });

        // Set the value of the line item ID input to the unique key itself
        const idInput = this.el.querySelector('[data-gift-card-product-id]');
        if (idInput) {
            idInput.value = key;
        }
    }

    _buildPayload() {
        const payload = {};

        this.el.querySelectorAll('[data-gift-card-payload-field]').forEach((field) => {
            if (!field.name) return;
            if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) return;

            // Skip fields that are currently disabled/hidden under dynamic delivery logic
            const deliveryMethodInput = this.el.querySelector('input[name="giftCardDeliveryMethod"]:checked');
            const isPrint = deliveryMethodInput && deliveryMethodInput.value === 'print';
            if (isPrint && (field.name === 'giftCardEmail' || field.name === 'giftCardSendDate')) {
                return;
            }

            payload[field.name] = field.value;
        });

        const templateInput = this.el.querySelector('[data-gift-card-template-id]');
        payload.giftCardTemplateId = templateInput ? templateInput.value : '';

        return payload;
    }

    _updatePayload() {
        const payload = this._buildPayload();

        // Add amount explicitly to payload if present
        const amountSelect = this.el.querySelector('[data-gift-card-amount]');
        if (amountSelect) {
            payload.giftCardAmount = amountSelect.value;
        }

        const payloadInput = this.el.querySelector('[data-gift-card-payload]');
        if (payloadInput) {
            payloadInput.value = JSON.stringify(payload);
        }
    }

    _updateAmount(select) {
        const selected = select.options[select.selectedIndex];
        const productId = selected ? selected.dataset.productId : '';

        // Only update the referencedId with the product ID, leaving the line item ID as the unique key
        const referencedIdInput = this.el.querySelector('[data-gift-card-referenced-id]');
        if (referencedIdInput) {
            referencedIdInput.value = productId;
        }

        this._updatePayload();
    }

    _selectTemplate(button) {
        this.el.querySelectorAll('[data-gift-card-template]').forEach((item) => item.classList.remove('is-selected'));
        button.classList.add('is-selected');

        const templateId = button.dataset.giftCardTemplate || '';

        const templateInput = this.el.querySelector('[data-gift-card-template-id]');
        if (templateInput) {
            templateInput.value = templateId;
        }

        this._updatePayload();
    }

    _filterTemplates(filter) {
        this.el.querySelectorAll('[data-gift-card-filter]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.giftCardFilter === filter);
        });

        this.el.querySelectorAll('[data-gift-card-template]').forEach((template) => {
            template.hidden = filter !== 'all' && template.dataset.giftCardTag !== filter;
        });
    }

    _enforceMinDate() {
        const dateInput = this.el.querySelector('input[name="giftCardSendDate"]');
        if (!dateInput) return;

        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;

        dateInput.addEventListener('change', () => {
            if (dateInput.value && dateInput.value < today) {
                dateInput.value = today;
            }
        });
    }

    _initDeliveryToggle() {
        const emailFields = this.el.querySelector('[data-gift-card-email-fields]');
        if (!emailFields) return;

        const emailInput = this.el.querySelector('input[name="giftCardEmail"]');
        const dateInput = this.el.querySelector('input[name="giftCardSendDate"]');

        const toggle = () => {
            const selected = this.el.querySelector('input[name="giftCardDeliveryMethod"]:checked');
            const method = selected ? selected.value : 'email';

            if (method === 'email') {
                emailFields.hidden = false;
                if (emailInput) {
                    emailInput.required = true;
                    emailInput.disabled = false;
                }
                if (dateInput) {
                    dateInput.required = true;
                    dateInput.disabled = false;
                }
            } else {
                emailFields.hidden = true;
                if (emailInput) {
                    emailInput.required = false;
                    emailInput.disabled = true;
                    emailInput.value = '';
                    emailInput.classList.remove('is-invalid');
                }
                if (dateInput) {
                    dateInput.required = false;
                    dateInput.disabled = true;
                    dateInput.value = '';
                    dateInput.classList.remove('is-invalid');
                }
            }

            this._updatePayload();
        };

        this.el.querySelectorAll('input[name="giftCardDeliveryMethod"]').forEach((radio) => {
            radio.addEventListener('change', toggle);
        });

        toggle();
    }

    _openPreview(previewUrl) {
        if (this.form) {
            if (!this.form.reportValidity()) {
                this.form.classList.add('was-validated');
                return;
            }
            this.form.classList.add('was-validated');
        }

        const payload = this._buildPayload();

        const amountSelect = this.el.querySelector('[data-gift-card-amount]');
        if (amountSelect) {
            const selected = amountSelect.options[amountSelect.selectedIndex];
            payload.giftCardAmount = amountSelect.value;
            payload.giftCardId = selected ? selected.dataset.giftCardId : '';
        }

        // Build GET URL with query params
        const params = new URLSearchParams();
        Object.entries(payload).forEach(([key, value]) => {
            if (value !== '') params.set(key, value);
        });

        const url = previewUrl + '?' + params.toString();

        // Open small popup window
        const w = 700, h = 800;
        const left = Math.round((window.screen.width - w) / 2);
        const top  = Math.round((window.screen.height - h) / 2);
        window.open(url, 'gift-card-preview', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
    }
}
