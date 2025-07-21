# 📦 Publishing Guide: Chargily Pay CLI

This guide covers how to publish and distribute the Chargily Pay CLI as a standalone executable and through package managers.

## 🏗️ Build Process

### 1. Build Standalone Executable

The CLI uses Laravel Zero with Box to create a standalone PHAR file:

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Build the PHAR executable
./vendor/bin/box compile

# This creates the executable: builds/chargily
```

### 2. Test the Build

```bash
# Test the built executable
./builds/chargily --version
./builds/chargily balance
./builds/chargily payment:list
```

## 📋 Pre-Release Checklist

- [ ] All tests pass: `./vendor/bin/pest`
- [ ] Code linting: `./vendor/bin/pint`
- [ ] Update version in `config/app.php`
- [ ] Update `CHANGELOG.md` with new features
- [ ] Build executable and test core functionality
- [ ] Test startup wizard with new installations
- [ ] Test application management (add/remove/switch)
- [ ] Verify API key validation works

## 🚀 Distribution Methods

### 1. GitHub Releases

Create releases directly on GitHub with pre-built binaries:

```bash
# Tag the release
git tag -a v1.2.0 -m "Release v1.2.0 - Enhanced UX and App Management"
git push origin v1.2.0

# Upload to GitHub Release:
# - builds/chargily (Linux/macOS)
# - chargily.exe (if Windows build available)
```

**Release Assets to Upload:**
- `chargily` - Main executable
- `chargily.tar.gz` - Compressed executable
- Source code archives (auto-generated)

### 2. Homebrew (macOS/Linux)

Create a Homebrew formula for easy installation:

**Step 1:** Fork `homebrew-core` or create your own tap
**Step 2:** Create formula file `chargily-pay-cli.rb`:

```ruby
class ChargilyPayCli < Formula
  desc "Professional CLI for Chargily Pay - Algeria's payment gateway"
  homepage "https://github.com/karaOdin/chargily-pay-cli"
  url "https://github.com/karaOdin/chargily-pay-cli/releases/download/v1.2.0/chargily.tar.gz"
  sha256 "YOUR_SHA256_HERE"
  version "1.2.0"

  def install
    bin.install "chargily"
  end

  test do
    assert_match "Chargily Pay CLI", shell_output("#{bin}/chargily --version")
  end
end
```

**Installation for users:**
```bash
brew install chargily-pay-cli
```

### 3. Direct Download

Host the executable for direct download:

```bash
# Users can download and install directly
curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily -o chargily
chmod +x chargily
sudo mv chargily /usr/local/bin/
```

### 4. Docker Container

Create a Docker image for containerized usage:

**Dockerfile:**
```dockerfile
FROM php:8.2-cli-alpine

# Install required extensions
RUN apk add --no-cache git zip unzip
RUN docker-php-ext-install bcmath

# Copy application
COPY . /app
WORKDIR /app

# Install dependencies and build
RUN composer install --no-dev --optimize-autoloader
RUN ./vendor/bin/box compile

# Make executable available
RUN cp builds/chargily /usr/local/bin/chargily
RUN chmod +x /usr/local/bin/chargily

ENTRYPOINT ["chargily"]
```

**Usage:**
```bash
docker build -t chargily-pay-cli .
docker run -it chargily-pay-cli balance
```

### 5. Package Managers

#### npm (for broader reach)
```bash
# Create package.json for npm distribution
npm publish
```

#### snap (Ubuntu/Linux)
```bash
# Create snapcraft.yaml
snapcraft
snap install chargily-pay-cli
```

## 📄 Documentation for Users

### Installation Instructions

**Quick Install (Recommended):**
```bash
curl -L https://github.com/karaOdin/chargily-pay-cli/releases/latest/download/chargily -o chargily
chmod +x chargily
sudo mv chargily /usr/local/bin/
```

**Verify Installation:**
```bash
chargily --version
```

**First Run:**
```bash
chargily
# Follows setup wizard to configure API keys
```

### Key Features to Highlight

- **🚀 Zero Configuration**: Guided setup wizard
- **🔒 Secure**: API keys stored locally, encrypted
- **🌍 Algeria-Focused**: Native DZD support, EDAHABIA/CIB
- **⚡ Fast**: Cached responses, optimized performance
- **🛠️ Professional**: Multi-application management
- **📱 Modern UX**: Interactive menus, colored output

### Basic Usage Examples

```bash
# Check account balance
chargily balance

# Create a payment
chargily payment:create --amount=2500 --description="Order #123"

# List recent payments
chargily payment:list

# Switch between test/live modes
chargily mode:switch

# Manage multiple applications
chargily configure
```

## 🔧 Build Configuration

### Box Configuration (`box.json`)

```json
{
    "alias": "chargily",
    "main": "chargily",
    "output": "builds/chargily",
    "compression": "GZ",
    "compactors": [
        "KevinGH\\Box\\Compactor\\Php"
    ],
    "directories": ["app", "bootstrap", "config", "vendor"],
    "files": ["composer.json"],
    "exclude-composer-files": false,
    "exclude-dev-files": true
}
```

### Version Management

Update version in `config/app.php`:
```php
'version' => '1.2.0',
```

## 🎯 Marketing & Distribution

### Target Audience
- **Algerian businesses** using Chargily Pay
- **Developers** integrating payment systems
- **E-commerce platforms** needing payment CLI
- **DevOps teams** automating payment workflows

### Distribution Channels
1. **GitHub Releases** (Primary)
2. **Packagist** (Composer)
3. **Homebrew** (macOS/Linux users)
4. **Docker Hub** (Containerized usage)
5. **Chargily Documentation** (Official endorsement)

### SEO Keywords
- Algeria payment CLI
- Chargily Pay command line
- EDAHABIA CIB payments
- DZD payment processing
- Algeria e-commerce tools

## 📊 Analytics & Tracking

Track adoption through:
- GitHub release downloads
- Package manager installations
- Docker pulls
- API usage patterns (aggregated, anonymous)

## 🔄 Update Mechanism

Implement update checking:
```bash
# Check for updates
chargily update --check

# Auto-update (if available)
chargily update --install
```

## 💬 Community & Support

- **Issues**: GitHub Issues for bugs/features
- **Discussions**: GitHub Discussions for Q&A
- **Documentation**: Comprehensive README and wiki
- **Examples**: Sample configurations and workflows

---

This CLI is production-ready for distribution and will provide Algerian businesses with a professional, efficient tool for managing Chargily Pay payments.