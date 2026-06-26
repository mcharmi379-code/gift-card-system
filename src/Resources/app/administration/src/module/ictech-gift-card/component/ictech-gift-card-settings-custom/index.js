import template from './ictech-gift-card-settings-custom.html.twig';

const { Component, Mixin } = Shopware;

Component.register('ictech-gift-card-settings-custom', {
    template,

    compatConfig: Shopware.compatConfig,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isPreviewing: false,
            pdfVariables: [
                { key: '{{card_lastname}}', label: this.$tc('ictech-gift-card.settings.variables.cardLastname') },
                { key: '{{card_firstname}}', label: this.$tc('ictech-gift-card.settings.variables.cardFirstname') },
                { key: '{{card_price}}', label: this.$tc('ictech-gift-card.settings.variables.cardPrice') },
                { key: '{{card_from}}', label: this.$tc('ictech-gift-card.settings.variables.cardFrom') },
                { key: '{{card_code}}', label: this.$tc('ictech-gift-card.settings.variables.cardCode') },
                { key: '{{card_message}}', label: this.$tc('ictech-gift-card.settings.variables.cardMessage') },
                { key: '{{card_image}}', label: this.$tc('ictech-gift-card.settings.variables.cardImage') },
                { key: '{{shop_name}}', label: this.$tc('ictech-gift-card.settings.variables.shopName') },
                { key: '{{validity_date}}', label: this.$tc('ictech-gift-card.settings.variables.validityDate') },
                { key: '{{shop_url}}', label: this.$tc('ictech-gift-card.settings.variables.shopUrl') },
            ],
        };
    },

    computed: {
        systemConfigPage() {
            let parent = this.$parent;
            while (parent) {
                if (parent.$options.name === 'sw-system-config') {
                    return parent;
                }
                parent = parent.$parent;
            }
            return null;
        },
        currentSalesChannelId() {
            return this.systemConfigPage?.currentSalesChannelId || null;
        }
    },

    methods: {
        onPreviewPdf() {
            this.isPreviewing = true;
            const salesChannelId = this.currentSalesChannelId;
            const query = salesChannelId ? `?salesChannelId=${salesChannelId}` : '';
            const { apiPath, authToken } = Shopware.Context.api;
            const url = `${apiPath}/ictech-gift-card/preview-pdf${query}`;

            fetch(url, { headers: { Authorization: `Bearer ${authToken?.access}` } })
                .then((res) => {
                    if (!res.ok) throw new Error('Failed to generate PDF');
                    return res.blob();
                })
                .then((blob) => {
                    window.open(URL.createObjectURL(blob), '_blank');
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('ictech-gift-card.settings.messagePdfError'),
                    });
                })
                .finally(() => {
                    this.isPreviewing = false;
                });
        },
    },
});
