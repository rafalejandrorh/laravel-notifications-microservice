SHELL := /bin/sh

DC := docker compose
DC_PROD := docker compose -f docker-compose.prod.yml
APP_SERVICE := app

.PHONY: help \
	up down stop restart logs sh bash ps build \
	up-prod down-prod logs-prod ps-prod build-prod \
	composer-install composer-update composer-dump \
	key-generate inbox-indexes messenger-setup messenger-consume queue-work \
	config-clear config-cache route-cache route-clear \
	view-clear cache-clear optimize artisan test

help:
	@echo "Comandos principales (entorno Docker):"
	@echo "  make up                 - Levantar contenedores de desarrollo"
	@echo "  make down               - Apagar y eliminar contenedores de desarrollo"
	@echo "  make restart            - Reiniciar contenedores de desarrollo"
	@echo "  make logs               - Ver logs de desarrollo"
	@echo "  make bash               - Shell del servicio app (dev)"
	@echo "  make up-prod            - Levantar contenedores de producción"
	@echo "  make down-prod          - Apagar contenedores de producción"
	@echo "  make logs-prod          - Ver logs de producción"
	@echo "  make composer-install   - composer install dentro de app"
	@echo "  make inbox-indexes      - php artisan inbox:ensure-indexes"
	@echo "  make messenger-setup    - php artisan messenger:setup"
	@echo "  make messenger-consume  - php artisan messenger:consume email"
	@echo "  make queue-work         - php artisan queue:work --queue=email.send"
	@echo "  make test               - Ejecutar la suite de tests"
	@echo "  make artisan CMD=...    - Ejecutar cualquier comando php artisan"

## Docker (dev) ###########################################################

up:
	$(DC) up -d --build

down:
	$(DC) down

stop:
	$(DC) stop

restart: down up

logs:
	$(DC) logs -f

ps:
	$(DC) ps

sh bash:
	$(DC) exec $(APP_SERVICE) sh

build:
	$(DC) build

## Docker (prod) ##########################################################

up-prod:
	$(DC_PROD) up -d --build

down-prod:
	$(DC_PROD) down

logs-prod:
	$(DC_PROD) logs -f

ps-prod:
	$(DC_PROD) ps

build-prod:
	$(DC_PROD) build

## Composer ###############################################################

composer-install:
	$(DC) exec -u www-data $(APP_SERVICE) composer install

composer-update:
	$(DC) exec -u www-data $(APP_SERVICE) composer update

composer-dump:
	$(DC) exec -u www-data $(APP_SERVICE) composer dump-autoload

## Laravel: instalación ###################################################

key-generate:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan key:generate

inbox-indexes:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan inbox:ensure-indexes

messenger-setup:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan messenger:setup

## Laravel: colas #########################################################

messenger-consume:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan messenger:consume email --time-limit=3600

queue-work:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan queue:work --queue=email.send --sleep=3 --tries=3

## Laravel: caches ########################################################

config-clear:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan config:clear

config-cache:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan config:cache

route-cache:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan route:cache

route-clear:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan route:clear

view-clear:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan view:clear

cache-clear:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan cache:clear

optimize:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan optimize

## Testing ################################################################

test:
	$(DC) exec -u www-data $(APP_SERVICE) php artisan test

## Wrapper genérico para artisan ##########################################

# make artisan CMD="cache:clear"  -> php artisan cache:clear
artisan:
	@[ -n "$$CMD" ] || { echo "Debe definir CMD='comando artisan'"; exit 1; }
	$(DC) exec -u www-data $(APP_SERVICE) php artisan $$CMD
