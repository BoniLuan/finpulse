# FinPulse — developer entrypoints. See CLAUDE.md for conventions.
COMPOSE = docker compose -f docker-compose.yml
DEV_COMPOSE = $(COMPOSE) -f compose.dev.yml
POWERSHELL ?= powershell
BASE_REF ?= HEAD

.PHONY: help up down build dev-up dev-down dev-build verify verify-ps rebuild-affected deploy-fast ship rollback hooks-install hooks-disable logs ps migrate seed test lint api-shell ai-shell

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Start the production stack
	$(COMPOSE) up -d

down: ## Stop the production stack
	$(COMPOSE) down

build: ## Build production images
	$(COMPOSE) build

dev-up: ## Start development with bind mounts and exposed ports
	$(DEV_COMPOSE) up -d

dev-down: ## Stop the development stack
	$(DEV_COMPOSE) down

dev-build: ## Build development images
	$(DEV_COMPOSE) build

verify: ## Test and lint services affected by uncommitted changes
	bash scripts/verify.sh $(BASE_REF)

verify-ps: ## Run legacy affected-service verification (PowerShell)
	$(POWERSHELL) -NoProfile -File scripts/verify-changes.ps1

rebuild-affected: ## Verify and rebuild only affected images (PowerShell)
	$(POWERSHELL) -NoProfile -File scripts/verify-changes.ps1 -BuildAffected

deploy-fast: ## Verify, rebuild affected production services, migrate, and health-check
	bash scripts/deploy.sh

ship: deploy-fast ## Deploy the current commit, then push it to origin
	git push

rollback: ## Restore service images captured before the last deployment
	bash scripts/rollback.sh

hooks-install: ## Enable automatic deployment after each local commit
	bash scripts/install-hooks.sh

hooks-disable: ## Disable repository-managed Git hooks for this checkout
	git config --unset core.hooksPath || true
	@echo "FinPulse Git hooks disabled."

logs: ## Tail all logs
	$(COMPOSE) logs -f

ps: ## Show service status
	$(COMPOSE) ps

migrate: ## Apply database migrations
	$(COMPOSE) exec api php bin/console migrate

seed: ## Load sample data
	$(COMPOSE) exec api php bin/console seed

test: ## Run all tests (PHP + Python)
	$(DEV_COMPOSE) exec api composer test
	$(DEV_COMPOSE) exec ai-worker pytest -q

lint: ## Run all linters
	$(DEV_COMPOSE) exec api composer lint
	$(DEV_COMPOSE) exec ai-worker ruff check . && $(DEV_COMPOSE) exec ai-worker mypy app

api-shell: ## Shell into the api container
	$(COMPOSE) exec api sh

ai-shell: ## Shell into the ai-worker container
	$(COMPOSE) exec ai-worker sh
