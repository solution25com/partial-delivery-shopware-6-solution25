import template from './sw-order-detail-shipment.html.twig';
import  '../../component/sw-order-detail-shipment-create'
import  '../../component/sw-order-detail-shipment-update'


const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-detail-shipment', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Mixin.getByName('notification')],
    props: {
        editShipment: {
            type: Object,
            required: false,
            default: null
        },
        orderId: {
            type: String,
            required: true
        },
        shipment: {
            type: Object,
            required: false,
            default: null
        }
    },
    

    data() {
        return {
            shipments: [],
            productDetails: {},
            hasPartialDelivery: true,
            showCreateShipment: false, 
            showUpdateShipment: false,
            isEditMode: false,
            shipmentBeingEdited: null,
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
                            id: shipment.id,
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
                console.log('Loaded shipments:', this.shipments);

        
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
        toggleShipmentUpdate() {
            this.showUpdateShipment = !this.showUpdateShipment;
        },
        async deleteShipment(shipmentToDelete, lineItemId) {
            try {
                if (!shipmentToDelete.id) {
                    this.createNotificationError({
                        title: 'Delete Failed',
                        message: 'Shipment ID missing.'
                    });
                    return;
                }
        
                const response = await Shopware.Service('repositoryFactory').httpClient.post(
                    `/_action/partial-shipment-delivery/delete/${shipmentToDelete.id}`,
                    {}, 
                    {
                        headers: {
                            Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                        }
                    }
                );
        
                this.createNotificationSuccess({
                    title: 'Success',
                    message: response.data.message || 'Shipment deleted successfully.'
                });
        
                await this.loadShipments();
            } catch (error) {
                console.error('Error deleting shipment:', error);
                this.createNotificationError({
                    title: 'Delete Error',
                    message: error.message || 'An error occurred while deleting the shipment.'
                });
            }
        },        
        startShipmentEdit(shipment, lineItem) {
            this.shipmentBeingEdited = {
                ...shipment,
                orderLineItemId: lineItem.id, 
                quantity: shipment.quantity || 0,
                package: shipment.package || '',
                trackingCode: shipment.trackingCode || '',
            };
        
            this.showUpdateShipment = true;
        },        
        
        async onShipmentCreated() {
            this.shipments = [];
            await this.loadShipments();
            this.showCreateShipment = false;
        },
        async onShipmentUpdated() {
            this.shipments = [];
            await this.loadShipments();
            this.showUpdateShipment = false;
        }
    }
});
