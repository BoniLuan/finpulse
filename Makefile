# FinPulse — developer entrypoints. See CLAUDE.md for conventions.
COMPOSE = docker compose

.PHONY: help up down build logs ps migrate seed test lint api-shell ai-shell

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the whole stack
	$(COMPOSE) up -d --build

down: ## Stop the stack
	$(COMPOSE) down

build: ## Build images
	$(COMPOSE) build

logs: ## Tail all logs
	$(COMPOSE) logs -f

ps: ## Show service status
	$(COMPOSE) ps

migrate: ## Apply database migrations
	$(COMPOSE) exec api php bin/console migrate

seed: ## Load sample data
	$(COMPOSE) exec api php bin/console seed

test: ## Run all tests (PHP + Python)
	$(COMPOSE) exec api composer test
	$(COMPOSE) exec ai-worker pytest -q

lint: ## Run all linters
	$(COMPOSE) exec api composer lint
	$(COMPOSE) exec ai-worker ruff check . && $(COMPOSE) exec ai-worker mypy app

api-shell: ## Shell into the api container
	$(COMPOSE) exec api sh

ai-shell: ## Shell into the ai-worker container
	$(COMPOSE) exec ai-worker sh
