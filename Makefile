COMPOSER = docker run --rm -v $(PWD):/app -w /app composer:latest
PINT     = docker run --rm -v $(PWD):/app -w /app composer:latest exec pint
APP      = docker exec egov_app

# ─── Docker ───────────────────────────────────────────────────────────────────

up:
	docker-compose up -d

up-build:
	docker-compose up -d --build

down:
	docker-compose down

restart:
	docker-compose restart

logs:
	docker-compose logs -f

# ─── Composer ─────────────────────────────────────────────────────────────────

install:
	$(COMPOSER) install

require:
	$(COMPOSER) require $(pkg)

require-dev:
	$(COMPOSER) require --dev $(pkg)

dump:
	$(COMPOSER) dump-autoload

# ─── Artisan ──────────────────────────────────────────────────────────────────

artisan:
	$(APP) php artisan $(cmd)

migrate:
	$(APP) php artisan migrate

migrate-fresh:
	$(APP) php artisan migrate:fresh

seed:
	$(APP) php artisan db:seed

migrate-seed:
	$(APP) php artisan migrate:fresh --seed

key:
	$(APP) php artisan key:generate

cache-clear:
	$(APP) php artisan cache:clear
	$(APP) php artisan config:clear
	$(APP) php artisan route:clear
	$(APP) php artisan view:clear

tinker:
	$(APP) php artisan tinker

# ─── Testing ──────────────────────────────────────────────────────────────────

test:
	$(APP) php artisan test

test-filter:
	$(APP) php artisan test --filter=$(filter)

test-coverage:
	$(APP) php artisan test --coverage

# ─── Code Quality ─────────────────────────────────────────────────────────────

lint:
	$(PINT) --test

fix:
	$(PINT)

# ─── Setup ────────────────────────────────────────────────────────────────────

setup: install up key migrate seed
	@echo "Setup complete — http://localhost"

.PHONY: up up-build down restart logs install require require-dev dump \
        artisan migrate migrate-fresh seed migrate-seed key cache-clear tinker \
        test test-filter test-coverage lint fix setup
