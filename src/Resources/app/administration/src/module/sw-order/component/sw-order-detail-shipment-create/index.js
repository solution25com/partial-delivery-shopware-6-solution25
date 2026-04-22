import template from './sw-order-detail-shipment-create.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const createEmptyShipmentData = () => ({
    orderLineItemId: '',
    quantity: 0,
    package: '',
    trackingCode: '',
});

Component.register('sw-order-detail-shipment-create', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            orderId: this.$route.params.id, 
            orderLineItems: [],
            selectedOrderLineItem: null,
            shipmentData: createEmptyShipmentData(),
            formResetKey: 0,
            columns: [
                {
                    property: 'select',
                    label: '',
                    rawData: true, 
                    allowResize: false,
                },
                { property: 'label', label: 'Item'},
                { property: 'product.productNumber', label: 'SKU' },
                { property: 'quantity', label: 'Available Quantity'},
            ],
        };
    },

    computed: {
        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },
    },

    methods: {
        async loadOrderLineItems() {
            if (!this.orderId) {
                return;
            }
        
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('orderId', this.orderId));
        
            criteria.addFilter(Criteria.not('OR', [
                Criteria.equals('productId', null)
            ]));
        
            criteria.addAssociation('product');
        
            criteria.setLimit(50); 
        
            try {
                this.orderLineItems = await this.orderLineItemRepository.search(criteria, Shopware.Context.api);
            } catch (error) {
                console.error('Error fetching order line items:', error);
                this.createNotificationError({
                    title: 'Error loading items',
                    message: error.message
                });
            }
        }
        ,

        onSelectOrderLineItem(selection) {
            const selectedKeys = Object.keys(selection);
            this.shipmentData.orderLineItemId = selectedKeys.length > 0 ? selectedKeys[0] : '';
        },

        async createShipment() {
            if (!this.shipmentData.orderLineItemId) {
                this.createNotificationError({
                    title: 'Shipment Error',
                    message: 'Please select an order line item before creating a shipment.',
                });
                return;
            }
        
            const payload = {
                partialDeliveries: [
                    {
                        orderLineItemId: this.shipmentData.orderLineItemId,
                        quantity: this.shipmentData.quantity,
                        package: this.shipmentData.package,
                        trackingCode: this.shipmentData.trackingCode,
                    },
                ],
            };
        
            try {
                const response = await Shopware.Service('repositoryFactory').httpClient.post(
                    '/_action/partial-shipment-delivery',
                    payload,
                    { 
                        headers: { 
                            'Content-Type': 'application/json', 
                            Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                        } 
                    }
                );
        
                if (response.data.insertedIds.length > 0) {
                    this.createNotificationSuccess({
                        title: 'Shipment Created',
                        message: 'The shipment has been successfully recorded.',
                    });
                    this.resetForm();
                    this.$emit('shipment-created');
                } else {
                    this.createNotificationWarning({
                        title: 'Shipment Warning',
                        message: 'No shipments were created. Some items may have been skipped.',
                    });
                }
        
                if (response.data.skippedItems && response.data.skippedItems.length > 0) {
                    response.data.skippedItems.forEach((item) => {
                        this.createNotificationWarning({
                            title: 'Shipment Skipped',
                            message: `Item ID ${item.orderLineItemId}: ${item.reason}`,
                        });
                    });
                }
        
            } catch (error) {
                this.createNotificationError({
                    title: 'Shipment Error',
                    message: 'An error occurred while creating the shipment. Please try again.',
                });
            }
        },        

        resetForm() {
            this.shipmentData = createEmptyShipmentData();
            this.formResetKey += 1;
        },
    
    },
    created() {
        this.loadOrderLineItems();
    },
});
