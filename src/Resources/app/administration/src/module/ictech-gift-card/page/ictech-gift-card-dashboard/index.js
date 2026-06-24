import template from './ictech-gift-card-dashboard.html.twig';
import './ictech-gift-card-dashboard.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            stats: null,
            isLoadingStats: true,

            purchased: null,
            purchasedTotal: 0,
            purchasedPage: 1,
            purchasedLimit: 25,
            purchasedStatus: null,
            purchasedDateFrom: null,
            purchasedDateTo: null,
            isLoadingPurchased: true,

            used: null,
            usedTotal: 0,
            usedPage: 1,
            usedLimit: 25,
            usedDateFrom: null,
            usedDateTo: null,
            isLoadingUsed: true,
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        voucherRepository() {
            return this.repositoryFactory.create('ictech_gift_card_voucher');
        },

        transactionRepository() {
            return this.repositoryFactory.create('ictech_gift_card_transaction');
        },

        purchasedCriteria() {
            const criteria = new Criteria(this.purchasedPage, this.purchasedLimit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.addAssociation('order');
            criteria.addAssociation('currency');

            if (this.purchasedStatus) {
                criteria.addFilter(Criteria.equals('status', this.purchasedStatus));
            }
            if (this.purchasedDateFrom) {
                criteria.addFilter(Criteria.range('createdAt', { gte: this.purchasedDateFrom }));
            }
            if (this.purchasedDateTo) {
                criteria.addFilter(Criteria.range('createdAt', { lte: this.purchasedDateTo + 'T23:59:59' }));
            }
            return criteria;
        },

        usedCriteria() {
            const criteria = new Criteria(this.usedPage, this.usedLimit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', true));
            criteria.addAssociation('voucher');
            criteria.addAssociation('order');
            criteria.addAssociation('customer');

            if (this.usedDateFrom) {
                criteria.addFilter(Criteria.range('createdAt', { gte: this.usedDateFrom }));
            }
            if (this.usedDateTo) {
                criteria.addFilter(Criteria.range('createdAt', { lte: this.usedDateTo + 'T23:59:59' }));
            }
            return criteria;
        },

        purchasedColumns() {
            return [
                { property: 'code', label: 'ictech-gift-card.dashboard.columnCode', allowResize: true },
                { property: 'originalAmount', label: 'ictech-gift-card.dashboard.columnAmount', allowResize: true, align: 'right' },
                { property: 'remainingBalance', label: 'ictech-gift-card.dashboard.columnBalance', allowResize: true, align: 'right' },
                { property: 'status', label: 'ictech-gift-card.dashboard.columnStatus', allowResize: true },
                { property: 'recipientName', label: 'ictech-gift-card.dashboard.columnRecipient', allowResize: true },
                { property: 'order.orderNumber', label: 'ictech-gift-card.dashboard.columnOrder', allowResize: true, sortable: false },
                { property: 'createdAt', label: 'ictech-gift-card.dashboard.columnDate', allowResize: true },
            ];
        },

        usedColumns() {
            return [
                { property: 'voucher.code', label: 'ictech-gift-card.dashboard.columnCode', allowResize: true, sortable: false },
                { property: 'amountUsed', label: 'ictech-gift-card.dashboard.columnAmountUsed', allowResize: true, align: 'right' },
                { property: 'balanceBefore', label: 'ictech-gift-card.dashboard.columnBalanceBefore', allowResize: true, align: 'right' },
                { property: 'balanceAfter', label: 'ictech-gift-card.dashboard.columnBalanceAfter', allowResize: true, align: 'right' },
                { property: 'order.orderNumber', label: 'ictech-gift-card.dashboard.columnOrder', allowResize: true, sortable: false },
                { property: 'createdAt', label: 'ictech-gift-card.dashboard.columnDate', allowResize: true },
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
        this.loadAll();
    },

    methods: {
        loadAll() {
            this.loadStats();
            this.loadPurchased();
            this.loadUsed();
        },

        async loadStats() {
            this.isLoadingStats = true;
            try {
                const token = Shopware.Service('loginService').getToken();
                const response = await fetch(
                    `${Shopware.Context.api.apiPath}/ictech-gift-card/dashboard/stats`,
                    { headers: { Authorization: `Bearer ${token}` } }
                );
                this.stats = await response.json();
            } catch {
                this.stats = null;
            } finally {
                this.isLoadingStats = false;
            }
        },

        async loadPurchased() {
            this.isLoadingPurchased = true;
            try {
                const result = await this.voucherRepository.search(this.purchasedCriteria, Shopware.Context.api);
                this.purchased = result;
                this.purchasedTotal = result.total;
            } finally {
                this.isLoadingPurchased = false;
            }
        },

        async loadUsed() {
            this.isLoadingUsed = true;
            try {
                const result = await this.transactionRepository.search(this.usedCriteria, Shopware.Context.api);
                this.used = result;
                this.usedTotal = result.total;
            } finally {
                this.isLoadingUsed = false;
            }
        },

        onPurchasedPageChange({ page, limit }) {
            this.purchasedPage = page;
            this.purchasedLimit = limit;
            this.loadPurchased();
        },

        onUsedPageChange({ page, limit }) {
            this.usedPage = page;
            this.usedLimit = limit;
            this.loadUsed();
        },

        onPurchasedFilterChange() {
            this.purchasedPage = 1;
            this.loadPurchased();
        },

        onUsedFilterChange() {
            this.usedPage = 1;
            this.loadUsed();
        },

        exportCsv(endpoint, filename, extraParams = {}) {
            const token = Shopware.Service('loginService').getToken();
            const params = new URLSearchParams(
                Object.fromEntries(Object.entries(extraParams).filter(([, v]) => v != null))
            );
            const url = `${Shopware.Context.api.apiPath}/ictech-gift-card/dashboard/${endpoint}?${params}`;

            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', filename);

            // Shopware admin API requires auth; open in same window to pass auth header via fetch + blob
            fetch(url, { headers: { Authorization: `Bearer ${token}` } })
                .then((r) => r.blob())
                .then((blob) => {
                    link.href = URL.createObjectURL(blob);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                });
        },

        exportPurchased() {
            this.exportCsv('purchased-export', 'gift-cards-purchased.csv', {
                status: this.purchasedStatus,
                dateFrom: this.purchasedDateFrom,
                dateTo: this.purchasedDateTo,
            });
        },

        exportUsed() {
            this.exportCsv('used-export', 'gift-cards-used.csv', {
                dateFrom: this.usedDateFrom,
                dateTo: this.usedDateTo,
            });
        },

        getStatusVariant(status) {
            return { waiting_valid_order: 'warning', unused: 'success', partially_used: 'info', used: 'info', canceled: 'neutral' }[status] ?? 'neutral';
        },

        getStatusLabel(status) {
            const map = {
                waiting_valid_order: 'waitingValidOrder',
                unused: 'unused',
                partially_used: 'partiallyUsed',
                used: 'used',
                canceled: 'canceled',
            };
            return this.$tc(`ictech-gift-card.order.status.${map[status] ?? status}`);
        },

        formatAmount(amount) {
            return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount ?? 0);
        },

        formatDate(date) {
            if (!date) return '—';
            return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(date));
        },

        openOrder(item) {
            const orderId = item.orderId || (item.order ? item.order.id : null);
            if (!orderId) {
                this.createNotificationInfo({
                    message: this.$tc('ictech-gift-card.order.list.noOrderLinked'),
                });
                return;
            }
            this.$router.push({ name: 'sw.order.detail', params: { id: orderId } });
        },
    },
};
