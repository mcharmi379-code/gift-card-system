import template from './ictech-gift-card-template-detail.html.twig';
import './ictech-gift-card-template-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

const DEFAULT_CUSTOMIZE_CONFIG = {
    price: '100',
    discountCode: 'XXXXXXXXXX',
    textOne: 'PrestaShop',
    textTwo: 'HAPPY',
    textThree: 'BIRTHDAY',
    colorOne: '#ff6a3d',
};

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('templateEntity'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        templateId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            templateEntity: null,
            isLoading: false,
            isSaveSuccessful: false,
            activeTab: 'information',
            customize: { ...DEFAULT_CUSTOMIZE_CONFIG },
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        templateRepository() {
            return this.repositoryFactory.create('ictech_gift_card_template');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        templateCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('media');

            return criteria;
        },

        isCreateMode() {
            return this.$route.name === 'ictech.gift.card.templateCreate';
        },

        identifier() {
            return this.placeholder(this.templateEntity, 'name', this.$tc('ictech-gift-card.template.detail.titleEdit'));
        },

        mediaUploadTag() {
            return `ictech-gift-card-template-detail--${this.templateEntity?.id}`;
        },

        previewImageUrl() {
            return this.templateEntity?.media?.url || '';
        },

        tooltipSave() {
            if (this.acl.can('ictech_gift_card.editor')) {
                return {
                    message: `${this.$device.getSystemKey()} + S`,
                    appearance: 'light',
                };
            }

            return {
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: false,
                showOnDisabledElements: true,
            };
        },

        tooltipCancel() {
            return { message: 'ESC', appearance: 'light' };
        },

        ...mapPropertyErrors('templateEntity', ['name']),
    },

    created() {
        this.createdComponent();
    },

    watch: {
        templateId() {
            this.createdComponent();
        },
    },

    methods: {
        createdComponent() {
            if (this.isCreateMode) {
                this.templateEntity = this.templateRepository.create();
                this.templateEntity.active = true;
                this.templateEntity.tag = '';
                this.templateEntity.customFields = {};
                this.customize = { ...DEFAULT_CUSTOMIZE_CONFIG };
                return;
            }

            this.loadTemplate();
        },

        async loadTemplate() {
            this.isLoading = true;

            this.templateEntity = await this.templateRepository.get(
                this.templateId,
                Shopware.Context.api,
                this.templateCriteria,
            );

            this.customize = {
                ...DEFAULT_CUSTOMIZE_CONFIG,
                ...(this.templateEntity.customFields?.giftCardTemplateCustomize || {}),
            };

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            if (!this.acl.can('ictech_gift_card.editor')) {
                return;
            }

            this.isLoading = true;

            try {
                this.templateEntity.customFields = {
                    ...(this.templateEntity.customFields || {}),
                    giftCardTemplateCustomize: { ...this.customize },
                };

                await this.templateRepository.save(this.templateEntity);
                this.isSaveSuccessful = true;

                if (this.isCreateMode) {
                    await this.$router.push({
                        name: 'ictech.gift.card.templateDetail',
                        params: { id: this.templateEntity.id },
                    });
                }

                await this.loadTemplate();

                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.template.detail.messageSaveSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$tc('ictech-gift-card.template.detail.messageSaveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCancel() {
            this.$router.push({ name: 'ictech.gift.card.template' });
        },

        async setMediaItem({ targetId }) {
            this.templateEntity.mediaId = targetId;
            this.templateEntity.media = await this.mediaRepository.get(targetId);
        },

        setMediaFromSidebar(media) {
            this.templateEntity.mediaId = media.id;
            this.templateEntity.media = media;
        },

        onUnlinkMedia() {
            this.templateEntity.mediaId = null;
            this.templateEntity.media = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onDropMedia(dragData) {
            this.setMediaItem({ targetId: dragData.id });
        },
    },
};
