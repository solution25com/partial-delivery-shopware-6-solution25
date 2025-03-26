import template from './sw-order-detail-shipment.html.twig';
import  '../../component/sw-order-detail-shipment-create'

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-detail-shipment', {
    template,

    props: {
        orderId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            shipments: [],
            productDetails: {},
            hasPartialDelivery: true,
            showCreateShipment: false, 
            isLoading: false
        };
    },

    created() {
        this.loadShipments();
    },

    methods: {
        async loadShipments() {
            this.isLoading = true; 
        
            try {
                const orderDeliveryRepository = Shopware.Service('repositoryFactory').create('order_delivery_position');
                const orderLineItemRepository = Shopware.Service('repositoryFactory').create('order_line_item');
                const productRepository = Shopware.Service('repositoryFactory').create('product');
                const partialDeliveryRepository = Shopware.Service('repositoryFactory').create('partial_delivery');
        
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('orderDelivery.orderId', this.orderId));
                criteria.addAssociation('orderDelivery');
        
                const orderDeliveryPositions = await orderDeliveryRepository.search(criteria, Shopware.Context.api);
        
                const orderLineItemIds = [...new Set(orderDeliveryPositions.map(pos => pos.orderLineItemId))];
        
                const lineItemCriteria = new Criteria();
                lineItemCriteria.addFilter(Criteria.equalsAny('id', orderLineItemIds));
        
                const orderLineItems = await orderLineItemRepository.search(lineItemCriteria, Shopware.Context.api);
        
                let lineItems = {};
                let productIds = [];
        
                orderLineItems.forEach(item => {
                    if (item.productId) {
                        productIds.push(item.productId);
                        lineItems[item.id] = {
                            productId: item.productId,
                            productName: item.label,
                            quantityOrdered: item.quantity,
                            shipments: []
                        };
                    }
                });
        
                if (productIds.length) {
                    const productCriteria = new Criteria();
                    productCriteria.addFilter(Criteria.equalsAny('id', productIds));
        
                    const products = await productRepository.search(productCriteria, Shopware.Context.api);
        
                    products.forEach(product => {
                        this.productDetails[product.id] = {
                            productNumber: product.productNumber,
                            name: product.translated.name || product.name
                        };
                    });
                }
        
                const shipmentCriteria = new Criteria();
                shipmentCriteria.addFilter(Criteria.equalsAny('orderLineItemId', orderLineItemIds));
        
                const partialDeliveries = await partialDeliveryRepository.search(shipmentCriteria, Shopware.Context.api);
        
                partialDeliveries.forEach(shipment => {
                    if (lineItems[shipment.orderLineItemId]) {
                        lineItems[shipment.orderLineItemId].shipments.push({
                            quantity: shipment.quantity,
                            package: shipment.package,
                            trackingCode: shipment.trackingCode,
                            createdAt: shipment.createdAt
                        });
                    }
                });
        
                if (!partialDeliveries.length) {
                    this.hasPartialDelivery = false;
                    this.shipments = [];
                    return;
                }
        
                this.hasPartialDelivery = true;
        
                this.shipments = Object.values(lineItems)
                    .map(item => {
                        const quantityShipped = item.shipments.reduce((sum, s) => sum + s.quantity, 0);
                        return {
                            ...item,
                            productNumber: this.productDetails[item.productId]?.productNumber || '',
                            quantityShipped,
                            quantityLeft: item.quantityOrdered - quantityShipped
                        };
                    })
                    .filter(item => item.shipments.length > 0);
        
            } catch (error) {
                console.error('Error loading shipments:', error);
                this.createNotificationError({
                    title: 'Loading Error',
                    message: 'An error occurred while loading shipment data.'
                });
            } finally {
                this.isLoading = false; 
            }
        },
        formatDateTime(dateTime) {
            return new Date(dateTime).toISOString().slice(0, 16).replace("T", " ");
        },
        toggleShipmentCreation() {
            this.showCreateShipment = !this.showCreateShipment;
        },
        async onShipmentCreated() {
            // this.showCreateShipment = false;
            this.shipments = [];
            await this.loadShipments();
        }
    }
});