import GiftCardPlugin from './plugin/gift-card.plugin';

const PluginManager = window.PluginManager;
if (PluginManager) {
    PluginManager.register('GiftCardPlugin', GiftCardPlugin, '[data-ictech-gift-card-page]');
}
