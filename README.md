<h1 align="center">Shopware 6 Plugin AmbimaxGlobalsysConnect</h1>

<p align="center">
  Connects your Globalsys ERP to your Shopware 6 shop
</p>

## Description

This plugin connects a Shopware 6 shop to an existing Globalsys ERP using
the [EDC-SDK](https://www.github.com/ambimax/composer-package-globalsys-edcsdk).

## Change log

From [1.9.2](https://github.com/ambimax/shopware6-plugin-ambimax-globalsysconnect/releases/tag/1.9.2) on this plugin
uses [ambimax/semantic-release-composer](https://github.com/ambimax/semantic-release-composer)
to manage releases and change logs.

With [1.16.0](https://github.com/ambimax/shopware6-plugin-ambimax-globalsysconnect/releases/tag/1.16.0) support for
updating Media after initialising was dropped due to a bug.

#### [See change logs](./CHANGELOG.md)

## Current features

| feature         | implementation                                                          | description                                                                                                                                                                  | tests |
|-----------------|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------|
| export orders   | Command `ambimax:export:order`<br/>ScheduledTask (default 10 minutes)   | Exports specific orders to the ERP (see [Config](docs/Config.md)).                                                                                                           | 0 %   |
| import products | Command `ambimax:import:product`<br/>ScheduledTask (default 10 minutes) | Imports products from the ERP with some defined settings (see [Config](docs/Config.md#product-import-configuration) and [Mapping](docs/Config.md#product-property-mapping)). | 0 %   |
| import stocks   | Command `ambimax:import:stock`<br/>ScheduledTask (default 5 minutes)    | Imports stocks from the ERP that have been changed in the past 100 minutes.                                                                                                  | 0 %   |
| update orders   | Command `ambimax:update:orders`                                         | Gets the payment/delivery status of the orders from a specific timeframe (default: `-24 HOURS`) and updates their status in shopware                                         | 0 %   |

## Installation

Install using composer:

```shell
$ composer require ambimax/shopware6-plugin-ambimax-globalsysconnect
```

### Available versions

For releases visit [the release page](https://github.com/ambimax/shopware6-plugin-ambimax-globalsysconnect/releases) on
GitHub.

## Configuration

You can read all about it in the [configuration](./docs/Config.md) documentation.

## Usage

First you need to fill in `Globalsys Connect General Configuration` (LastPass helps) to have a connection to Globalsys.

### Sandbox Mode

Since [1.26.0](https://github.com/ambimax/shopware6-plugin-ambimax-globalsysconnect/releases/tag/1.26.0) this plugin has
a so-called "sandbox mode". With this you can test features more easily. It only works if there is a separate channel in
the Globalsys EDC, that exists for this purpose. All supported features will interact with the sandbox channel.

Make sure you enter the credentials for the sandbox channel (LastPass helps).

With the "Enable/disable sandbox mode" you enable/disable the sandbox mode globally, but only the following features
support it:

* Order Export
* Order Import

At the moment of developing the sandbox mode, it made sense not to use it for importing products and stocks. It was
important to still retrieve real product data in every environment.

In the configuration of the sandbox mode you can see a text field for "Sandbox emails for test orders". Only orders with
one of those emails will be exported to the sandbox channel.

#### Adding sandbox support to other features

If you want to add another feature to the sandbox mode you have to adjust its request class and the corresponding
handler like
in [this commit](https://github.com/ambimax/shopware6-plugin-ambimax-globalsysconnect/commit/003d8e79c5b34a438f65d30d6ec9d1ab8747ea82)
.

### Export Orders

With enabling `Order Export Configuration` the ScheduledTask can export orders at any time. So be aware of knowing what
you do. You also are now able to use the `ambimax:export:order` Command. Turn on `Enable/disable single order export` to
let the ScheduledTask and Command export only a single order.

There is an additional mapping for shipping methods in the configuration section in the administration. With this
mapping the order export also sends the type of the shipping method.

#### Export orders manually with the Shopware Command:

```shell
$ bin/console ambimax:export:order
```

If an order has been exported, it won't be exported again. To check that go to `Custom fields` of an order and look
for `Has been sent`.

An order only will be exported if one of the following conditions is fulfilled:

* Payment method is in config `This payment methods always lead to an export`
* Payment status is `'paid'`

Or in other words: nothing will be exported if there is no order with one of these conditions.

Manipulate the next execution time with `Interval for sending orders to Globalsys`. It will be applied with the next
run. Meaning: If the next run is in eight minutes, and you adjust this value from `600` (10 minutes) to `10` (10
seconds) it will still take eight minutes to the next execution.

### Import Products

With enabling `Product Import Configuration` the ScheduledTask can import products at any time. So be aware of knowing
what you do. You also are now able to use the `ambimax:import:product` Command.

There are exceptions due to subsequent additional requirements. Read
the [Product Import Exceptions](./docs/ProductImportExceptions.md) for more information.

#### Import products manually with the Shopware Command:

```text
$ bin/console ambimax:import:product [options]

Options:
  -c, --categories[=CATEGORIES]      Specify categories from which you want to import, separated by '~'
  -f, --force                        Force import of products. They will be imported, even nothing significant has changed.
  -m, --max[=MAX]                    Maximum amount of products you want to import
  -s, --search[=SEARCH]              Import products that are the result of a search with that string
  -u, --updatedAfter[=UPDATEDAFTER]  Import products that have been updated in this elapsed time [default: "-60minutes"]
```

See [Product Import Configuration](docs/Config.md#product-import-configuration) to get information about how you can
configure the import of products.

Manipulate the next execution time with `Interval for importing products from Globalsys`. It will be applied with the
next run. Meaning: If the next run is in eight minutes, and you adjust this value from `600` (10 minutes) to `10` (10
seconds) it will still take eight minutes to the next execution.

It is important to know, that the entry of `'Choose the property group that represents the option axis'` matches the
exact property in which the single variants differ each other. The rest of
the [Product Import Configuration](docs/Config.md#product-import-configuration) is self-explanatory.

In the section [Product Property Mapping](docs/Config.md#product-property-mapping) you define to which property each
product attribute out of Globalsys will be assigned.

### Import Stocks

With enabling `Stock Import Configuration` the ScheduledTask can import stocks at any time. You also are now able to use
the `ambimax:import:stock` Command.

#### Import stocks manually with the Shopware Command:

```text
$ bin/console ambimax:import:stock [options]

Options:
  -p, --pastMinutes[=PASTMINUTES]  Import stocks that have been updated in this past minutes. 0 for all.
  -b, --bunchSize[=BUNCHSIZE]      Set the bunch size for updating products in DB.
```

The stock import fetches all stocks that have been updated in the past `n` minutes. There are no paginated collections
in the response from the ERP API. Just one response with all changed stocks. With this response the implementation
collects and prepares all product data, that should be updated. It results in one database request, that saves the whole
collection at one time to save time.

## Integration Tests

Here you get explained how to test the integration of a feature.

### Export Orders

1. Configure the plugin:

* Fill in `Sandbox Configuration` (LastPass helps)
* Enable `Enable/disable sandbox mode`
* Type in comma separated emails, whose orders should be sent
* Enable `Order Export Configuration`
* Enable `Enable/disable single order export`
* (Optionally) Choose a mapping for the different shipping methods

1. Make sure there is an unsent order that has the payment method `Pay in advance` (Vorkasse) or payment status `paid`

1. Run `bin/console ambimax:export:order` or wait until the ScheduledTask has been handled successfully

1. Look up for a UUID in [Globalsys](https://berg.gs-center.de/) to validate the result

### Import Products

1. Configure the plugin:

* Fill in `Globalsys Connect General Configuration` (LastPass helps)
* Enable `Product Import Configuration`
* Choose the `property_group` that represents the variant axis
* Choose the `tax` for the import configuration
* Choose the delivery time for the import configuration
* Fill in the mapping (if there are no properties to select, create them with the names you see in this list)

1. Run `bin/console ambimax:import:product` and abort the command after a few seconds or wait until the ScheduledTask
   has been handled successfully

1. Look up for new products in the administration

### Import Stocks

1. Configure the plugin:

* Fill in `Globalsys Connect General Configuration` (LastPass helps)
* Enable `Stock Import Configuration`

1. Run `bin/console ambimax:import:stock` or wait until the ScheduledTask has been handled successfully and abort the
   command after a few seconds

1. Look up for updated stocks in the administration

### Update Orders

1. Run `bin/console ambimax:update:orders`

2. Check if the status in the administration matches the one in Globalsys

## Logging

### Administration

You maybe find some entries in the `Logging` section of the Administration under `Settings > System > Logging`.

The following log entries are available:

* `No manufacturer found` `{ "message": "No manufacturer found! Name: %s, SKU: %s" }`
* `Stock import successful` `{ "message": "Import took %s seconds for updating stock of %s products" }`

### Debugging Logs

You can enable logging for debug. If the options is activated, the plugin will log in the shopware Logging system.

* Enable Debugging Logs
  1. Configure the plugin
  2. Navigate to `Globalsys Connect General Configuration` Section
  3. Enable `Enable/disable debugging Logs`

## Plugin Structure

```shell
.
├── docs                        # documentation
└── src
    ├── AmbimaxGlobalsysConnect.php # un-/install and de-/activate scripts
    │
    ├── Administration          # things to show in the administration
    ├── Api                     # communication with the Globalsys api through the EDC SDK
    ├── Command                 # Shopware Commands
    │
    ├── Export                  # mapping from Shopware Entities to Globalsys Models
    │   ├── [Entity]            # e. g. 'Order'
    │   │   ├── [Entity]Collection.php # here you collect data from an Entity and map them
    │   │   └── Processor       # outsource logic of data processing before you can map it
    │   │       ├── ...
    │   │       ├── [DataName].php         # choose a good that represents the processed data
    │   │       └── ProcessorInterface.php # always use interfaces!
    │   └── [OtherModel]
    │       └── ...             # the same as in [Entity]
    │
    ├── Import                  # mapping from Globalsys Models to Shopware DAL queries
    │   ├── [Entity]            # e. g. 'Product'
    │   │   ├── [Entity]Collection.php # here you collect data from the Model and map them
    │   │   └── Processor       # outsource logic of data processing before you can map it
    │   │       ├── ...         # Look at this folder. It is a good example.
    │   │       ├── [DataName].php         # choose a good that represents the processed data
    │   │       └── ProcessorInterface.php # always use interfaces!
    │   └── [OtherModel]
    │       └── ...             # the same as in [Entity]
    │
    ├── Migration               # contains all migrations
    ├── Resources
    │   └── config              # service definitions and plugin configurations
    │       └── services        # separated service definitions
    └── ScheduledTask           # ScheduledTasks (every task shall manipulate the 'next_execution_time' after its run)
```

# Maintainer

- [Dominik Wißler](https://github.com/Wysselbie)
- [Konstantin Bode](https://github.com/BodeSpezial)
- [Julian Bour](https://github.com/JulianBour)
