import template from './ictech-gift-card-placeholder.html.twig';

export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        page: {
            type: String,
            required: true,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.title),
        };
    },

    computed: {
        title() {
            return this.$tc(`ictech-gift-card.placeholder.${this.page}.title`);
        },

        subline() {
            return this.$tc(`ictech-gift-card.placeholder.${this.page}.subline`);
        },
    },
};
