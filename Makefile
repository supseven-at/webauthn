
RELEASE_VERSION ?= $(shell git tag -l | sort -V | tail -n 1)
PREV_VERSION = $(shell php .github/helper/find-previous-version.php $(RELEASE_VERSION))

.PHONY: help
help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}'

##
## Main entry points
##

.PHONY: lint
lint: lint-php lint-frontend ## Lint all code

.PHONY: fix
fix: fix-php fix-frontend ## Fix all code

.PHONY: clean
clean: ## Remove built files and dependencies
	rm -rf node_modules vendor .php-cs-fixer-cache

.PHONY: release
release: release/webauthn.zip release/changelog.md release/ter_notes.md ## Create release artifact

.PHONY: frontend
frontend: Resources/Public/JavaScript/Auth.js Resources/Public/JavaScript/Edit.js Resources/Public/JavaScript/Setup.js

##
## Sub targets called by entry points
##

.PHONY: lint-php
lint-php: vendor/autoload.php ## Lint PHP code style
	./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php -vvv --dry-run

.PHONY: lint-frontend
lint-frontend: node_modules/.yarn-integrity ## Lint frontend code style
	./node_modules/.bin/prettier --check . '!vendor' '!composer.lock' '!Resources/Public/JavaScript'

Resources/Public/JavaScript/Auth.js: node_modules/.yarn-integrity res/Auth.js
	./node_modules/.bin/esbuild --outfile=Resources/Public/JavaScript/Auth.js --bundle --minify --platform=browser res/Auth.js

Resources/Public/JavaScript/Edit.js: node_modules/.yarn-integrity res/Edit.js
	./node_modules/.bin/esbuild --outfile=Resources/Public/JavaScript/Edit.js --bundle --minify --platform=browser res/Edit.js

Resources/Public/JavaScript/Setup.js: node_modules/.yarn-integrity res/Setup.js
	./node_modules/.bin/esbuild --outfile=Resources/Public/JavaScript/Setup.js --bundle --minify --platform=browser res/Setup.js

.PHONY: fix-php
fix-php: vendor/autoload.php ## Fix PHP code style
	./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php -vvv

.PHONY: fix-frontend
fix-frontend: node_modules/.yarn-integrity ## Fix frontend code style
	./node_modules/.bin/prettier --write . '!vendor' '!composer.lock' '!Resources/Public/JavaScript'

release/changelog.md:
	mkdir -p release
	git log --format='* %h %s' $(PREV_VERSION)..$(RELEASE_VERSION) > release/changelog.md

release/ter_notes.md:
	mkdir -p release
	git log --format='* %s' $(PREV_VERSION)..$(RELEASE_VERSION) | sed -r 's/\[\S+\] //' > release/ter_notes.md

release/webauthn.zip:
	mkdir -p release/webauthn
	cp -a Configuration Resources src composer.json ext_emconf.php LICENSE Readme.md release/webauthn
	php .github/helper/set-version.php $(RELEASE_VERSION) release/webauthn
	cd release && zip -r webauthn.zip webauthn

##
## Dependencies of targets
##

vendor/autoload.php: composer.json composer.lock
	composer install --prefer-dist -n
	touch vendor/autoload.php

node_modules/.yarn-integrity: package.json yarn.lock
	yarn install --frozen-lockfile --prefer-offline
	touch node_modules/.yarn-integrity
