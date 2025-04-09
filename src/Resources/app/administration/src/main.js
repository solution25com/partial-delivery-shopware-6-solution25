import './module/sw-order/page/sw-order-detail';
import './module/sw-order/page/sw-order-detail-shipment';
import './modules/swag-return-management/component/swag-return-management-create-return-modal'
import './modules/swag-return-management/component/swag-return-management-return-card'
import './module/sw-order/component/sw-order-line-items-grid/index'
import './module/sw-order/view/sw-order-detail-general/index'
import './styles/base.scss'


Shopware.Module.register('order-detail-shipment', {
    routeMiddleware(next, currentRoute) {
        const customRouteName = 'sw.order.detail.shipment';

        if (
            currentRoute.name === 'sw.order.detail'
            && currentRoute.children.every((currentRoute) => currentRoute.name !== customRouteName)
        ) {

                currentRoute.children.push({
                    name: 'sw.order.detail.shipment',
                    path: '/sw/order/detail/:id/shipment',
                    component: 'sw-order-detail-shipment',
                    meta: {
                        parentPath: 'sw.product.index',
                    }
                });

        }
        next(currentRoute);
    }
});