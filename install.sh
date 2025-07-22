#!/bin/bash

echo "Installing Chargily Pay CLI..."
echo

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "ERROR: Composer is not installed or not in PATH"
    echo "Please install Composer first: https://getcomposer.org/download/"
    exit 1
fi

# Install the CLI globally
echo "Installing via Composer..."
composer global require karaodin/chargily-pay-cli

if [ $? -ne 0 ]; then
    echo "ERROR: Installation failed"
    exit 1
fi

# Create a simple wrapper script in current directory
cat > chargily << 'EOF'
#!/bin/bash
php ~/.config/composer/vendor/karaodin/chargily-pay-cli/chargily "$@"
EOF

chmod +x chargily

echo
echo "✅ Installation complete!"
echo
echo "You can now run: ./chargily"
echo