import template from './ictech-gift-card-moderation.html.twig';

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
            selectedVoucherId: null,
            voucher: null,
            isLoading: false,
            activeTab: 'resend',
            newBalance: 0,
            recipientEmail: '',
            reasonRevoke: '',
            reasonAdjust: '',
            isResending: false,
            isAdjusting: false,
            isRevoking: false,
            auditLogs: [],
            auditLogColumns: [
                {
                    property: 'createdAt',
                    label: this.$tc('ictech-gift-card.moderation.columnDate'),
                    allowResize: true,
                },
                {
                    property: 'action',
                    label: this.$tc('ictech-gift-card.moderation.columnAction'),
                    allowResize: true,
                },
                {
                    property: 'oldValue',
                    label: this.$tc('ictech-gift-card.moderation.columnOldValue'),
                    allowResize: true,
                },
                {
                    property: 'newValue',
                    label: this.$tc('ictech-gift-card.moderation.columnNewValue'),
                    allowResize: true,
                },
                {
                    property: 'reason',
                    label: this.$tc('ictech-gift-card.moderation.columnReason'),
                    allowResize: true,
                },
            ],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        voucherRepository() {
            return this.repositoryFactory.create('ictech_gift_card_voucher');
        },

        auditLogRepository() {
            return this.repositoryFactory.create('ictech_gift_card_audit_log');
        },

        isVoucherRevoked() {
            return this.voucher?.status === 'canceled';
        },

        resendDisabled() {
            return !this.voucher || this.isResending;
        },

        adjustDisabled() {
            return !this.voucher || this.isAdjusting || this.newBalance === null || this.newBalance < 0 || this.newBalance > this.voucher.originalAmount;
        },

        revokeDisabled() {
            return !this.voucher || this.isRevoking || this.isVoucherRevoked;
        },
    },

    methods: {
        async onVoucherChange(voucherId) {
            this.selectedVoucherId = voucherId;
            if (!voucherId) {
                this.voucher = null;
                this.newBalance = 0;
                this.recipientEmail = '';
                this.reasonRevoke = '';
                this.reasonAdjust = '';
                this.auditLogs = [];
                return;
            }

            this.isLoading = true;
            try {
                const criteria = new Criteria();
                criteria.addAssociation('currency');
                criteria.addAssociation('customer');
                criteria.addAssociation('order');

                this.voucher = await this.voucherRepository.get(voucherId, Shopware.Context.api, criteria);
                if (this.voucher) {
                    this.newBalance = this.voucher.remainingBalance;
                    this.recipientEmail = this.voucher.recipientEmail;
                }
                this.reasonRevoke = '';
                this.reasonAdjust = '';
                await this.loadAuditLogs();
            } catch (error) {
                this.createNotificationError({
                    message: 'Could not load voucher details.',
                });
                this.voucher = null;
            } finally {
                this.isLoading = false;
            }
        },

        async loadAuditLogs() {
            if (!this.selectedVoucherId) {
                this.auditLogs = [];
                return;
            }

            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('voucherId', this.selectedVoucherId));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            try {
                this.auditLogs = await this.auditLogRepository.search(criteria, Shopware.Context.api);
            } catch (error) {
                console.error('Failed to load audit logs:', error);
            }
        },

        async sendPostRequest(endpoint, body) {
            const { apiPath, authToken } = Shopware.Context.api;
            const url = `${apiPath}/ictech-gift-card/moderation/${endpoint}`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${authToken?.access}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.error || 'Request failed');
            }

            return response.json();
        },

        async onResendEmail() {
            if (this.resendDisabled) return;

            this.isResending = true;
            try {
                await this.sendPostRequest('resend', {
                    voucherId: this.selectedVoucherId,
                    recipientEmail: this.recipientEmail,
                });
                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.moderation.messageResendSuccess'),
                });
                await this.loadAuditLogs();
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$tc('ictech-gift-card.moderation.messageResendError'),
                });
            } finally {
                this.isResending = false;
            }
        },

        async onAdjustBalance() {
            if (this.adjustDisabled) return;

            this.isAdjusting = true;
            try {
                await this.sendPostRequest('adjust-balance', {
                    voucherId: this.selectedVoucherId,
                    newBalance: this.newBalance,
                    reason: this.reasonAdjust,
                });
                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.moderation.messageAdjustSuccess'),
                });
                await this.onVoucherChange(this.selectedVoucherId);
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$tc('ictech-gift-card.moderation.messageAdjustError'),
                });
            } finally {
                this.isAdjusting = false;
            }
        },

        async onRevokeVoucher() {
            if (this.revokeDisabled) return;

            this.isRevoking = true;
            try {
                await this.sendPostRequest('revoke', {
                    voucherId: this.selectedVoucherId,
                    reason: this.reasonRevoke,
                });
                this.createNotificationSuccess({
                    message: this.$tc('ictech-gift-card.moderation.messageRevokeSuccess'),
                });
                await this.onVoucherChange(this.selectedVoucherId);
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$tc('ictech-gift-card.moderation.messageRevokeError'),
                });
            } finally {
                this.isRevoking = false;
            }
        },

        formatAmount(amount) {
            if (!amount && amount !== 0) return '—';
            if (!this.voucher || !this.voucher.currency) {
                return `${Number(amount).toFixed(2)}`;
            }
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: this.voucher.currency.isoCode,
            }).format(amount);
        },

        formatDate(date) {
            if (!date) return '—';
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit',
            }).format(new Date(date));
        },

        getActionLabel(action) {
            const map = {
                revoke: this.$tc('ictech-gift-card.moderation.actionRevoke'),
                balance_adjust: this.$tc('ictech-gift-card.moderation.actionAdjust'),
                manual_resend: this.$tc('ictech-gift-card.moderation.actionResend'),
            };
            return map[action] ?? action;
        },

        getActionVariant(action) {
            const map = {
                revoke: 'danger',
                balance_adjust: 'warning',
                manual_resend: 'info',
                status_change: 'neutral',
            };
            return map[action] ?? 'neutral';
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
            if (!status) return '—';
            const key = `ictech-gift-card.order.status.${this.kebabToCamel(status)}`;
            return this.$tc(key);
        },

        kebabToCamel(str) {
            if (!str) return '';
            return str.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
        },
    },
};
