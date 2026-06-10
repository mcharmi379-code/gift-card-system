import template from './ictech-gift-card-detail.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('giftCard'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('ictech_gift_card.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        giftCardId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            giftCard: null,
            isLoading: false,
            isSaveSuccessful: false,
            selectedTemplate: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPageErrors({}),

        identifier() {
            return this.placeholder(this.giftCard, 'name');
        },

        giftCardRepository() {
            return this.repositoryFactory.create('ictech_gift_card');
        },

        giftCardCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('template.media');
            return criteria;
        },

        templateRepository() {
            return this.repositoryFactory.create('ictech_gift_card_template');
        },

        templateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('media');
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        templateMediaUrl() {
            return this.selectedTemplate?.media?.url ?? null;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            return new Criteria(1, 25)
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        isCreateMode() {
            return this.$route.name === 'ictech.gift.card.create';
        },

        tooltipSave() {
            if (!this.acl.can('ictech_gift_card.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: false,
                    showOnDisabledElements: true,
                };
            }
            return { message: '', disabled: true };
        },

        tooltipCancel() {
            return { message: 'ESC', appearance: 'light' };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.isCreateMode) {
                this.giftCard = this.giftCardRepository.create();
                this.giftCard.active = true;
                this.giftCard.validityDays = 365;
                this.giftCard.quantityIssued = 0;
                return;
            }

            this.loadGiftCard();
        },

        async loadGiftCard() {
            this.isLoading = true;
            this.giftCard = await this.giftCardRepository.get(this.giftCardId, Shopware.Context.api, this.giftCardCriteria);
            if (this.giftCard.templateId) {
                this.selectedTemplate = this.giftCard.template ?? await this.templateRepository.get(this.giftCard.templateId, Shopware.Context.api, new Criteria().addAssociation('media'));
            }
            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            this.isLoading = true;

            try {
                await this.giftCardRepository.save(this.giftCard);
                this.isSaveSuccessful = true;

                if (this.isCreateMode) {
                    this.$router.push({
                        name: 'ictech.gift.card.detail',
                        params: { id: this.giftCard.id },
                    });
                }

                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.detail.messageSaveSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$tc('ictech-gift-card.detail.messageSaveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onTemplateChange(templateId) {
            this.giftCard.templateId = templateId || null;
            if (!templateId) {
                this.selectedTemplate = null;
                return;
            }
            const criteria = new Criteria();
            criteria.addAssociation('media');
            this.selectedTemplate = await this.templateRepository.get(templateId, Shopware.Context.api, criteria);
        },

        onCancel() {
            this.$router.push({ name: 'ictech.gift.card.index' });
        },
    },
};
