#!/bin/bash

# Zafaran Backend Deployment Script

function show_help {
    echo "Usage: ./deploy.sh [command]"
    echo ""
    echo "Commands:"
    echo "  update       - Pull code, migrate DB, clean cache (Standard Deploy)"
    echo "  deploy       - Full Deploy: Pull, migrate, seed, clean cache"
    echo "  pull         - Only pull git changes"
    echo "  migrate      - Only run migrations"
    echo "  seed         - Only run seeders"
    echo "  seed-cities  - Only run CitySeeder"
    echo ""
}

# --- Command functions ---

function pull {
    echo "Running: git pull origin main..."
    git pull origin main
}

function migrate {
    echo "Running: migrations..."
    php artisan migrate --force
}

function seed {
    echo "Running: seeders..."
    php artisan db:seed --force
}

function seed_cities {
    echo "Running: CitySeeder..."
    php artisan db:seed --class=CitySeeder --force
}

function clean {
    echo "Cleaning cache..."
    # php artisan cache:clear # Uncomment if needed
}

# --- Logic ---

if [ -z "$1" ]; then
    show_help
    exit 1
fi

case "$1" in
    pull)
        pull
        ;;
    migrate)
        migrate
        ;;
    seed)
        seed
        ;;
    seed-cities)
        seed_cities
        ;;
    update)
        pull
        migrate
        clean
        echo "✅ Update completed successfully!"
        ;;
    deploy)
        pull
        migrate
        seed
        clean
        echo "✅ Full Deployment completed successfully!"
        ;;
    *)
        show_help
        ;;
esac
