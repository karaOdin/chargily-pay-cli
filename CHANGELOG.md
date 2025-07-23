# Changelog

All notable changes to the Chargily Pay CLI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.11] - 2025-07-23

### Added
- "Press Enter to continue" prompts after displaying command results
- User-friendly pauses after payment creation to show payment links
- Confirmation prompts after CSV exports to display file locations
- Result display improvements for customer, product, and payment link creation
- Better user experience with time to read important information

### Fixed
- Commands no longer immediately return to menu after showing results
- Payment creation now properly displays payment URLs with pause
- CSV export operations show file paths and wait for user confirmation
- All creation commands (customers, products, links) now pause after displaying results

## [1.0.10] - 2025-07-23

### Fixed
- PHAR logging issues by using system temp directory instead of internal storage
- Windows execution errors when running PHAR files
- Laravel log file creation in read-only PHAR environment

## [1.0.9] - 2025-07-22

### Added
- Platform-specific PHAR builds for optimal cross-platform experience
- Windows-specific build without interactive menu dependencies (eliminates POSIX errors)
- Linux/macOS builds retain full interactive menu functionality
- Separate composer configuration for Windows builds (`composer-windows.json`)

### Enhanced
- GitHub Actions workflow with dual build process
- Clean dependency separation between platforms
- Improved Windows user experience with zero errors

## [1.0.8] - 2025-07-22

### Fixed
- GitHub Actions release workflow permissions for asset uploads
- Release asset upload failures in automated builds
- Missing GITHUB_TOKEN environment variable in workflow

## [1.0.7] - 2025-07-22

### Added
- Standalone PHAR installation method for simplified deployment
- Cross-platform installation scripts (Windows batch and Unix shell)
- Direct download instructions for PHAR executables
- Comprehensive installation documentation

### Enhanced
- README with multiple installation options
- User-friendly installation process
- Platform-specific installation guidance

## [1.0.6] - 2025-07-22

### Fixed
- Interactive menu restoration for Linux and macOS platforms
- Windows compatibility while preserving menu functionality on Unix systems
- Composer dependency management for cross-platform support

### Enhanced
- Smart platform detection for menu availability
- Graceful fallback to command list on Windows
- Maintained full interactive experience on supported platforms

## [1.0.5] - 2025-07-22

### Added
- Windows platform compatibility and detection
- Automatic fallback to command list when interactive menus unavailable
- Cross-platform executable building with GitHub Actions

### Fixed
- POSIX extension requirements on Windows systems
- Interactive menu crashes on non-Unix platforms
- Build process compatibility across different operating systems

## [1.0.4] - 2025-07-22

### Fixed
- PHP code style violations across 29 files
- Laravel Pint formatting issues
- GitHub Actions linter compliance

### Enhanced
- Code quality and consistency
- Automated formatting standards
- Professional codebase presentation

## [1.0.3] - 2025-07-22

### Fixed
- GitHub Actions workflow compatibility issues
- Build process failures on different PHP versions
- Deprecated action versions in CI pipeline

### Enhanced
- Modern GitHub Actions workflow
- Cross-platform build reliability
- Automated release process improvements

## [1.0.2] - 2025-07-22

### Added
- PHAR executable building capability
- Box compiler integration for standalone distribution
- Executable packaging for easy deployment

### Enhanced
- Distribution options for end users
- Simplified installation process
- Self-contained application packaging

## [1.0.1] - 2025-07-22

### Fixed
- Composer dependency resolution issues
- Package installation conflicts
- Runtime dependency management

## [1.0.0] - 2025-07-22

### Added
- Initial release of Chargily Pay CLI
- Complete payment management functionality
- Multi-application support with secure configuration
- Interactive startup wizard with guided setup
- Comprehensive API integration for Chargily Pay v2
- Customer, product, and payment link management
- CSV export capabilities for all data types
- Real-time balance monitoring across currencies
- Test and live mode switching with safety warnings
- Professional CLI interface with colored output
- Secure local configuration storage
- Cache management for optimal performance
- Comprehensive error handling and user guidance

### Features
- **Payment Operations**: Create, list, and monitor payment status
- **Customer Management**: Full CRUD operations with export functionality
- **Product Management**: Product and pricing management with bulk operations
- **Payment Links**: Create and manage reusable payment links
- **Multi-Application**: Manage multiple Chargily applications seamlessly
- **Export System**: CSV export for payments, customers, products, and links
- **Security**: Encrypted configuration, live mode warnings, API key validation
- **User Experience**: Interactive menus, progress indicators, ESC shortcuts
- **Cross-Platform**: Windows, Linux, and macOS support