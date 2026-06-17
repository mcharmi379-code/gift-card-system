const pageSelector = '[data-ictech-gift-card-page]';

function buildPayload(page) {
    const payload = {};

    page.querySelectorAll('[data-gift-card-payload-field]').forEach((field) => {
        if (!field.name) return;
        if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) return;

        // Skip fields that are currently disabled/hidden under dynamic delivery logic
        const deliveryMethodInput = page.querySelector('input[name="giftCardDeliveryMethod"]:checked');
        const isPrint = deliveryMethodInput && deliveryMethodInput.value === 'print';
        if (isPrint && (field.name === 'giftCardEmail' || field.name === 'giftCardSendDate')) {
            return;
        }

        payload[field.name] = field.value;
    });

    const templateInput = page.querySelector('[data-gift-card-template-id]');
    payload.giftCardTemplateId = templateInput ? templateInput.value : '';

    return payload;
}

/**
 * Sync visible form field values into the single hidden payload input
 * as a JSON-encoded string to match Shopware's expected structure.
 */
function updatePayload(page) {
    const payload = buildPayload(page);

    // Add amount explicitly to payload if present
    const amountSelect = page.querySelector('[data-gift-card-amount]');
    if (amountSelect) {
        payload.giftCardAmount = amountSelect.value;
    }

    const payloadInput = page.querySelector('[data-gift-card-payload]');
    if (payloadInput) {
        payloadInput.value = JSON.stringify(payload);
    }
}

function updateAmount(page, select) {
    const selected = select.options[select.selectedIndex];
    const productId = selected ? selected.dataset.productId : '';

    page.querySelectorAll('[data-gift-card-product-id], [data-gift-card-referenced-id]').forEach((input) => {
        input.value = productId;
    });

    updatePayload(page);
}

function selectTemplate(page, button) {
    page.querySelectorAll('[data-gift-card-template]').forEach((item) => item.classList.remove('is-selected'));
    button.classList.add('is-selected');

    const templateId = button.dataset.giftCardTemplate || '';

    const templateInput = page.querySelector('[data-gift-card-template-id]');
    if (templateInput) {
        templateInput.value = templateId;
    }

    updatePayload(page);
}

function filterTemplates(page, filter) {
    page.querySelectorAll('[data-gift-card-filter]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.giftCardFilter === filter);
    });

    page.querySelectorAll('[data-gift-card-template]').forEach((template) => {
        template.hidden = filter !== 'all' && template.dataset.giftCardTag !== filter;
    });
}

function enforceMinDate(page) {
    const dateInput = page.querySelector('input[name="giftCardSendDate"]');
    if (!dateInput) return;

    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;

    dateInput.addEventListener('change', () => {
        if (dateInput.value && dateInput.value < today) {
            dateInput.value = today;
        }
    });
}

function initDeliveryToggle(page) {
    const emailFields = page.querySelector('[data-gift-card-email-fields]');
    if (!emailFields) return;

    const emailInput = page.querySelector('input[name="giftCardEmail"]');
    const dateInput = page.querySelector('input[name="giftCardSendDate"]');

    function toggle() {
        const selected = page.querySelector('input[name="giftCardDeliveryMethod"]:checked');
        const method = selected ? selected.value : 'email';
        
        if (method === 'email') {
            emailFields.hidden = false;
            if (emailInput) emailInput.required = true;
            if (dateInput) dateInput.required = true;
        } else {
            emailFields.hidden = true;
            if (emailInput) emailInput.required = false;
            if (dateInput) dateInput.required = false;
        }

        updatePayload(page);
    }

    page.querySelectorAll('input[name="giftCardDeliveryMethod"]').forEach((radio) => {
        radio.addEventListener('change', toggle);
    });

    toggle();
}

function openPreview(page, previewUrl) {
    const form = page.querySelector('form');
    if (form && !form.reportValidity()) {
        return; // Block opening the preview if form contains validation errors
    }

    const payload = buildPayload(page);

    const amountSelect = page.querySelector('[data-gift-card-amount]');
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

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll(pageSelector).forEach((page) => {
        const firstTemplate = page.querySelector('[data-gift-card-template]');
        const amountSelect = page.querySelector('[data-gift-card-amount]');

        if (firstTemplate) selectTemplate(page, firstTemplate);
        if (amountSelect) {
            updateAmount(page, amountSelect);
            amountSelect.addEventListener('change', () => updateAmount(page, amountSelect));
        }

        page.querySelectorAll('[data-gift-card-payload-field]').forEach((field) => {
            field.addEventListener('input', () => updatePayload(page));
            field.addEventListener('change', () => updatePayload(page));
        });

        page.querySelectorAll('[data-gift-card-template]').forEach((button) => {
            button.addEventListener('click', () => selectTemplate(page, button));
        });

        page.querySelectorAll('[data-gift-card-filter]').forEach((button) => {
            button.addEventListener('click', () => filterTemplates(page, button.dataset.giftCardFilter));
        });

        enforceMinDate(page);
        initDeliveryToggle(page);

        const previewBtn = page.querySelector('[data-gift-card-preview]');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => openPreview(page, previewBtn.dataset.previewUrl));
        }

        // Do initial sync
        updatePayload(page);
    });
});
