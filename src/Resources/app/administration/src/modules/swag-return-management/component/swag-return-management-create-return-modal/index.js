import {
    LineItemStatus
} from "../../../../../../../../../../SwagCommercial/src/ReturnManagement/Resources/app/administration/src/type/types";

const { Component } = Shopware;

Component.override('swag-return-management-create-return-modal', {
    
    
    data() {
        return {
            returnQuantityAdded: false
        };
    },
    
    methods: {
        hasReturnLineItems(lineItem) {
            const returnedQty = lineItem?.extensions?.returns?.reduce((sum, returnItem) => {
                return sum + returnItem.quantity;
            }, 0);
        
            return returnedQty >= lineItem.quantity;
        },
        hasInvalidStates(lineItem) {
            return false
        },
        onSave() {
            this.isLoading = true;
        
            let lineItems = this.lineItems.filter(item => {
                const totalReturned = item.extensions?.returns?.reduce((sum, r) => sum + (r.quantity || 0), 0);
                const remainingQty = item.quantity - totalReturned;
        
                if (item.returnQuantity > remainingQty) {
                    item.returnQuantity = remainingQty;
                }
        
                return item.returnQuantity > 0;
            });
        
            if (lineItems.length === 0) {
                this.createNotificationError({
                    message: this.$tc('swag-return-management.returnModal.messageErrorNoItemHasQuantity'),
                });
        
                this.isLoading = false;
                return;
            }
        
            lineItems = lineItems.map(item => {
                return {
                    orderLineItemId: item.id,
                    quantity: item.returnQuantity,
                    internalComment: item.comment,
                };
            });
        
            return this.createOrderReturn(lineItems);
        }
    }
   
});