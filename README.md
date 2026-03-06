# Shiprocket Pro Enhanced for WooCommerce

A custom WooCommerce shipping plugin built to replace the official Shiprocket plugin, which lacked real-time delivery estimates, proper express/standard separation, and a meaningful checkout experience for Indian customers.

---

## 🚀 Key Features

- **Real-time Serviceability Check** — Pincode checker on product pages with instant API-powered delivery availability.
- **Express & Standard Separation** — Dynamically identifies air (express) vs. surface (standard) couriers from the Shiprocket API.
- **Smart EDD Calculation** — Delivery estimates factor in your internal processing time (printing, packing, label generation), a 5 PM order cutoff rule, and Sunday-skipping business day logic.
- **Date Range for Standard Delivery** — Standard shipping shows a realistic range (e.g. "17–20 Mar") instead of a falsely precise single date.
- **Blue Dart Air Awareness** — Express delivery is tied to air couriers; if unavailable for a pincode, the option is greyed out and disabled rather than hidden.
- **Auto City/State Fill** — Entering a pincode on checkout auto-populates the city and state fields via the same API call.
- **Free Shipping Threshold Notice** — Dynamic banner on cart and checkout showing how much more is needed to unlock free shipping.
- **Fine Print Disclaimer** — Small, honest delivery estimate disclaimer shown only in the order summary, not cluttering the shipping option list.
- **Separate Admin Controls** — Independent buffer day settings for express and standard shipping, configurable cutoff time, and per-method base charges.

---

## 🛠 Installation

1. Download or clone this repository.
2. Upload the `shiprocket-pro` folder to `/wp-content/plugins/`.
3. Activate the plugin from **Plugins > Installed Plugins**.
4. Go to **WooCommerce > Shiprocket Pro** to configure your API credentials and shipping rules.

---

## ⚙️ Configuration

All settings live under **WooCommerce → Shiprocket Pro** in the WordPress admin.

### API Configuration
| Field | Description |
|---|---|
| API Email | Your Shiprocket account email |
| API Password | Your Shiprocket account password |
| Pickup Pincode | Your warehouse/origin pincode |

### Pricing & Threshold Logic
| Field | Description |
|---|---|
| Free Shipping Minimum Order (₹) | Cart subtotal above which shipping is free |
| Standard Base Charge (₹) | Flat fee for standard shipping |
| Enable Express Delivery | Toggle to show/hide the express option |
| Express Base Charge (₹) | Flat fee for express shipping |

### Delivery Estimate Rules
| Field | Description |
|---|---|
| Same-Day Processing Cutoff Time | Orders after this time are processed next day (default: 17:00) |
| Standard Delivery Extra Buffer (Business Days) | Your internal processing time for standard orders (default: 2) |
| Express Delivery Extra Buffer (Business Days) | Your internal processing time for express orders (default: 1) |

---

## 🧠 How EDD is Calculated

The estimated delivery date is built in two steps, separately for express and standard:

```
Final EDD = Today
          + Your buffer (business days, Sundays skipped)
          + Cutoff extra (1 day if order placed after cutoff time)
          + Courier transit days from Shiprocket API (calendar days)
```

**Standard** shows a date range (earliest + 3 days) to honestly represent natural courier variance without over-promising.

**Express** shows a single date as it's a more predictable service.

---

## 📁 File Structure

```
shiprocket-pro/
├── shiprocket-pro.php                        # Plugin bootstrap, admin menu, settings registration
├── includes/
│   ├── class-sr-api-handler.php              # Shiprocket API authentication, serviceability, EDD logic
│   ├── class-wc-shipping-shiprocket-pro.php  # WooCommerce shipping method (rate calculation)
│   └── frontend-hooks.php                    # Pincode checker UI, AJAX handlers, checkout label filter
└── assets/
    ├── css/
    │   └── shiprocket-pro.css                # Frontend styles (pincode checker, checkout labels)
    └── js/
        └── shiprocket-enhanced.js            # Pincode checker JS, checkout autofill, session restore
```

---

## 🔒 Security

- All API credentials are stored in the WordPress options table via `register_setting()` — never hardcoded.
- AJAX endpoints are protected with WordPress nonces (`wp_create_nonce` / `check_ajax_referer`).
- All user inputs are sanitized with `sanitize_text_field()` and validated with regex before use.
- All outputs are escaped with `esc_html()` / `wp_kses_post()`.
- API tokens are cached in transients server-side only — never exposed to the browser.

---

## 🔍 Project Spotlight: Solving the Shiprocket UX Gap

### The Problem

While managing a WooCommerce store that ships physical photo prints across India, I ran into significant limitations with the official Shiprocket plugin:

- No pincode check on product pages — customers had no idea if their area was serviceable before adding to cart.
- Express and standard delivery were not distinguished — the plugin showed all couriers the same way.
- EDD calculation didn't account for internal processing time (printing, packing, label generation).
- Checkout labels were plain text with no delivery date shown.
- No honest handling of "express not available" — the option either showed wrong dates or was silently hidden.

### The Solution

I built a complete replacement plugin with a clear separation of concerns:

- **API Layer** (`class-sr-api-handler.php`) handles authentication, serviceability checks, courier classification (air vs. surface), and EDD arithmetic — independently testable.
- **Shipping Method** (`class-wc-shipping-shiprocket-pro.php`) keeps rate registration clean by using plain-text labels and passing EDD data through WooCommerce rate metadata, avoiding the HTML-stripping issue that breaks most custom label attempts.
- **Frontend Layer** (`frontend-hooks.php`) uses the `woocommerce_cart_shipping_method_full_label` filter to inject styled HTML only after WooCommerce processes the rate — the only reliable approach that survives page builder templates like ShopLentor.

### Technical Highlights

- **Business Day Logic** — Custom `add_business_days()` method that advances a timestamp day-by-day, skipping Sundays, reflecting the store's actual no-print-on-Sunday policy.
- **Cutoff Time Rule** — Orders placed after 5 PM are treated as next-day starts, adding 1 day to the processing buffer automatically.
- **Greyed-out Unavailable Express** — When no air courier serves a pincode, the express option is still rendered but visually disabled via CSS and the radio input is programmatically prevented from being selected via JS — no silent hiding.
- **Label HTML Injection** — WooCommerce strips HTML from `add_rate()` labels. The solution is to store the EDD in rate `meta_data` and inject styled HTML through the `woocommerce_cart_shipping_method_full_label` filter, which runs after WooCommerce's own processing.
- **Cache Strategy** — Serviceability results are cached per pincode in WordPress transients for 12 hours, preventing repeated API calls for the same location. Cache keys are versioned to force fresh data after structural changes.
- **Session Persistence** — Pincode, city, and state are stored in `sessionStorage` so checkout fields repopulate if a customer navigates away and returns.

### Impact

- Customers see delivery estimates before they even add to cart — reducing checkout abandonment from location uncertainty.
- Express availability is honestly communicated per pincode rather than showing wrong dates or hiding the option.
- Internal processing time and Sunday non-working days are baked into every estimate, making dates genuinely reliable.
- Zero hardcoded credentials — the plugin is safely shareable and reusable across any store using Shiprocket.

---

## 👨‍💻 Author

Developed by **Sarfaraz Akhtar**

Built with Generative AI orchestration and manual logic refinement — AI handled boilerplate scaffolding while architecture decisions, debugging, API mapping, and business logic were driven manually.

---

## 📄 License

This plugin is shared for reference and learning purposes. Feel free to fork and adapt for your own store.
