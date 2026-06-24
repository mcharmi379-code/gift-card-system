const pageSelector = '[data-ictech-gift-card-page]';

function generateUuid() {
    return 'xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function updateLineItemKey(page, key) {
    page.querySelectorAll('[data-gift-card-line-item-input]').forEach((input) => {
        const type = input.dataset.giftCardLineItemInput;
        input.name = `lineItems[${key}][${type}]`;
    });

    // Set the value of the line item ID input to the unique key itself
    const idInput = page.querySelector('[data-gift-card-product-id]');
    if (idInput) {
        idInput.value = key;
    }
}

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

    // Only update the referencedId with the product ID, leaving the line item ID as the unique key
    const referencedIdInput = page.querySelector('[data-gift-card-referenced-id]');
    if (referencedIdInput) {
        referencedIdInput.value = productId;
    }

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

    const templateError = page.querySelector('[data-gift-card-template-error]');
    if (templateError) {
        templateError.classList.remove('d-block');
        templateError.classList.add('d-none');
    }
    const grid = page.querySelector('.ictech-gift-card-template-grid');
    if (grid) {
        grid.classList.remove('is-invalid');
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

        updatePayload(page);
    }

    page.querySelectorAll('input[name="giftCardDeliveryMethod"]').forEach((radio) => {
        radio.addEventListener('change', toggle);
    });

    toggle();
}

function openPreview(page, previewUrl) {
    const form = page.querySelector('form');
    const templateInput = page.querySelector('[data-gift-card-template-id]');
    const isTemplateValid = templateInput && templateInput.value;
    const templateError = page.querySelector('[data-gift-card-template-error]');

    if (!isTemplateValid) {
        if (templateError) {
            templateError.classList.remove('d-none');
            templateError.classList.add('d-block');
        }
        const grid = page.querySelector('.ictech-gift-card-template-grid');
        if (grid) {
            grid.classList.add('is-invalid');
        }
    } else {
        if (templateError) {
            templateError.classList.remove('d-block');
            templateError.classList.add('d-none');
        }
        const grid = page.querySelector('.ictech-gift-card-template-grid');
        if (grid) {
            grid.classList.remove('is-invalid');
        }
    }

    if (form) {
        if (!form.reportValidity() || !isTemplateValid) {
            form.classList.add('was-validated');
            return; // Block opening the preview if form contains validation errors
        }
        form.classList.add('was-validated');
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
        const form = page.querySelector('form');
        const firstTemplate = page.querySelector('[data-gift-card-template]');
        const amountSelect = page.querySelector('[data-gift-card-amount]');

        // Initialize unique line item key (also sets value of product ID field to the key)
        updateLineItemKey(page, generateUuid());

        // Do not automatically select the first template, as template selection is now mandatory
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

        // Regenerate unique key after submission so next addition gets a new line item
        if (form) {
            form.addEventListener('submit', (event) => {
                const templateInput = page.querySelector('[data-gift-card-template-id]');
                const isTemplateValid = templateInput && templateInput.value;
                const templateError = page.querySelector('[data-gift-card-template-error]');

                if (!isTemplateValid) {
                    if (templateError) {
                        templateError.classList.remove('d-none');
                        templateError.classList.add('d-block');
                    }
                    const grid = page.querySelector('.ictech-gift-card-template-grid');
                    if (grid) {
                        grid.classList.add('is-invalid');
                    }
                } else {
                    if (templateError) {
                        templateError.classList.remove('d-block');
                        templateError.classList.add('d-none');
                    }
                    const grid = page.querySelector('.ictech-gift-card-template-grid');
                    if (grid) {
                        grid.classList.remove('is-invalid');
                    }
                }

                if (!form.checkValidity() || !isTemplateValid) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    form.classList.add('was-validated');
                }
            }, true);

            form.addEventListener('submit', () => {
                setTimeout(() => {
                    updateLineItemKey(page, generateUuid());
                    updatePayload(page);
                }, 100);
            });
        }

        // Do initial sync
        updatePayload(page);
    });
});
