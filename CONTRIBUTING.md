# Contributing to Chargily Pay CLI

Thank you for your interest in contributing to Chargily Pay CLI! This guide will help you get started with contributing to the project.

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git
- A Chargily Pay account for testing

### Development Setup

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/YOUR_USERNAME/chargily-pay-cli.git
   cd chargily-pay-cli
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up configuration**
   ```bash
   # Run the configuration wizard
   php chargily configure
   ```

4. **Test the installation**
   ```bash
   php chargily --version
   php chargily balance
   ```

## 🛠️ Development Workflow

### Code Style

We use [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
# Format your code
vendor/bin/pint

# Check formatting without fixing
vendor/bin/pint --test
```

### Testing

We use [Pest](https://pestphp.com/) for testing:

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Unit/ApiServiceTest.php

# Run with coverage
vendor/bin/pest --coverage
```

### Code Architecture

The CLI follows a service-oriented architecture:

- **Commands** (`app/Commands/`) - CLI command handlers
- **Services** (`app/Services/`) - Business logic and API communication
- **Exceptions** (`app/Exceptions/`) - Custom exception classes
- **Config** (`config/`) - Configuration files

### Adding New Commands

1. **Create the command**
   ```bash
   php chargily make:command YourNewCommand
   ```

2. **Implement the command logic**
   - Add constructor injection for required services
   - Implement the `handle()` method
   - Add appropriate error handling
   - Include progress indicators and user feedback

3. **Register the command**
   Commands are automatically registered if they extend `LaravelZero\Framework\Commands\Command`

4. **Add tests**
   ```bash
   php chargily make:test YourNewCommandTest
   ```

### API Integration Guidelines

When adding new API endpoints:

1. **Add method to ChargilyApiService**
   ```php
   public function newApiMethod(array $data): array
   {
       $response = $this->makeRequest('POST', '/endpoint', $data);
       return $response->json();
   }
   ```

2. **Add appropriate error handling**
   - Use `ChargilyApiException` for API errors
   - Provide user-friendly error messages
   - Include suggested actions for common errors

3. **Add caching if appropriate**
   - Use caching for expensive operations
   - Respect cache TTL settings
   - Provide cache invalidation mechanisms

## 📝 Contribution Guidelines

### Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Write clean, well-documented code
   - Follow existing code patterns
   - Add appropriate tests
   - Update documentation if needed

3. **Test your changes**
   ```bash
   vendor/bin/pest
   vendor/bin/pint --test
   ```

4. **Commit your changes**
   ```bash
   git add .
   git commit -m "feat: add new feature description"
   ```

5. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   # Create PR on GitHub
   ```

### Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` - New features
- `fix:` - Bug fixes
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `test:` - Adding or modifying tests
- `chore:` - Maintenance tasks

Examples:
```
feat: add payment link analytics command
fix: resolve API timeout issue in balance command
docs: update README with new command examples
```

### Code Review Criteria

Your PR will be reviewed for:

- **Functionality** - Does it work as intended?
- **Code Quality** - Is it clean, readable, and maintainable?
- **Testing** - Are there appropriate tests?
- **Documentation** - Is it properly documented?
- **Security** - Does it follow security best practices?
- **Performance** - Is it efficient?

## 🐛 Bug Reports

When reporting bugs, please include:

1. **Clear description** of the problem
2. **Steps to reproduce** the issue
3. **Expected behavior** vs actual behavior
4. **Environment information**:
   - PHP version
   - Operating system
   - CLI version
   - Chargily API mode (test/live)

## 💡 Feature Requests

For feature requests, please provide:

1. **Clear description** of the feature
2. **Use case** - Why is this needed?
3. **Proposed implementation** (if you have ideas)
4. **Examples** of how it would work

## 🚨 Security Issues

**Do not report security vulnerabilities publicly.**

Instead, please email security issues to: [security@example.com]

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if known)

## 📚 Resources

### Documentation
- [Chargily Pay API Documentation](https://dev.chargily.com)
- [Laravel Zero Documentation](https://laravel-zero.com)
- [Laravel Prompts Documentation](https://laravel.com/docs/prompts)

### Development Tools
- [Pest Testing Framework](https://pestphp.com)
- [Laravel Pint Code Formatting](https://laravel.com/docs/pint)
- [PHPStan Static Analysis](https://phpstan.org)

## 🎉 Recognition

Contributors will be:
- Added to the project's contributors list
- Mentioned in release notes for significant contributions
- Given appropriate credit in documentation

## 🤝 Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors, regardless of:
- Experience level
- Gender identity and expression
- Sexual orientation
- Disability
- Personal appearance
- Body size
- Race
- Ethnicity
- Age
- Religion
- Nationality

### Our Standards

Examples of behavior that contributes to a positive environment:
- Being respectful and inclusive
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards other community members

Examples of unacceptable behavior:
- Harassment or discrimination
- Trolling, insulting, or derogatory comments
- Public or private harassment
- Publishing others' private information
- Other conduct which could reasonably be considered inappropriate

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be reported by contacting the project team. All complaints will be reviewed and investigated promptly and fairly.

## 📞 Getting Help

If you need help with contributing:

1. **Check existing issues** for similar questions
2. **Read the documentation** thoroughly
3. **Ask in GitHub Discussions** for general questions
4. **Join our community** chat (if available)

## 🙏 Thank You

Your contributions make this project better for everyone in the Algerian fintech ecosystem. Every contribution, no matter how small, is valued and appreciated!

---

Happy coding! 🚀