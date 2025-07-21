# Changelog

All notable changes to the Chargily Pay CLI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Enhanced startup wizard with API key guidance and Chargily documentation links
- Intelligent application detection with loading states
- Comprehensive application management system (add, remove, update, switch)
- Silent API key validation during setup
- Post-deletion wizard trigger when removing all applications
- Cache management system for optimal performance
- ESC/Ctrl+C cancellation support throughout all interactive flows
- Smart delay logic for better user experience

## [1.2.0] - 2025-01-XX (Enhanced UX & App Management)

### Added
- Startup wizard with guided setup flow
- API key validation with guidance links to Chargily dashboard
- Multi-application CRUD operations with confirmation dialogs
- Application switching with validation and cache clearing
- Post-removal wizard trigger for seamless user experience
- Loading states and progress indicators
- Comprehensive error handling for empty states

### Enhanced
- Startup process now intelligently detects valid applications
- Configuration management with proper cache invalidation
- User experience with appropriate loading delays
- Application removal process with selection and confirmation
- Error messages and guidance throughout the CLI

### Fixed
- Main menu no longer appears after deleting all applications
- Configuration service handles empty application states gracefully
- API service initialization with proper null checks
- Command-level application detection across all operations
- Cache clearing when applications are added or removed

### Technical Improvements
- Enhanced ConfigurationService with validation methods
- Improved MainMenuCommand with application state detection
- Better error handling in ChargilyApiService initialization
- Optimized startup flow for faster user experience
- Comprehensive app management with proper state transitions

## [1.1.0] - 2024-XX-XX (Core Functionality)

### Added
- Initial release of Chargily Pay CLI
- Complete payment management system
- Customer management with CRUD operations
- Product and price catalog management
- Payment link system with analytics
- Multi-application support with test/live modes
- Interactive CLI menus using Laravel Zero
- Professional CLI UX with progress indicators
- Data export capabilities (CSV format)
- Real-time balance monitoring
- Advanced filtering and search capabilities
- Security features and live mode warnings
- Comprehensive error handling and logging

### Features

#### Payment Operations
- Create payments with validation and confirmation
- List payments with advanced filtering (status, date, customer)
- Check payment status with detailed information
- Export payment data to CSV format
- Interactive payment creation workflow

#### Customer Management
- Create, read, update, delete customers
- Advanced search by name, email, phone
- Customer payment history tracking
- Bulk operations and data export
- Address and metadata management

#### Product & Price Management
- Complete product catalog management
- Flexible pricing (one-time and recurring)
- Bulk price operations (activate/deactivate)
- Product-price relationship management
- Image and metadata support

#### Payment Links
- Create professional payment links
- Custom branding and themes
- Analytics dashboard with conversion tracking
- Sharing tools (QR codes, social templates, embed codes)
- Revenue and visitor tracking

#### System Features
- Multi-application architecture
- Test/Live mode switching with safety checks
- Secure API key management
- Configuration management system
- Balance monitoring with caching
- Professional CLI interface
- Error handling with user-friendly messages
- Export functionality across all modules

### Technical Implementation
- Built on Laravel Zero framework
- Uses Laravel Prompts for interactive input
- HTTP client with retry mechanisms
- Caching system for performance
- JSON-based configuration storage
- Service-oriented architecture
- Comprehensive API wrapper for Chargily Pay v2
- Exception handling with contextual messages

## [1.0.0] - 2024-01-XX (Initial Release)

### Added
- Initial stable release
- Full Chargily Pay v2 API integration
- Production-ready CLI tool
- Documentation and usage guides
- Security best practices implementation
- PHAR build support for standalone executables