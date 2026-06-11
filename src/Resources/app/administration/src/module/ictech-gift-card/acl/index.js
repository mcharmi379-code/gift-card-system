const { Service } = Shopware;

Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'ictech_gift_card',
    roles: {
        viewer: {
            privileges: [
                'ictech_gift_card:read',
                'ictech_gift_card_template:read',
                'ictech_gift_card_voucher:read',
                'media:read',
                'sales_channel:read',
                'currency:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'ictech_gift_card:update',
                'ictech_gift_card_template:update',
            ],
            dependencies: ['ictech_gift_card.viewer'],
        },
        creator: {
            privileges: [
                'ictech_gift_card:create',
                'ictech_gift_card_template:create',
            ],
            dependencies: ['ictech_gift_card.viewer', 'ictech_gift_card.editor'],
        },
        deleter: {
            privileges: [
                'ictech_gift_card:delete',
                'ictech_gift_card_template:delete',
            ],
            dependencies: ['ictech_gift_card.viewer'],
        },
    },
});
