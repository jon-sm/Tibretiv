.DEFAULT_GOAL := help
.PHONY: help install build deps up down test phpstan deptrac

# Run the containers as the current user, so that they can write to ./src.
# Precedence: command line, then environment, then .env, then the current user.
APP_UID ?= $(or $(shell sed -n 's/^APP_UID=//p' .env 2>/dev/null),$(shell id -u))
APP_GID ?= $(or $(shell sed -n 's/^APP_GID=//p' .env 2>/dev/null),$(shell id -g))
export APP_UID APP_GID

# Arguments after "--" go to the program: make test -- --filter=Foo
# Make reads them as extra goals, or as variables when they contain "=", so they
# are collected here and the extra goals are turned into no-ops.
ifneq ($(filter test phpstan deptrac,$(firstword $(MAKECMDGOALS))),)
ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS)) \
	$(foreach v,$(filter -%,$(.VARIABLES)),$(if $(filter command line,$(origin $v)),$v=$(value $v)))
ifneq ($(filter help install build deps up down test phpstan deptrac,$(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))),)
$(error An argument has the same name as a make target, pass it with ARGS="..." instead)
endif
ifneq ($(strip $(ARGS)),)
%:
	@:
endif
endif

help: ## Show the available targets
	@grep -hE '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

install: build deps up ## Build the images, install dependencies and start the stack

build: ## Build the :base and :build images
	docker compose --profile tools build

deps: ## Install the Composer dependencies
	docker compose run --rm -T build composer install --no-interaction --no-progress

up: ## Start the stack in the foreground with its logs, Ctrl+C stops it
	docker compose up

down: ## Stop and remove the containers, keeping the data volumes
	docker compose down

test: ## Run the PHPUnit tests
	docker compose run --rm -T build vendor/bin/phpunit $(ARGS)

phpstan: ## Run the PHPStan static analysis
	docker compose run --rm -T build vendor/bin/phpstan analyse --no-progress --memory-limit=1G $(ARGS)

deptrac: ## Check the architecture layer rules
	docker compose run --rm -T build vendor/bin/deptrac analyse --no-progress $(ARGS)
