import template from './ictech-gift-card-settings.html.twig';

const { Mixin } = Shopware;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
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
            ],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.$refs.systemConfig.saveAll().then(() => {
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.settings.messageSaveSuccess'),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('ictech-gift-card.settings.messageSaveError'),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
        },

        onPreviewPdf() {
            this.isPreviewing = true;
            const salesChannelId = this.$refs.systemConfig?.currentSalesChannelId || null;
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
};
