const { Component } = Shopware;


Component.override('sw-order-line-items-grid', {
    created() {
        console.log('[DEBUG] sw-order-line-items-grid override: created hook');
    },

    computed: {
        unitPriceLabel() {
            console.log('[DEBUG] sw-order-line-items-grid override: unitPriceLabel method called. Tax status:', this.taxStatus);

            if (this.taxStatus === 'net') {
                return this.$tc('Unit Price');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.detailBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.detailBase.columnPriceGross');
        },
        getLineItemColumns() {
            const columnDefinitions = [{
                property: 'quantity',
                dataIndex: 'quantity',
                label: 'sw-order.detailBase.columnQuantity',
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '90px',
            }, {
                property: 'label',
                dataIndex: 'label',
                label: 'sw-order.detailBase.columnProductName',
                allowResize: false,
                primary: true,
                inlineEdit: true,
                multiLine: true,
            }, {
                property: 'payload.productNumber',
                dataIndex: 'payload.productNumber',
                label: 'sw-order.detailBase.columnProductNumber',
                allowResize: false,
                align: 'left',
                visible: false,
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: this.unitPriceLabel,
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '120px',
            }];

            if (this.taxStatus !== 'tax-free') {
                columnDefinitions.push({
                    property: 'price.taxRules[0]',
                    label: 'Tax',
                    allowResize: false,
                    align: 'right',
                    inlineEdit: true,
                    width: '90px',
                });
            }

            return [...columnDefinitions, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: this.taxStatus === 'gross' ?
                    'sw-order.detailBase.columnTotalPriceGross' :
                    'sw-order.detailBase.columnTotalPriceNet',
                allowResize: false,
                align: 'right',
                width: '120px',
            }];
        },
    }
});