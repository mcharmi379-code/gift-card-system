import './module/ictech-gift-card';

Shopware.Component.override('sw-pagination', {
    computed: {
        possibleSteps() {
            return this.steps;
        }
    }
});
