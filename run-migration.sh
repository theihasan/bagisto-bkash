#!/bin/bash

# Script to run bKash payment migration
# Run this from your main Bagisto directory: /Users/figlab/Desktop/sites/bagisto

echo "Running bKash payment migration..."

# Copy migration file to Laravel migration directory if it doesn't exist
MIGRATION_FILE="2025_02_24_181736_create_bkash_payment_table.php"
SOURCE_PATH="packages/bagisto-bkash/database/migrations/${MIGRATION_FILE}"
TARGET_PATH="database/migrations/${MIGRATION_FILE}"

if [ ! -f "$TARGET_PATH" ]; then
    echo "Copying migration file..."
    cp "$SOURCE_PATH" "$TARGET_PATH"
    echo "Migration file copied to $TARGET_PATH"
else
    echo "Migration file already exists in $TARGET_PATH"
fi

# Run the migration
echo "Running migration..."
php artisan migrate

echo "Migration completed!"