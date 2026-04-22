import template from './sw-order-detail-shipment-update.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const createShipmentDataFromShipment = (shipment = {}) => ({
    orderLineItemId: shipment.orderLineItemId || '',
    quantity: shipment.quantity || 0,
    package: shipment.package || '',
    trackingCode: shipment.trackingCode || '',
});

Component.register('sw-order-detail-shipment-update', {
  template,

  inject: ['repositoryFactory'],

  mixins: [Mixin.getByName('notification')],

  props: {
    shipment: {
        type: Object,
        required: true,
    },
},

  data() {
    return {
      orderLineItems: [],
      selectedOrderLineItem: null,
      shipmentData: createShipmentDataFromShipment(this.shipment),
      initialShipmentData: createShipmentDataFromShipment(this.shipment),
      formResetKey: 0,
      columns: [
        {
          property: 'select',
          label: '',
          rawData: true, 
          allowResize: false,
        },
        { property: 'label', label: 'Item' },
        { property: 'quantity', label: 'Available Quantity' },
      ],
    };
  },

  computed: {
    orderLineItemRepository() {
        return this.repositoryFactory.create('order_line_item');
    },

    selectedProduct() {
        return this.orderLineItems.find(
            (item) => item.id === this.shipmentData.orderLineItemId
        ) || {};
    },
},

    created() {
        this.fetchShipmentDetails();
        this.loadOrderLineItems();
    },

    watch: {
        shipment(newShipment) {
            if (newShipment) {
                this.fetchShipmentDetails();
            }
        },
    },    

  methods: {
    async loadOrderLineItems() {
      const orderId = this.shipment.orderId;
      
      if (!orderId) {
        return;
      }

      const criteria = new Criteria();
      criteria.addFilter(Criteria.equals('orderId', orderId));
      criteria.setLimit(10);

      try {
        this.orderLineItems = await this.orderLineItemRepository.search(criteria, Shopware.Context.api);
      } catch (error) {
        console.error('Error fetching order line items:', error);
      }
    },

    onSelectOrderLineItem(selection) {
      const selectedKeys = Object.keys(selection);
      this.shipmentData.orderLineItemId = selectedKeys.length > 0 ? selectedKeys[0] : '';
    },
    async fetchShipmentDetails() {
        const shipmentId = this.shipment?.id;
        if (!shipmentId) return;
    
        try {
            const response = await Shopware.Service('repositoryFactory').httpClient.get(
                `/_action/partial-shipment-delivery/${shipmentId}`,
                {
                    headers: {
                        Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                }
            );
    
            if (response?.data) {
                const shipmentData = createShipmentDataFromShipment(response.data);
                this.shipmentData = shipmentData;
                this.initialShipmentData = { ...shipmentData };
    
                console.log('Fetched Order Line Item ID:', this.shipmentData.orderLineItemId);
            }
        } catch (error) {
            console.error('Failed to fetch shipment details', error);
        }
    },

    async updateShipment() {
        const shipmentId = this.shipment.id;
        if (!shipmentId) {
            this.createNotificationError({
                title: 'Shipment Error',
                message: 'No shipment selected for update.',
            });
            return;
        }
        console.log('Order Line Item ID:', this.shipmentData.orderLineItemId); // 👈 Add this line

        const payload = {
            partialDeliveries: [
                {
                    orderLineItemId: this.shipmentData.orderLineItemId,
                    quantity: this.shipmentData.quantity,
                    package: this.shipmentData.package,
                    trackingCode: this.shipmentData.trackingCode,
                },
            ],
            shipmentId: shipmentId, 
        };


        try {
            const response = await Shopware.Service('repositoryFactory').httpClient.patch(
                `/_action/partial-shipment-delivery/update/${shipmentId}`,
                payload,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
                    },
                }
            );

            if (response.data.updatedIds && response.data.updatedIds.length > 0) {
                this.createNotificationSuccess({
                    title: 'Shipment Updated',
                    message: 'The shipment has been successfully updated.',
                });
                this.$emit('shipment-updated');
            } else {
                const skipped = response.data.skippedItems?.[0];
                const reason = skipped?.reason || 'Some items may have been skipped.';
                this.createNotificationWarning({
                    title: 'Shipment Warning',
                    message: reason,
                });
            }
        } catch (error) {
            console.error('Error updating shipment:', error);
            this.createNotificationError({
                title: 'Shipment Error',
                message: 'An error occurred while updating the shipment. Please try again.',
            });
        }
    },

    resetForm() {
        this.shipmentData = {
            orderLineItemId: this.initialShipmentData.orderLineItemId || this.shipmentData.orderLineItemId,
            quantity: 0,
            package: '',
            trackingCode: '',
        };
        this.formResetKey += 1;
    },
  },
});
