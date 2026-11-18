# This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
#
# Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
#
# SPDX-License-Identifier: MIT

# Every target runs inside Docker; no local PHP/Composer is required.
#
# Tooling notes:
# - All QA tools are installed through Composer (they co-install on PHP 8.2), including Mago
#   (carthage-software/mago, pinned by composer.lock), so no extra docker service/PHAR is needed.
# - Pass PHP_VERSION=8.3 (or 8.4) to run against another PHP version: `make ci PHP_VERSION=8.4`.

PHP := docker compose run --rm php
PHP_XDEBUG := docker compose run --rm -e XDEBUG_MODE=coverage php
# Default suites = unit + functional, limited to the directories that exist (PHPUnit fails on a missing one).
SUITES := $(shell for s in Unit Functional; do [ -d tests/$$s ] && echo $$s; done | tr A-Z a-z | paste -sd, -)
UNIT_FUNCTIONAL := --testsuite=$(SUITES)

.DEFAULT_GOAL := help
.PHONY: help build install update shell test test-unit test-functional test-contract coverage coverage-paths coverage-branches \
	bench infection phpstan mago cs cs-fix rector rector-fix deptrac composer-checks hadolint guard ci

help: ## List targets
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "%-16s %s\n", $$1, $$2}'

build: ## Build the PHP image
	docker compose build php

install: build ## Install dependencies from composer.lock
	$(PHP) mkdir -p var
	$(PHP) composer install --no-interaction --no-progress

update: ## Update dependencies
	$(PHP) composer update --no-interaction --no-progress

shell: ## Open a shell in the container
	$(PHP) bash

test: ## Run unit + functional tests
	$(PHP) vendor/bin/phpunit $(UNIT_FUNCTIONAL)

test-unit: ## Run unit tests
	$(PHP) vendor/bin/phpunit --testsuite=unit

test-functional: ## Run functional tests
	$(PHP) vendor/bin/phpunit --testsuite=functional

# Sandbox variables forwarded to the container. `-e NAME` without a value copies the shell value and leaves the variable
# unset when the shell has none (so the live tests skip). No value is ever written here and no --env-file is read.
CONTRACT_ENV := URSSAF_SANDBOX_CLIENT_ID URSSAF_SANDBOX_CLIENT_SECRET URSSAF_SANDBOX_BASE_URI URSSAF_SANDBOX_TOKEN_URI \
	URSSAF_SANDBOX_EMPLOYER_NUMBER URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE URSSAF_SANDBOX_EMPLOYEE_NUMBER \
	URSSAF_SANDBOX_CHILD_LAST_NAME URSSAF_SANDBOX_CHILD_FIRST_NAME URSSAF_SANDBOX_CHILD_BIRTH_DATE \
	URSSAF_SANDBOX_ALLOW_WRITES

test-contract: ## Live sandbox contract tests (skipped without URSSAF_SANDBOX_CLIENT_ID/SECRET); report in var/contract
	$(PHP) rm -f var/contract/sandbox-report.json
	docker compose run --rm $(addprefix -e ,$(CONTRACT_ENV)) php vendor/bin/phpunit --testsuite=contract
	@$(PHP) sh -c '[ -f var/contract/sandbox-report.json ] && cat var/contract/sandbox-report.json || true'

coverage: ## Coverage (pcov): text + clover + html in var/coverage, enforce 100% lines/methods/classes
	$(PHP) vendor/bin/phpunit $(UNIT_FUNCTIONAL) --coverage-text --coverage-clover var/coverage/clover.xml --coverage-html var/coverage/html --coverage-xml var/coverage/coverage-xml --log-junit var/coverage/junit.xml
	$(PHP) php bin/check-coverage var/coverage/clover.xml

coverage-paths: ## Path coverage report (Xdebug)
	$(PHP_XDEBUG) php -d pcov.enabled=0 vendor/bin/phpunit $(UNIT_FUNCTIONAL) --path-coverage --coverage-html var/coverage/paths

coverage-branches: ## Branch coverage (Xdebug, slow): Cobertura in var/coverage, enforce >= 96 % branches
	$(PHP_XDEBUG) php -d pcov.enabled=0 vendor/bin/phpunit $(UNIT_FUNCTIONAL) --path-coverage --coverage-text --coverage-cobertura var/coverage/cobertura.xml
	$(PHP) php bin/check-branch-coverage var/coverage/cobertura.xml 96

bench: ## Benchmarks (PHPBench in tools/phpbench, out of the package dependencies); e.g. make bench ARGS="--tag=main"
	$(PHP) composer install --no-progress --working-dir=tools/phpbench
	$(PHP) tools/phpbench/vendor/bin/phpbench run --report=urssaf $(ARGS)

infection: ## Mutation testing (100% MSI); runs its own initial test pass (never --skip-initial-tests: false positives)
	$(PHP) vendor/bin/infection --min-msi=100 --min-covered-msi=100 --threads=max --show-mutations --no-progress \
		--test-framework-options="$(UNIT_FUNCTIONAL)"
	@grep -q '^Skipped: 0$$' var/infection/summary.log || { echo 'Infection skipped mutants (time limit, see infection.json5)'; exit 1; }
	@grep -q '^Syntax Errors: 0$$' var/infection/summary.log || { echo 'Infection produced invalid mutants (syntax errors): rewrite the mutated expression'; exit 1; }
	@grep -q '^Timed Out: 0$$' var/infection/summary.log || { echo 'Infection mutants timed out: a timeout is not a verdict (see infection.json5)'; exit 1; }

phpstan: ## PHPStan level max
	$(PHP) vendor/bin/phpstan analyse --memory-limit=-1 --no-progress

mago: ## Mago lint + analyze (never `mago fmt`)
	$(PHP) vendor/bin/mago lint --minimum-fail-level=note
	$(PHP) vendor/bin/mago analyze --minimum-fail-level=note

cs: ## Check code style (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff --show-progress=none

cs-fix: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

rector: ## Rector dry-run
	$(PHP) vendor/bin/rector process --dry-run --no-progress-bar

rector-fix: ## Apply Rector
	$(PHP) vendor/bin/rector process --no-progress-bar

deptrac: ## Architecture layers
	$(PHP) vendor/bin/deptrac analyse --no-progress 

composer-checks: ## composer validate/audit/normalize + dependency hygiene
	$(PHP) composer validate --strict
	$(PHP) composer audit
	$(PHP) composer normalize --dry-run --diff
	$(PHP) vendor/bin/composer-require-checker check --config-file=composer-require-checker.json composer.json
	$(PHP) vendor/bin/composer-unused --no-progress --configuration=composer-unused.php

hadolint: ## Lint the Dockerfiles (hadolint, .hadolint.yaml)
	docker run --rm -v "$(CURDIR)":/repo:ro -w /repo hadolint/hadolint:v2.15.1@sha256:32dac94127fd60b7b7e3fbfc65e1383b9b5e25c9bfd7b8536de7a539fe68a12d \
		hadolint docker/php/Dockerfile

guard: ## Fail on the forbidden word or a missing SPDX licence header
	@if grep -rIil --exclude-dir=vendor --exclude-dir=var --exclude-dir=.git 'extra''bat' . ; then \
		echo "Forbidden word found (see files above)"; exit 1; \
	fi
	$(PHP) sh bin/check-spdx

ci: cs rector phpstan mago deptrac composer-checks hadolint guard coverage infection ## Full quality gate
