## [Back](../README.md)

## Configuration

### Globalsys Connect General Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|URL to the Globalsys API|apiUrl|text| ||
|API username|apiUser|text| ||
|API secret|apiSecret|password| ||
|Enable/disable debug logs|debugLogEnabled|bool|false|During the execution of the jobs, at certain points Shopware logs will be created.|

### Sandbox Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Enable/disable sandbox mode|sandboxEnabled|bool|false|If enabled, orders will be exported/imported to/from a sandbox channel.|
|Sandbox API username|sandboxApiUser|text| ||
|Sandbox API secret|sandboxApiSecret|password| ||
|Sandbox emails for test orders|sandboxEmails|text| |Type in the email addresses separated by commas.|

### Order Export Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Enable/disable order export|exportOrdersEnabled|bool|false|Enable/disable the ScheduledTask and Command.|
|Enable/disable single order export|exportOrdersSingle|bool|false|When exporting real orders, export only a single order.|
|Interval for sending orders to Globalsys|exportOrdersInterval|int|600|Specify the amount of seconds to wait until the next execution of sending orders to Globalsys.             |
|Enable/disable debug logs|exportOrdersDebugLogEnabled|bool|false|During the execution of this jobs, at certain points Shopware logs will be created.|
|This payment methods always lead to an export|exportOrdersPaymentMethods|multi-id-select payment_method| |Orders with one of those payment methods will be exported.|
|Choose the shipping method for 'DHL'|exportOrderShippingMappingDHL|single-select shipping_method| |The string 'DHL' will be in the order export for this shipping method.|
|Choose the shipping method for 'DPD'|exportOrderShippingMappingDPD|single-select shipping_method| |The string 'DPD' will be in the order export for this shipping method.|
|Choose the shipping method for 'Abholung'|exportOrderShippingMappingPickUp|single-select shipping_method| |The string 'Abholung' will be in the order export for this shipping method.|

### Update Orders Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Enable/disable importing order updates|importOrdersEnabled|bool|false|Allow importing order updats from globalsys|
|Interval for importing order updates from Globalsys|importOrdersInterval|int|3600|Specify the amount of seconds to wait until the next execution of importing orders from Globalsys.             |
|Enable/disable debug logs|importOrdersDebugLogEnabled|bool|false|During the execution of this jobs, at certain points Shopware logs will be created.|

### Stock Import Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Enable/disable stock import|importStockEnabled|bool|false|Enable/disable the ScheduledTask and Command.|
|Interval for importing stocks from Globalsys|importStockInterval|int|300|Specify the amount of seconds to wait until the next execution of importing stocks from Globalsys.             |
|Import fetches stocks from this past minutes|importStockPastMinutes|int|100|The import fetches all stocks that have been changed in the specified past minutes.             |
|Enable/disable debug logs|importStockDebugLogEnabled|bool|false|During the execution of this jobs, at certain points Shopware logs will be created.|

### Product Import Configuration

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Enable/disable product import|importProductsEnabled|bool|false|Enable/disable the ScheduledTask and Command.|
|Skip existing products during importing products from Globalsys|importProductsSkipExistingEnabled|bool| |The plugin looks up for an existing 'productNumber' and skips if it exists.|
|Activate clearance sale while importing products|importProductsSetClearanceSale|bool|false||
|Interval for importing products from Globalsys|importProductsInterval|int|600|Specify the amount of seconds to wait until the next execution of importing products from                 Globalsys.             |
|Maximum age of products that will be imported from Globalsys|importProductsMaximumAge|int| |Specify the amount of minutes until which products will be imported from Globalsys. 0 for all.             |
|Size of product bunches to update into database|importProductsBunchSize|int| |Keep it empty to use the default value. (100)             |
|Enable/disable debug logs|importProductsDebugLogEnabled|bool|false|During the execution of this jobs, at certain points Shopware logs will be created.|
|Choose the 'property_group' that represents the variant axis|optionAxisId|single-select property_group| |Imported products should differ in this.|
|Choose a tax for the import configuration|productsTax|single-select tax| |Imported products will have this tax.|
|Choose the delivery time for the import configuration|deliveryTime|single-select delivery_time| |Imported products will have this delivery time.|

### Product Property Mapping

|Label|Key|Type|Default|Description|
|---|---|---|---|---|
|Heel|AbsatzBez|single-select property_group| ||
|Inner sole material|ErlebnisBez|single-select property_group| ||
|Gender|FormBez|single-select property_group| ||
|Inner material|FutterBez|single-select property_group| ||
|Outer material|MaterialBez|single-select property_group| ||
|Colour|FarbBez|single-select property_group| ||
|Sole material|SohleBez|single-select property_group| ||
|Width|WeiteBez|single-select property_group| ||
|Model|productsName|single-select property_group| ||
|ID Number|productsWwsCode|single-select property_group| ||

