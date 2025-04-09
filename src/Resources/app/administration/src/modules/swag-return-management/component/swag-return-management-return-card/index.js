import template from './swag-return-management-return-card.html.twig';

const { Component, Mixin, Filter, Utils } = Shopware;

Component.register('swag-return-management-return-card', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'acl',
        'repositoryFactory',
        'stateMachineService',
        'stateStyleDataProviderService',
        'orderReturnApiService',
    ],

    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    // ... Paste in all other logic (data, computed, methods, etc.)
});