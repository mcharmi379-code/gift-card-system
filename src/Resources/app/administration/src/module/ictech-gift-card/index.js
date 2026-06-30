import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/ictech-gift-card-settings-custom';

const { Module } = Shopware;

Module.register('ictech-gift-card', {
    type: 'plugin',
    name: 'ictech-gift-card',
    title: 'ictech-gift-card.general.mainMenuItemGeneral',
    description: 'ictech-gift-card.general.description',
    color: '#57D9A3',
    icon: 'regular-gift',
    favicon: 'icon-module-catalogue.png',
    entity: 'ictech_gift_card',

    defaultSearchConfiguration,
    routes: {
        dashboard: {
            component: 'ictech-gift-card-dashboard',
            path: 'dashboard',
            meta: { privilege: 'ictech_gift_card.viewer' },
        },
        index: {
            component: 'ictech-gift-card-list',
            path: 'index',
            meta: {
                privilege: 'ictech_gift_card.viewer',
                appSystem: { view: 'list' },
            },
        },
        create: {
            component: 'ictech-gift-card-detail',
            path: 'create',
            meta: { privilege: 'ictech_gift_card.creator', parentPath: 'ictech.gift.card.index' },
        },
        detail: {
            component: 'ictech-gift-card-detail',
            path: 'detail/:id',
            props: {
                default(route) {
                    return { giftCardId: route.params.id };
                },
            },
            meta: { privilege: 'ictech_gift_card.viewer', parentPath: 'ictech.gift.card.index' },
        },
        order: {
            component: 'ictech-gift-card-order-list',
            path: 'order',
            meta: { privilege: 'ictech_gift_card.viewer' },
        },
        template: {
            component: 'ictech-gift-card-template-list',
            path: 'template',
            meta: { privilege: 'ictech_gift_card.viewer' },
        },
        templateCreate: {
            component: 'ictech-gift-card-template-detail',
            path: 'template/create',
            meta: { privilege: 'ictech_gift_card.creator', parentPath: 'ictech.gift.card.template' },
        },
        templateDetail: {
            component: 'ictech-gift-card-template-detail',
            path: 'template/detail/:id',
            props: {
                default(route) {
                    return { templateId: route.params.id };
                },
            },
            meta: { privilege: 'ictech_gift_card.viewer', parentPath: 'ictech.gift.card.template' },
        },

        moderation: {
            component: 'ictech-gift-card-moderation',
            path: 'moderation',
            meta: { privilege: 'ictech_gift_card.viewer' },
        },
    },

    navigation: [
        {
            id: 'ictech-gift-card',
            path: 'ictech.gift.card.dashboard',
            label: 'ictech-gift-card.general.mainMenuItemGeneral',
            color: '#57D9A3',
            icon: 'regular-gift',
            position: 105,
            parent: 'sw-catalogue',
            privilege: 'ictech_gift_card.viewer',
        },
        {
            id: 'ictech-gift-card-dashboard',
            path: 'ictech.gift.card.dashboard',
            label: 'ictech-gift-card.navigation.dashboard',
            parent: 'ictech-gift-card',
            position: 5,
            privilege: 'ictech_gift_card.viewer',
        },
        {
            id: 'ictech-gift-card-order',
            path: 'ictech.gift.card.order',
            label: 'ictech-gift-card.navigation.order',
            parent: 'ictech-gift-card',
            position: 10,
            privilege: 'ictech_gift_card.viewer',
        },
        {
            id: 'ictech-gift-card-product',
            path: 'ictech.gift.card.index',
            label: 'ictech-gift-card.navigation.product',
            parent: 'ictech-gift-card',
            position: 20,
            privilege: 'ictech_gift_card.viewer',
        },
        {
            id: 'ictech-gift-card-template',
            path: 'ictech.gift.card.template',
            label: 'ictech-gift-card.navigation.template',
            parent: 'ictech-gift-card',
            position: 30,
            privilege: 'ictech_gift_card.viewer',
        },
        {
            id: 'ictech-gift-card-moderation',
            path: 'ictech.gift.card.moderation',
            label: 'ictech-gift-card.navigation.moderation',
            parent: 'ictech-gift-card',
            position: 35,
            privilege: 'ictech_gift_card.viewer',
        },

    ],
});

Shopware.Component.register('ictech-gift-card-dashboard', () => import('./page/ictech-gift-card-dashboard'));
Shopware.Component.register('ictech-gift-card-order-list', () => import('./page/ictech-gift-card-order-list'));
Shopware.Component.register('ictech-gift-card-list', () => import('./page/ictech-gift-card-list'));
Shopware.Component.register('ictech-gift-card-detail', () => import('./page/ictech-gift-card-detail'));
Shopware.Component.register('ictech-gift-card-placeholder', () => import('./page/ictech-gift-card-placeholder'));
Shopware.Component.register('ictech-gift-card-template-list', () => import('./page/ictech-gift-card-template-list'));
Shopware.Component.register('ictech-gift-card-template-detail', () => import('./page/ictech-gift-card-template-detail'));
Shopware.Component.register('ictech-gift-card-moderation', () => import('./page/ictech-gift-card-moderation'));
Shopware.Component.register('ictech-gift-card-settings', () => import('./page/ictech-gift-card-settings'));
