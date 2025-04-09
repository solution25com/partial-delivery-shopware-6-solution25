import './sw-order-detail';
import './sw-order-detail-shipment';


const { Module } = Shopware;

Module.register('sw-order', {
    routes: {
        detail: {
            component: 'sw-order-detail',
            path: 'sw/order/detail',
            redirect: {
                name: 'sw.order.detail.content'
            },
            children: {
                shipment: {
                    component: 'sw-order-detail-shipment',
                    path: '/sw/order/detail/:id/shipment',
                }
            }
        }
    }
});


