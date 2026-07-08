# WooCommerce Custom Shipping with Pickup

A custom WooCommerce shipping plugin that allows store owners to define granular, weight-based and location-based shipping rates, alongside custom local pickup options.

## Features

### 1. Flexible Shipping Rates
- **Geographic Targeting**: Configure rates based on **Country** and **Postal Code** (supports `*` wildcard for matching all postal codes in a country).
- **Weight-Based Tiers**: Define rates dynamically based on the shopping cart's total weight (**Min Weight** and **Max Weight** in kg).
- **Dual Shipping Speeds**: Specify two shipping rates per configuration:
  - **Standard Fee**
  - **One Day Fee** (Express Delivery)

### 2. Custom Local Pickup Locations
- Configure specific physical addresses where customers can choose to collect their orders.
- Fields per pickup location: **Name**, **Address**, **Country**, **City**, **Postal Code**, and a **Location Fee** (if any).
- Customers can select their preferred pickup location directly on the checkout page.

### 3. AJAX-Powered Admin Dashboard
- Smooth admin UI embedded directly within WooCommerce settings.
- Instantly add and remove shipping rates or pickup locations with interactive confirmation prompts.

---

## Installation

1. Download the plugin folder and compress it into a `.zip` archive or upload it directly to your WordPress site.
2. Navigate to **Plugins > Add New > Upload Plugin** in your WordPress Admin dashboard.
3. Choose the ZIP file and click **Install Now**.
4. **Activate** the plugin.

---

## Configuration

1. Go to **WooCommerce > Settings > Shipping**.
2. Click on **Shipping Zones** and edit the zone where you want to add this method.
3. Click **Add shipping method**, select **Custom Shipping** from the dropdown, and add it.
4. Click on the added **Custom Shipping** method to configure settings:
   - **Enable/Disable**: Toggle the overall shipping method.
   - **Title**: Custom display title shown to customers at checkout.
   - **Local Pickup**: Toggle local pickup options globally.
   - **Shipping Rates Table**: Define country, postal code, weights, standard fee, and one-day fee.
   - **Local Pickup Locations Table**: Define pickup names, addresses, and associated pickup fees.

---

## Database Architecture

Upon activation, the plugin automatically creates two custom tables in your WordPress database to store configuration data:

### 1. `{wp_prefix}wc_custom_shipping_rates`
Stores conditional shipping rates:
- `id` (mediumint, Primary Key)
- `country` (varchar(2)) - Two-character country code (e.g. `US`, `GB`, `LK`).
- `postal_code` (varchar(10)) - Exact postal code or `*` wildcard.
- `min_weight` (decimal) - Minimum cart weight.
- `max_weight` (decimal) - Maximum cart weight.
- `standard_fee` (decimal) - Cost for standard shipping.
- `one_day_fee` (decimal) - Cost for express shipping.

### 2. `{wp_prefix}wc_custom_shipping_pickups`
Stores local pickup points:
- `id` (mediumint, Primary Key)
- `location_name` (varchar(255)) - Store name or identifier.
- `address` (text) - Street address.
- `country` (varchar(2))
- `city` (varchar(100))
- `postal_code` (varchar(20))
- `fee` (decimal) - Service fee for choosing pickup.

---

## Development

The plugin consists of two main files:
- **`custom-shiping.php`**: Core plugin bootstrap, database setup hook, backend admin rendering, shipping calculations logic, and AJAX handlers.
- **`admin.js`**: Client-side logic handling dynamic AJAX rows addition/deletion inside the WooCommerce settings page.
