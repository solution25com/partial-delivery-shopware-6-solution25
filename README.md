# Partial Delivery Plugin
 
## Introduction
 
The **Partial Delivery** plugin for Shopware 6 enables merchants to manage and track partial shipments for individual order items. This is especially useful in scenarios where an order cannot be fulfilled all at once and must be shipped in multiple packages.It ensures that customers receive available items as soon as possible without having to wait for the entire order to be ready. This improves customer satisfaction and keeps logistics transparent and organized.
 
## Key Features
 
1. **Track Partial Shipments**  
   - Enables tracking of multiple packages per order item.  
   - Each package has its own quantity and tracking number.
 
2. **Custom Admin Tab for Shipments**  
   - Adds a new “Shipment” tab in the Order Detail view.  
   - View and manage all partial deliveries directly in the admin panel.
 
3. **Multiple Entries per Line Item**  
   - Supports recording several shipments for the same item.  
   - Useful for cases like backorders or separate warehouse fulfillment.
 
---
 
## Get Started
 
### Prerequisites
 
> **Important Requirement**  
> The Partial Delivery plugin requires the **Shopware Commercial** edition to function properly.  
> Make sure [Shopware Commercial](https://docs.shopware.com/en/shopware-6-en/extensions/shopware-commercial) is installed and active before proceeding.
 
---
 
### Installation & Activation
1. **Download**
## Git
- Clone the Plugin Repository:
- Open your terminal and run the following command in your Shopware 6 custom plugins directory (usually located at custom/plugins/):
 
   ```bash
   git clone https://github.com/solution25com/partial-delivery-shopware-6-solution25.git
   ```
 
2. **Install the Plugin in Shopware 6**
- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the newly cloned plugin and click Install.
3. **Activate the Plugin**
- After installation, click Activate to enable the plugin.
- In your Shopware Admin, go to Settings > System > Plugins.
- Upload or install the “PartialDelivery” plugin.
- Once installed, toggle the plugin to activate it.
4. **Verify Installation**
- After activation, you will see PartialDelivery in the list of installed plugins.
- The plugin name, version, and installation date should appear as shown in the screenshot below.
 
 ![image1](https://github.com/user-attachments/assets/849cffa1-aa37-4604-a408-b544308df729)
 
---
 
## Plugin Configuration
 
The Partial Delivery plugin does not require any specific configuration after installation. Once activated, it automatically adds a **"Shipment"** tab in the Order Detail view in the Shopware Admin panel.
 
![image2](https://github.com/user-attachments/assets/2251a9f3-ecd1-4ad2-b8cf-76bd787c9baa)
 
If the tab does not appear immediately after installation, run the following commands in your Shopware terminal:
 
```bash
bin/build-administration.sh
```
 
These commands will rebuild the admin interface to ensure the shipment tab is visible.
 
---
 
## How It Works
 
**1. Customer Places an Order**  
When a customer places an order, the full quantity of each product is recorded in the order under the Quantity column.
 
**2. Admin Creates a Shipment**  
From the order’s **Shipments** tab, the admin can manually create a shipment by clicking the **Create Shipment** button.
 
**3. Partial Shipment Entry**  
Instead of shipping the full quantity, the admin can enter a partial quantity to ship (e.g., only 5 out of 20 units).
 
Each partial shipment can include:
- Quantity
- A custom box label (e.g., “package 1”)
- Tracking number
 
 ![image3](https://github.com/user-attachments/assets/ab063867-fd41-4515-8343-603a587831ae)

 
**4. Shipment Details Are Tracked**
Each shipment is tracked under **Shipment Details**, showing:
- Box name
- Shipped quantity
- Tracking info
 
This allows multiple packages to be associated with a single order.
 
**5. Order Status and Fulfillment Management**  
- The **Shipped** column updates based on the total quantity sent.  
- Any remaining quantity stays unshipped, allowing future partial shipments.  
- Full visibility is maintained of what has been shipped vs. what is pending.
 
![image4](https://github.com/user-attachments/assets/4274303c-fa99-489c-9d4a-d2c5fd60a05a)

 
---
 
## Best Practices
 
- **Consistent Labeling**: Use consistent naming for box labels (e.g., "Package 1", "Box A") to easily identify shipments.
- **Track Everything**: Always enter tracking numbers for each shipment to maintain full shipment history.
- **Data Cleanup**: Periodically review old shipment records to ensure clarity and accuracy in your order management.
- **Test Before Use**: Try the partial delivery flow in a staging environment to confirm expected behavior before using it on live orders.
---
 
## Troubleshooting
 
- **Shipment Tab Not Visible in Admin?**  
  - Make sure the Partial Delivery plugin is installed and activated.
  - Confirm that the Shopware Commercial extension is installed and active, as it is a required dependency.
  - Rebuild the admin interface using the following command:
 
    ```bash
    bin/build-administration.sh
 
- **Plugin not visible in Extensions?**  
  - Clear the cache and refresh the plugin list:
    ```bash
    bin/console cache:clear
    bin/console plugin:refresh
- **Errors when submitting shipments?**  
  - Double-check that all required fields are filled in:
  - Quantity
  - Box name
  - Tracking number
---
 
## FAQ
 
- **Can I create multiple shipments for the same item?**  
  - Yes. Each shipment entry can have its own quantity and tracking data.
 
- **Is there a limit to how many shipments I can add per order?**  
  - No hard limit is enforced by the plugin. You can add as many shipment entries as needed per item or order.
 
- **Do I need to install additional plugins to use the shipment features?**  
  - To use the full shipment functionality, ensure that the required dependencies, such as the Shopware Commercial extension, are installed and active.
