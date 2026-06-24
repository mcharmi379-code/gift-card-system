import template from './ictech-gift-card-order-list.html.twig';
import './ictech-gift-card-order-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            vouchers: null,
            isLoading: true,
            total: 0,
            page: 1,
            limit: 25,
            term: '',
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            filterStatus: null,
            deleteItem: null,
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        voucherRepository() {
            return this.repositoryFactory.create('ictech_gift_card_voucher');
        },

        voucherCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.filterStatus) {
                criteria.addFilter(Criteria.equals('status', this.filterStatus));
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('giftCard.template.media');
            criteria.addAssociation('order');
            criteria.addAssociation('customer');
            criteria.addAssociation('currency');
            criteria.addAssociation('transactions.order');

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'order.orderNumber',
                    label: 'ictech-gift-card.order.list.columnOrder',
                    allowResize: true,
                    sortable: false,
                    width: '80px',
                },
                {
                    property: 'giftCard.template',
                    label: 'ictech-gift-card.order.list.columnTemplate',
                    allowResize: false,
                    sortable: false,
                    width: '100px',
                },
                {
                    property: 'originalAmount',
                    label: 'ictech-gift-card.order.list.columnPrice',
                    allowResize: true,
                    align: 'right',
                    width: '90px',
                },
                {
                    property: 'recipientEmail',
                    label: 'ictech-gift-card.order.list.columnRecipient',
                    allowResize: true,
                    width: '140px',
                },
                {
                    property: 'customer',
                    label: 'ictech-gift-card.order.list.columnCustomer',
                    allowResize: true,
                    sortable: false,
                    width: '130px',
                },
                {
                    property: 'code',
                    label: 'ictech-gift-card.order.list.columnCode',
                    allowResize: true,
                    width: '160px',
                },
                {
                    property: 'status',
                    label: 'ictech-gift-card.order.list.columnStatus',
                    allowResize: true,
                    width: '180px',
                },
                {
                    property: 'remainingBalance',
                    label: 'ictech-gift-card.order.list.columnBalance',
                    allowResize: true,
                    align: 'right',
                    width: '110px',
                },
                {
                    property: 'usedInOrders',
                    label: 'ictech-gift-card.order.list.columnUsedInOrders',
                    allowResize: true,
                    sortable: false,
                    width: '150px',
                },
                {
                    property: 'scheduledSendDate',
                    label: 'ictech-gift-card.order.list.columnScheduledSendDate',
                    allowResize: true,
                    width: '140px',
                },
                {
                    property: 'sentAt',
                    label: 'ictech-gift-card.order.list.columnSentAt',
                    allowResize: true,
                    width: '140px',
                },
                {
                    property: 'createdAt',
                    label: 'ictech-gift-card.order.list.columnDate',
                    allowResize: true,
                    width: '140px',
                },
            ];
        },

        statusOptions() {
            return [
                { value: null, label: this.$tc('ictech-gift-card.order.list.filterAll') },
                { value: 'waiting_valid_order', label: this.$tc('ictech-gift-card.order.status.waitingValidOrder') },
                { value: 'unused', label: this.$tc('ictech-gift-card.order.status.unused') },
                { value: 'partially_used', label: this.$tc('ictech-gift-card.order.status.partiallyUsed') },
                { value: 'used', label: this.$tc('ictech-gift-card.order.status.used') },
                { value: 'canceled', label: this.$tc('ictech-gift-card.order.status.canceled') },
            ];
        },
    },

    created() {
        this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;
            try {
                const result = await this.voucherRepository.search(this.voucherCriteria);
                this.vouchers = result;
                this.total = result.total;
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.getList();
        },

        onFilterStatus(status) {
            this.filterStatus = status;
            this.page = 1;
            this.getList();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }
            this.getList();
        },

        getStatusVariant(status) {
            const map = {
                waiting_valid_order: 'warning',
                unused: 'success',
                partially_used: 'info',
                used: 'info',
                canceled: 'neutral',
            };
            return map[status] ?? 'neutral';
        },

        getStatusLabel(status) {
            const key = `ictech-gift-card.order.status.${this.kebabToCamel(status)}`;
            return this.$tc(key);
        },

        getUsedLabel(voucher) {
            if (voucher.status === 'used' && voucher.usedInOrderNumber) {
                return this.$tc('ictech-gift-card.order.status.usedInOrder', 0, {
                    number: voucher.usedInOrderNumber,
                });
            }
            return this.getStatusLabel(voucher.status);
        },

        getCustomerName(voucher) {
            if (voucher.customer) {
                return `${voucher.customer.firstName} ${voucher.customer.lastName}`.trim();
            }
            return voucher.recipientName || '—';
        },

        formatAmount(amount, voucher) {
            if (!voucher.currency) {
                return `${Number(amount).toFixed(2)}`;
            }
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: voucher.currency.isoCode,
            }).format(amount);
        },

        openOrder(voucher) {
            if (!voucher.orderId) {
                this.createNotificationInfo({
                    message: this.$tc('ictech-gift-card.order.list.noOrderLinked'),
                });
                return;
            }
            this.$router.push({ name: 'sw.order.detail', params: { id: voucher.orderId } });
        },

        onDelete(voucher) {
            this.deleteItem = voucher;
        },

        onCloseDeleteModal() {
            this.deleteItem = null;
        },

        async onConfirmDelete() {
            const voucher = this.deleteItem;
            this.deleteItem = null;
            await this.voucherRepository.delete(voucher.id, Shopware.Context.api);
            this.createNotificationSuccess({
                message: this.$tc('ictech-gift-card.order.list.messageDeleted'),
            });
            this.getList();
        },

        formatDate(date) {
            if (!date) return '—';
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit',
            }).format(new Date(date));
        },

        formatDateOnly(date) {
            if (!date) return '—';
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric', month: '2-digit', day: '2-digit',
            }).format(new Date(date));
        },

        getUniqueUsedTransactions(voucher) {
            if (!voucher.transactions || voucher.transactions.length === 0) {
                return [];
            }

            const seenOrderNumbers = new Set();
            const uniqueTransactions = [];

            voucher.transactions.forEach(t => {
                if (t.order && t.order.orderNumber) {
                    if (!seenOrderNumbers.has(t.order.orderNumber)) {
                        seenOrderNumbers.add(t.order.orderNumber);
                        uniqueTransactions.push(t);
                    }
                }
            });

            return uniqueTransactions;
        },

        kebabToCamel(str) {
            return str.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
        },
    },
};
