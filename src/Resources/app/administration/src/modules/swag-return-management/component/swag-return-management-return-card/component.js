import template from './swag-return-management-return-card.html.twig';
// import './swag-return-management-return-card.scss';

const { Component, Utils, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();
const { cloneDeep } = Shopware.Utils.object;
const { format } = Utils;

export default Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

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
        required: true,
      },
    },

    data(){
      return {
        returnItem: null,
        orderReturnStateOptions: [],
        isLoading: false,
        showDeleteReturnModal: false,
        showChangeStatusModal: false,
        selectedState: null,
      };
    },
    computed: {
      ...mapState('swOrderDetail', [
        'order',
        'versionContext'
      ]),

      taxStatus(){
        return this.returnItem?.price?.taxStatus || '';
      },

      returnRepository() {
        return this.repositoryFactory.create('order_return');
      },

      stateMachineStateRepository() {
        return this.repositoryFactory.create('state_machine_state');
      },

      sortedCalculatedTaxes() {
        return this.sortByTaxRate(cloneDeep(this.returnItem?.price?.calculatedTaxes)).filter(price => price.tax !== 0);
      },

      cardDescription() {
        const time = Utils.format.date(this.returnItem?.createdAt, {
          hour: '2-digit',
          minute: '2-digit',
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
        });

        return this.$tc('swag-return-management.returnCard.labelReturnCreated', 0,
          { time, user: this.lastChangedUser });
      },

      lastChangedUser() {
        if (this.returnItem?.updatedBy) {
          const { firstName, lastName } = this.returnItem.updatedBy;
          return `${firstName} ${lastName}`;
        }

        if (this.returnItem?.createdBy) {
          const { firstName, lastName } = this.returnItem.createdBy;
          return `${firstName} ${lastName}`;
        }

        return '';
      },

      returnLineItems(){
      return this.returnItem?.lineItems ?? [];
    },

    totalItems() {
      return this.returnLineItems.reduce((total, lineItem) => total + lineItem.quantity, 0) ?? 0;
    },

    cardTitle() {
      return this.$tc('swag-return-management.returnCard.labelTitle', 0, { returnNumber: this.item.returnNumber})
    },

    stateSelectPlaceholder() {
      return this.returnItem?.state?.translated?.name || '';
    },

    shippingCostsDetail() {
      const calcTaxes = this.sortByTaxRate(cloneDeep(this.returnItem?.shippingCosts?.calculatedTaxes) ?? []);
      if (calcTaxes.length === 0) {
        return '';
      }

      const formattedTaxes = `${calcTaxes.map(
        calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
          taxRate: calcTax.taxRate,
          tax: format.currency(calcTax.tax, this.order.currency.shortName),
        })}`,
      ).join('<br>')}`;
      if (this.taxStatus === 'gross') {
        return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
      }

      return `${this.$tc('swag-return-management.returnCard.excludedTax')}<br>${formattedTaxes}`;
    },

    currencyFilter() {
      return Shopware.Filter.getByName('currency');
    },
  },

  created() {
  this.createdComponent();
},

  unmounted() {
  this.destroyedComponent();
},

  methods: {
  createdComponent() {
    if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
    this.$root.$on('order-edit-cancel', this.onCancelEditing);
  } else {
    Shopware.Utils.EventBus.on('order-edit-cancel', this.onCancelEditing);
  }

  this.returnItem = this.item;
  this.loadReturn();
  this.loadStateMachineState();
},

  destroyedComponent() {
    if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
    this.$root.$off('order-edit-cancel', this.onCancelEditing);
  } else {
    Shopware.Utils.EventBus.off('order-edit-cancel', this.onCancelEditing);
  }
},

  backgroundStyle(stateType) {
    return this.stateStyleDataProviderService.getStyle(
      `${stateType}.state`,
      this?.returnItem?.state?.technicalName,
    ).selectBackgroundStyle;
  },

    async onStateSelect(stateType, actionName) {
    if (!stateType || !actionName) {
    this.createStateChangeErrorNotification(this.$tc('swag-return-management.notification.labelErrorNoAction'));
    return;
  }

  this.showChangeStatusModal = true;
  this.selectedState = this.orderReturnStateOptions.find(item => item.id === actionName);
},

  loadReturn(){
    this.isLoading = true;

    const criteria = new Criteria();
    criteria.addAssociation('state')
      .addAssociation('lineItems.lineItem')
      .addAssociation('lineItems.state')
      .addAssociation('createdBy')
      .addAssociation('updatedBy');

    return this.returnRepository.get(this.item.id, this.versionContext, criteria)
      .then(data => {
        this.returnItem = data;
      }).finally(() => {
        this.isLoading = false;
      });
  },

    loadStateMachineState() {
    const criteria = new Criteria(1, null);
    criteria.addSorting({ field: 'name', order: 'ASC' });
    criteria.addAssociation('stateMachine');
    criteria.addFilter(
      Criteria.equals(
        'state_machine_state.stateMachine.technicalName',
        'order_return.state',
      ),
    );

    return this.stateMachineStateRepository.search(criteria)
      .then((data) => {
        const orderReturnStates = data;
        this.getOrderLineItemStateTransition(orderReturnStates);
      });
  },
    sortByTaxRate(price) {
    return price.sort((prev, current) => {
      return prev.taxRate - current.taxRate;
    });
  },
    getOrderLineItemStateTransition(orderReturnStates) {
    return this.stateMachineService.getState(
      'order_return',
      this.item.id,
      {},
      Shopware.Classes.ApiService.getVersionHeader(this.versionContext.versionId)
    )
      .then((response) => {
        this.orderReturnStateOptions = this.buildTransitionOptions(
          orderReturnStates,
          response?.data?.transitions
        );
      });
  },

    buildTransitionOptions(allTransitions = [], possibleTransitions= []){
    const options = allTransitions.map((state, index) => {
      return {
        stateName: state.technicalName,
        id: state.technicalName,
        name: state.translated.name,
        disabled: true,
      };
    });

    options.forEach((option) => {
      const transitionToState = possibleTransitions.filter((transition) => {
        return transition.toStateName === option.stateName;
      });

      if (transitionToState.length >= 1) {
        option.disabled = false;
        option.id = transitionToState[0].actionName;
      }
    });

    return options;
  },

    createStateChangeErrorNotification(errorMessage) {
    this.createNotificationError({
      message: this.$tc('swag-return-management.notification.labelErrorStateChange') + errorMessage,
    });
  },

    reloadData() {
    return this.loadReturn().then(() => {
      this.$emit('reload-order');
    });
  },

    openDeleteReturnModal(){
    this.showDeleteReturnModal = true;
  },

    onCloseDeleteReturnModal() {
    this.showDeleteReturnModal = false;
  },

    onCloseChangeStatusModal(){
    this.showChangeStatusModal = false;
    this.selectedState = null;
  },

    onReturnStateChange() {
    this.showChangeStatusModal = false;
    this.selectedState = null;
    this.saveOrder();
  },

    onShippingChargeEdit(amount) {
    this.returnItem.shippingCosts.quantity = 1;
    this.returnItem.shippingCosts.unitPrice = amount;
    this.returnItem.shippingCosts.totalPrice = amount;

    this.updateShippingCosts(this.returnItem);
  },

    async updateShippingCosts(item) {
    try {
      await this.returnRepository.save(item, this.versionContext);
      await this.orderReturnApiService.recalculateRefundAmount(item.id, this.versionContext.versionId);
      await this.reloadData();
    } catch (error) {
      this.createNotificationError({
        message: `${this.$tc('swag-return-management.notification.labelErrorUpdateRefund')}${error}`,
      });
    }
  },

    async onCancelEditing(){
    await this.loadReturn();
    await this.loadStateMachineState();
  },

    saveOrder() {
    this.$emit('save-order');
  },

  onShippingChargeUpdated(amount) {
    const positiveAmount = Math.abs(amount);
    this.item.shippingCosts.unitPrice = positiveAmount;
    this.item.shippingCosts.totalPrice = positiveAmount;
  },
},
});
