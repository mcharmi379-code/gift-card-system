import template from './ictech-gift-card-template-list.html.twig';
import './ictech-gift-card-template-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            templates: null,
            isLoading: true,
            total: 0,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        templateRepository() {
            return this.repositoryFactory.create('ictech_gift_card_template');
        },

        templateCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('media');

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'media',
                    label: 'ictech-gift-card.template.list.columnImage',
                    allowResize: false,
                    sortable: false,
                    width: '96px',
                },
                {
                    property: 'name',
                    label: 'ictech-gift-card.template.list.columnName',
                    routerLink: 'ictech.gift.card.templateDetail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'tag',
                    label: 'ictech-gift-card.template.list.columnTag',
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: 'ictech-gift-card.template.list.columnActive',
                    allowResize: true,
                    align: 'center',
                },
            ];
        },

        addButtonTooltip() {
            return {
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('ictech_gift_card.creator'),
                showOnDisabledElements: true,
            };
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            const result = await this.templateRepository.search(this.templateCriteria);
            this.templates = result;
            this.total = result.total;
            this.isLoading = false;
        },

        updateTotal({ total }) {
            this.total = total;
        },

        onChangeLanguage() {
            this.getList();
        },
    },
};
