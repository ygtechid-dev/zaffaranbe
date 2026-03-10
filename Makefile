.PHONY: help pull migrate seed seed-cities clean update deploy

# Default target
help:
	@echo "Available commands:"
	@echo "  make pull         - Pull latest changes from git (branch main)"
	@echo "  make migrate      - Run database migrations"
	@echo "  make seed         - Run database seeders"
	@echo "  make seed-cities  - Run specifically CitySeeder"
	@echo "  make clean        - Clear application cache"
	@echo "  make update       - Pull, migrate, and clear cache"
	@echo "  make deploy       - Full deploy: pull, migrate, seed, and clear cache"

pull:
	git pull origin main

migrate:
	php artisan migrate --force

seed:
	php artisan db:seed --force

seed-cities:
	php artisan db:seed --class=CitySeeder --force

clean:
	# Clean cache files for Lumen/Laravel
	@echo "Cleaning cache..."
	# rm -f bootstrap/cache/config.php
	# rm -f storage/framework/cache/data/*
	# php artisan cache:clear

update: pull migrate clean
	@echo "Update completed successfully."

deploy: pull migrate seed clean
	@echo "Deployment completed successfully."
