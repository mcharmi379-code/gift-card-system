import template from './ictech-gift-card-list.html.twig';
import './ictech-gift-card-list.scss';

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
            giftCards: null,
            isLoading: true,
            total: 0,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            showDeleteModal: false,
            deleteId: null,
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        giftCardRepository() {
            return this.repositoryFactory.create('ictech_gift_card');
        },

        giftCardCriteria() {
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
                    label: 'ictech-gift-card.list.columnMedia',
                    allowResize: false,
                    sortable: false,
                    width: '80px',
                },
                {
                    property: 'name',
                    label: 'ictech-gift-card.list.columnName',
                    routerLink: 'ictech.gift.card.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'formattedAmount',
                    label: 'ictech-gift-card.list.columnAmount',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'codePrefix',
                    label: 'ictech-gift-card.list.columnCodePrefix',
                    allowResize: true,
                },
                {
                    property: 'validityDays',
                    label: 'ictech-gift-card.list.columnValidityDays',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'quantityIssued',
                    label: 'ictech-gift-card.list.columnQuantityIssued',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'active',
                    label: 'ictech-gift-card.list.columnActive',
                    inlineEdit: 'boolean',
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

            const result = await this.giftCardRepository.search(this.giftCardCriteria);
            this.giftCards = result;
            this.total = result.total;
            this.isLoading = false;
        },

        formatAmount(amount) {
            const numericAmount = Number(amount);

            if (!Number.isFinite(numericAmount)) {
                return '';
            }

            return numericAmount.toFixed(2);
        },

        updateTotal({ total }) {
            this.total = total;
        },

        onDelete(id) {
            this.deleteId = id;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.deleteId = null;
        },

        async onConfirmDelete() {
            this.showDeleteModal = false;

            await this.giftCardRepository.delete(this.deleteId);
            this.deleteId = null;
            this.getList();
        },
    },
};
