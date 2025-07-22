# 🚀 Chargily Pay CLI

Professional command-line interface for **Chargily Pay** - Algeria's leading payment gateway. Built for developers, businesses, and DevOps teams who need efficient payment management tools.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.1-blue)](https://php.net)
[![Laravel Zero](https://img.shields.io/badge/Laravel%20Zero-10.x-red)](https://laravel-zero.com)

## ✨ Features

### 🎯 Core Payment Operations
- **💳 Payment Creation** - Create secure checkout links with validation
- **📋 Payment Management** - List, filter, and track payment status
- **💰 Balance Monitoring** - Real-time account balance across currencies
- **📊 Export Capabilities** - CSV export for reporting and analysis

### 🏢 Multi-Application Support
- **🔄 App Switching** - Manage multiple Chargily applications seamlessly
- **🧪 Test/Live Modes** - Safe testing environment with live mode protection
- **⚙️ Configuration Management** - Secure API key storage and management
- **🎛️ Guided Setup** - Interactive wizard for first-time configuration

### 🎨 Professional UX
- **🖥️ Interactive Menus** - Beautiful CLI interface with colored output
- **⚡ Smart Caching** - Optimized performance with intelligent caching
- **🔒 Security First** - Encrypted local storage, live mode warnings
- **📱 Modern Design** - Progress indicators, loading states, ESC shortcuts

## 🛠️ Quick Start

### 📥 Installation (Super Easy!)

**🎯 One-Line Install (Recommended)**

**Windows:**
```cmd
curl -o install.bat https://raw.githubusercontent.com/karaOdin/chargily-pay-cli/main/install.bat && install.bat
```

**Linux/macOS:**
```bash
curl -fsSL https://raw.githubusercontent.com/karaOdin/chargily-pay-cli/main/install.sh | bash
```

**Manual Installation (Advanced Users)**
```bash
# Install via Composer
composer global require karaodin/chargily-pay-cli

# Run directly:
~/.config/composer/vendor/bin/chargily  # Linux/macOS
php "%APPDATA%\Composer\vendor\karaodin\chargily-pay-cli\chargily"  # Windows
```

**⚡ Method 2: Direct Executable Download**

**Linux:**
```bash
curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily-linux -o chargily
chmod +x chargily
sudo mv chargily /usr/local/bin/
chargily  # Ready to use!
```

**macOS:**
```bash
curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily-macos -o chargily
chmod +x chargily
sudo mv chargily /usr/local/bin/
chargily  # Ready to use!
```

**Windows:**
```cmd
# Download and run directly (no setup needed!)
curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily-windows.phar -o chargily.phar
php chargily.phar  # Works immediately

# Or add to PATH for global access
move chargily.phar C:\Windows\System32\
chargily.phar  # Use anywhere
```

**🐳 Method 3: Docker**
```bash
docker run -it ghcr.io/karaodin/chargily-pay-cli:latest
```

> **✅ Zero Configuration**: No environment variables, PHP extensions, or complex setup! The CLI automatically:
> - Stores config in `~/.chargily/` folder
> - Shows startup wizard for first-time users  
> - Works on Windows, Linux, and macOS
> - Falls back to individual commands if interactive menus aren't supported

### First Run

```bash
chargily
```

The CLI will guide you through the setup process:
1. 🎯 **Welcome screen** with feature overview
2. 🔑 **API Key Setup** with links to Chargily dashboard
3. 🧪 **Test Environment** configuration (recommended first)
4. ✅ **Ready to use** - start managing payments!

## 📖 Usage Examples

### Basic Commands

```bash
# Check account balance
chargily balance

# Create a new payment
chargily payment:create

# List recent payments
chargily payment:list

# Check specific payment status
chargily payment:status ch_123456789
```

### Advanced Operations

```bash
# Filter payments by status
chargily payment:list --status=paid --limit=50

# Export payment data to CSV
chargily payment:list --export=payments_2024.csv

# Switch between applications
chargily configure

# Switch between test/live modes
chargily mode:switch
```

### Interactive Mode

```bash
# Launch interactive menu
chargily menu

# Available options:
# 💳 Create Payment
# 📋 List Recent Payments  
# 🔍 Check Payment Status
# 💰 Check Balance
# ⚙️ Configuration
# 🔄 Switch Mode
```

## 🏗️ For Developers

### API Coverage

Full support for Chargily Pay v2 API:

- ✅ **Payments** - Create, retrieve, list with filtering
- ✅ **Balance** - Multi-currency balance checking
- ✅ **Customers** - Full CRUD operations
- ✅ **Products** - Catalog management
- ✅ **Payment Links** - Professional link creation
- ✅ **Webhooks** - Configuration and testing

### Configuration

The CLI stores configuration in `~/.chargily/`:
```
~/.chargily/
├── applications.json    # Application configurations
├── cache/              # API response cache
└── logs/               # Error and activity logs
```

### Environment Variables

```bash
export CHARGILY_DEFAULT_APP=my_business
export CHARGILY_GLOBAL_MODE=test  # Force test mode globally
export CHARGILY_CACHE_TIMEOUT=300 # Cache timeout in seconds
```

### Integration Examples

**Payment Creation Workflow:**
```bash
#!/bin/bash
# Create payment for order
PAYMENT_ID=$(chargily payment:create \
  --amount=2500 \
  --description="Order #${ORDER_ID}" \
  --success-url="https://mystore.dz/success" \
  --format=json | jq -r '.id')

echo "Payment created: $PAYMENT_ID"
```

**Balance Monitoring:**
```bash
#!/bin/bash
# Monitor balance and alert if low
BALANCE=$(chargily balance --format=json | jq -r '.dzd.balance')
if [ "$BALANCE" -lt 10000 ]; then
  echo "⚠️ Low balance alert: ${BALANCE} DZD"
fi
```

## 🌍 Algeria-Focused Features

### Currency Support
- **🇩🇿 DZD (Algerian Dinar)** - Primary currency with native formatting
- **💵 USD, EUR** - Multi-currency support for international transactions

### Payment Methods
- **💳 EDAHABIA** - Algeria's national payment card
- **🏦 CIB** - Banque CIB cards
- **📱 Mobile Payments** - Integration ready

### Localization
- **🕐 Algeria Timezone** - Automatic timezone handling (UTC+1)
- **📅 Date Formats** - Localized date and time formatting
- **💱 Amount Display** - Proper DZD formatting with separators

## 🔧 Advanced Configuration

### Multi-Application Setup

```bash
# Add new application
chargily configure

# Switch between applications
chargily app:switch

# Remove application
chargily configure --remove=old_app
```

### Security Best Practices

```bash
# Always start with test mode
chargily mode:switch test

# Verify API keys
chargily configure --test-connection

# Enable additional safety checks
export CHARGILY_LIVE_MODE_CONFIRM=true
```

## 📊 Monitoring & Analytics

### Built-in Reporting
```bash
# Payment summary with statistics
chargily payment:list --summary

# Export for external analysis
chargily payment:list --export=monthly_report.csv --from=2024-01-01
```

### Performance Metrics
- **⚡ Fast Response** - Cached API responses
- **📈 Success Rate** - Built-in retry mechanisms  
- **🔄 Auto-Recovery** - Network failure handling

## 🚀 Production Deployment

### CI/CD Integration

**GitHub Actions Example:**
```yaml
name: Payment Processing
on:
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours

jobs:
  check-payments:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Install Chargily CLI
        run: |
          curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily -o chargily
          chmod +x chargily
          sudo mv chargily /usr/local/bin/
      - name: Check Payment Status
        run: chargily payment:list --status=pending --format=json
        env:
          CHARGILY_API_KEY: ${{ secrets.CHARGILY_API_KEY }}
```

### Docker Deployment

```dockerfile
FROM ghcr.io/karaodin/chargily-pay-cli:latest
COPY payment-processor.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/payment-processor.sh
CMD ["payment-processor.sh"]
```

## 👨‍💻 About the Developer

**Chawki Mahdi**
- 🚀 **CEO** of [DevCloud](https://devcloud.dz) - Leading Algerian development company
- ⚡ **CTO** of [Chargily](https://chargily.com) - Algeria's premier payment gateway
- 💡 **Vision**: Empowering Algeria's digital economy with cutting-edge payment solutions
- 🇩🇿 **Mission**: Building world-class fintech tools for Algerian businesses

### DevCloud Company
[DevCloud](https://devcloud.dz) specializes in:
- 🏗️ **Enterprise Software Development**
- 🔒 **Fintech Solutions & Payment Systems**  
- 🌐 **Digital Transformation Consulting**
- ⚡ **High-Performance CLI Tools & APIs**

*This CLI represents our commitment to providing Algerian businesses with professional-grade tools that rival international standards.*

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/karaOdin/chargily-pay-cli.git
cd chargily-pay-cli
composer install
./chargily --version
```

### Building

```bash
# Build PHAR executable
./vendor/bin/box compile

# Test the build
./builds/chargily --version
```

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🔗 Links

- **🌐 Chargily Pay** - [https://chargily.com](https://chargily.com)
- **📖 API Documentation** - [https://dev.chargily.com](https://dev.chargily.com)
- **🎫 Get API Keys** - [https://pay.chargily.dz/developer](https://pay.chargily.dz/developer)
- **🐛 Report Issues** - [GitHub Issues](https://github.com/karaOdin/chargily-pay-cli/issues)

## 📞 Support

- **💬 GitHub Discussions** - Community Q&A
- **🐛 GitHub Issues** - Bug reports and feature requests
- **📧 Email** - support@chargily.com (for Chargily-related questions)

---

<div align="center">

**🇩🇿 Made for Algeria's Digital Economy**

*Empowering businesses with professional payment tools*

</div>