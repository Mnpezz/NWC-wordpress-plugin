# ⚡ Nostr Login & Pay - WordPress Plugin

A WordPress plugin that enables **Nostr-based authentication** and **Lightning Network payments** for WooCommerce stores.

## ✨ Features

### 🔐 **Nostr Authentication**
- **One-Click Login**: Users log in using their Nostr browser extension (Alby, nos2x)
- **Auto Account Creation**: Automatically creates WordPress accounts for new Nostr users
- **Secure**: Server-side signature verification with time-limited auth events
- **No Passwords**: Eliminate password fatigue and security risks

### ⚡ **Lightning Network Payments**
- **Instant Payments**: Accept Bitcoin via Lightning Network (settles in seconds)
- **Multiple Payment Methods**:
  - Browser wallet (WebLN/nos2x) - One-click instant payment
  - QR code - Scan with any Lightning wallet
- **Auto-Verification**: Payments auto-complete in 3-6 seconds via NWC
- **Accurate Pricing**: Real-time BTC exchange rates from multiple reliable APIs

### 🎯 **Smart Payment Flow**
- **Browser Wallet Priority**: If customer has Alby/nos2x, show instant pay button
- **QR Code Fallback**: Universal payment method for any Lightning wallet
- **Automatic Detection**: Plugin adapts based on merchant's NWC configuration

---

## 🚀 Quick Start (2 Minutes!)

### **Prerequisites**
1. WordPress 5.8+ with WooCommerce installed
2. A [Coinos](https://coinos.io) account (free, instant setup)

### **Step 1: Install Plugin**
1. Upload `NWC-wordpress-plugin` folder to `/wp-content/plugins/`
2. Activate in WordPress admin → Plugins

### **Step 2: Configure Lightning Address**
1. Sign up at [coinos.io](https://coinos.io) (takes 30 seconds)
2. Your Lightning address is: `username@coinos.io`
3. Go to **Settings → Nostr Login & Pay → NWC Settings**
4. Paste your Lightning address → **Save Settings**

✅ **You're now accepting Lightning payments!**

### **Step 3: Enable Auto-Verification (Recommended)**

For QR code payments to auto-complete:

1. Go to [coinos.io](https://coinos.io) → **Settings → Plugins → NWC**
2. Create new connection with permission: `lookup_invoice`
3. Copy the connection string (starts with `nostr+walletconnect://`)
4. Paste in **NWC Connection (For Auto-Verification)** field → **Save**

🎉 **Done! QR payments now auto-complete in 3-6 seconds!**

---

## 💡 How It Works

### **For Customers**

#### **Browser Wallet Payment** (Recommended)
1. Click "⚡ Pay with Browser Wallet" at checkout
2. Alby/nos2x prompts for approval
3. Confirm payment
4. Order completes automatically ✅

#### **QR Code Payment** (Universal)
1. Scan QR code with any Lightning wallet (Zeus, Phoenix, Wallet of Satoshi, etc.)
2. Confirm payment in your wallet
3. Plugin detects payment via NWC
4. Order completes automatically ✅ (if NWC configured)

### **For Store Owners**

```
Order Created → Invoice Generated (via Lightning Address)
                        ↓
        ┌───────────────┴───────────────┐
        │                               │
 Browser Wallet                    QR Code
 (WebLN instant)               (Universal payment)
        │                               │
        ├─ Preimage returned            ├─ NWC checks wallet
        ├─ Auto-complete ✅             │   every 3 seconds
        │                               ├─ Payment detected
        │                               ├─ Auto-complete ✅
        └───────────────┬───────────────┘
                        │
                Order Complete! 🎉
```

---

## ⚙️ Configuration

### **General Settings** (Settings → Nostr Login & Pay → General)

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Nostr Login** | Allow users to log in with Nostr | ✅ Enabled |
| **Auto-create Accounts** | Create WordPress accounts for new Nostr users | ✅ Enabled |
| **Default User Role** | Role for new Nostr accounts | Customer |

### **NWC Payment Settings** (Settings → Nostr Login & Pay → NWC Settings)

| Setting | Description | Required |
|---------|-------------|----------|
| **Enable Payment Gateway** | Enable Lightning as payment method | ✅ Yes |
| **Lightning Address** | Your receiving address (`username@coinos.io`) | ✅ Yes |
| **NWC Connection** | For QR auto-verification (Coinos NWC string) | ⚠️ Optional* |

\* Without NWC: QR payments require manual "Mark as Paid" button.  
With NWC: QR payments auto-complete in seconds! 🚀

### **Advanced Tools**

**Clear BTC Price Cache** (NWC Settings page):
- Plugin caches Bitcoin prices for 5 minutes (performance)
- If pricing seems wrong, clear cache to fetch fresh rates
- Automatically tries 3 reliable APIs:
  1. CoinGecko (primary)
  2. Coinbase (backup)
  3. Blockchain.info (backup)

---

## 🔧 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **WooCommerce**: 6.0 or higher
- **Lightning Wallet**: Coinos (recommended) or any NWC-compatible wallet
- **Browser Extension** (for customers using browser wallet): Alby or nos2x

---

## 🌐 Browser Extensions

For **Nostr login** and **browser wallet payments**, users need:

- **[Alby](https://getalby.com/)** - Full-featured Lightning wallet + Nostr (Chrome, Firefox)
- **[nos2x](https://github.com/fiatjaf/nos2x)** - Nostr signer (Chrome)

Both support WebLN for instant Lightning payments!

---

## 🔐 Security

- ✅ **Server-Side Verification**: All Nostr login events verified server-side
- ✅ **Time-Limited Auth**: Auth events expire after 5 minutes
- ✅ **Read-Only NWC**: Auto-verification uses `lookup_invoice` (no spend permission)
- ✅ **No Private Keys**: Plugin never handles private keys
- ✅ **WordPress Security**: Integrates with standard WordPress user management

---

## 📋 Payment Methods Comparison

| Method | Speed | Compatibility | Auto-Complete | Setup |
|--------|-------|---------------|---------------|-------|
| **Browser Wallet** | ⚡ Instant | Requires extension | ✅ Always | None |
| **QR Code** | ⚡ Fast | 📱 Universal | ✅ With NWC | 2 min |

**Recommendation**: Configure NWC for full auto-verification on all payment methods!

---

## 🛠️ Troubleshooting

### **"Lightning (NWC)" not showing in checkout**
1. Check: Settings → Nostr Login & Pay → NWC Settings
2. Ensure "Enable Payment Gateway" is checked
3. Verify Lightning Address is filled in
4. Go to: WooCommerce → Settings → Payments
5. Enable "Lightning (NWC)"

### **QR payments not auto-completing**
1. Check: Is NWC Connection configured?
2. Go to: Settings → Nostr Login & Pay → NWC Settings
3. Look for: "✓ NWC Auto-Verification Enabled!" message
4. If not: Follow Step 3 in Quick Start above

### **Wrong satoshi amounts**
1. Go to: Settings → Nostr Login & Pay → NWC Settings
2. Scroll to: "🔧 Advanced Tools"
3. Click: "🔄 Clear BTC Price Cache"
4. Next order will fetch fresh exchange rates

### **Browser wallet not detected**
1. Install [Alby](https://getalby.com/) or [nos2x](https://github.com/fiatjaf/nos2x)
2. Ensure extension is enabled in browser
3. Refresh the payment page
4. Check browser console for errors

### **Login button not appearing**
1. Ensure "Enable Nostr Login" is checked (Settings → General)
2. Clear WordPress cache
3. Check that WooCommerce is active

---

## 📁 File Structure

```
NWC-wordpress-plugin/
├── nostr-login-and-pay.php              # Main plugin file
├── includes/
│   ├── class-nostr-auth.php             # Nostr authentication
│   ├── class-admin-settings.php         # Admin settings page
│   ├── class-lnurl-service.php          # LNURL invoice generation
│   ├── class-nwc-wallet.php             # NWC connection parsing
│   └── woocommerce/
│       ├── class-wc-gateway-nwc.php     # Payment gateway
│       └── class-wc-gateway-nwc-blocks-support.php # Blocks support
├── assets/
│   ├── js/
│   │   ├── frontend.js                  # Nostr login
│   │   ├── nwc-payment.js               # Payment UI & NWC verification
│   │   └── checkout.js                  # WooCommerce checkout
│   └── css/
│       ├── frontend.css                 # Frontend styles
│       └── admin.css                    # Admin styles
└── README.md                            # This file
```

---

## 🔄 Changelog

### **Version 1.0.0** (Current)
- ✅ Nostr authentication (NIP-42 style)
- ✅ Lightning Address invoice generation (LNURL protocol)
- ✅ NWC auto-verification via Alby SDK `lookup_invoice`
- ✅ Browser wallet payments (WebLN)
- ✅ QR code payments with auto-complete
- ✅ Multi-API BTC price fetching (CoinGecko, Coinbase, Blockchain.info)
- ✅ Smart payment display (adapts to NWC configuration)
- ✅ WooCommerce Blocks support
- ✅ Admin tools (BTC cache clearing)
- ✅ nostr-tools v1.17.0 + Alby SDK v3.6.1 compatibility

---

## 💻 Technical Details

### **Payment Verification Architecture**

**Frontend (JavaScript)**:
- Uses **Alby SDK v3.6.1** with **nostr-tools v1.17.0**
- NWC `lookup_invoice` method checks payment status
- Polls merchant's Coinos wallet every 3 seconds
- Auto-completes order on successful payment detection

**Backend (PHP)**:
- LNURL protocol for invoice generation from Lightning Address
- Simple order status checks (verification happens on frontend)
- WooCommerce HPOS compatible

### **Supported NWC Methods**
- `lookup_invoice` - Check invoice payment status (used for auto-verification)

### **Browser Compatibility**
- Tested with: Chrome, Firefox, Brave
- Requires: JavaScript enabled, Nostr extension (Alby/nos2x)

---

## 🤝 Support & Contributing

For issues or questions:
1. Check this README thoroughly
2. Review WordPress error logs (`wp-content/debug.log`)
3. Check browser console for JavaScript errors
4. Verify Coinos account is active and funded

**Useful Links**:
- [Nostr Protocol](https://github.com/nostr-protocol/nostr)
- [NWC Documentation](https://docs.nwc.dev/)
- [Coinos](https://coinos.io/)
- [Alby SDK](https://github.com/getAlby/js-sdk)

---

## 📜 License

GPL v2 or later

---

## 🙏 Credits

Built with:
- [nostr-tools v1.17.0](https://github.com/nbd-wtf/nostr-tools) - Nostr protocol implementation
- [Alby SDK v3.6.1](https://github.com/getAlby/js-sdk) - NWC client for payment verification
- [Nostr Wallet Connect](https://nwc.dev/) - Payment protocol specification
- [LNURL](https://github.com/lnurl/luds) - Lightning Address invoice generation

Special thanks to the Coinos, Nostr, and Lightning Network communities! ⚡🤙

---

**Made with ⚡ and 🧡 for the Bitcoin Lightning Network**
