import template from './sw-order-detail-general.html.twig';

const {Component} = Shopware;
const {Criteria} = Shopware.Data;

Component.override('sw-order-detail-general', {
  template,

  inject: [
    'repositoryFactory'
  ],

  data() {
    return {
      returnOptions: [],
      selectedReturnId: null,
      selectedReturnData: null
    };
  },

  computed: {
    orderId() {
      return this.$route.params.id || this.order?.id;
    },
    stateMachineStateRepository() {
      return this.repositoryFactory.create('state_machine_state');
    }
  },

  methods: {
    async getDoneState() {
      return await this.stateMachineStateRepository.search(this.stateMachineStateCriteria());
    },

    stateMachineStateCriteria() {
      const criteria = new Criteria(1, 50)
        .addAssociation('stateMachine')
        .addFilter(Criteria.equals('stateMachine.technicalName', 'order_return.state'))
        .addFilter(Criteria.equals('technicalName', 'open'));
      return criteria;
    },

    async fetchReturnOptions() {
      try {
        const orderId = this.$route.params.id || this.order?.id;

        if (!orderId) {
          this.createNotificationError({
            title: 'Order ID missing',
            message: 'Cannot fetch returns without an order ID.'
          });
          return;
        }

        const stateResponse = await this.getDoneState();


        const filter = [
          {
            type: 'equals',
            field: 'orderId',
            value: orderId
          }
        ]
        if (stateResponse && stateResponse[0]?.id) {
          filter.push(
            {
              type: "equals",
              field: "stateId",
              value: stateResponse[0].id
            });
        }

        const response = await Shopware.Service('repositoryFactory').httpClient.post(
          '/search/order-return',
          {
            filter: filter
          },
          {
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
            }
          }
        );
        const options = response.data.data.map(item => ({
          id: item.id,
          label: `Return #${item.returnNumber} - ${item.amountTotal}`
        }));

        this.returnOptions = options;
      } catch
        (error) {
        this.createNotificationError({
          title: 'Error loading returns',
          message: error.message
        });
      }
    }
    ,

    onReturnSelect(returnId) {
      this.selectedReturnData = this.returnOptions.find(r => r.id === returnId);
    }
    ,

    async onRefundClick() {
      if (!this.orderId || !this.selectedReturnId) {
        this.createNotificationError({
          title: 'Refund Error',
          message: 'Order ID or Return ID is missing.'
        });
        return;
      }

      try {
        const response = await Shopware.Service('repositoryFactory').httpClient.post(
          '/refund',
          {
            orderId: this.orderId,
            returnId: this.selectedReturnId
          }, {
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
            }
          }
        );

        this.createNotificationSuccess({
          title: 'Refund Triggered',
          message: 'The refund request was successfully sent.'
        });
      } catch (error) {
        this.createNotificationError({
          title: 'Refund Failed',
          message: error.response?.data?.errors?.[0]?.detail || error.message
        });
      }
    }
  },

  mounted() {
    this.fetchReturnOptions();
  }
});
