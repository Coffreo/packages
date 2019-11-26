.PHONY: install test dev configure database
.DEFAULT_GOAL := help

## install composer dependencies
install:
	composer install

config.yml:
	cp config.yml.dist config.yml

## edit configuration
configure: config.yml
	vi config.yml

## initialize sqlite database if file doesn't exist
database: database.sqlite

database.sqlite:
	bin/console orm:schema-tool:create

## run phpunit test suite
test:
	vendor/bin/phpunit

## start a worker
worker:
	bin/console resque:worker:start

## start a localhost server on port 8080
start:
	php -S localhost:8080 -t web

## start a localhost server on port 8080 with xdebug enabled
dev:
	php -S localhost:8080 -t web -ddisplay_errors=1 -dzend_extension=xdebug.so -dxdebug.remote_enable=1 -dxdebug.remote_autostart=1

# COLORS
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

TARGET_MAX_CHAR_NUM=20
## Show this help
help:
	@echo '# ${YELLOW}packages${RESET}'
	@echo ''
	@echo 'Usage:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<target>${RESET}'
	@echo ''
	@echo 'Targets:'
	@awk '/^[a-zA-Z\-\_0-9.]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")); \
			gsub(":", " ", helpCommand); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "  ${YELLOW}%-$(TARGET_MAX_CHAR_NUM)s${RESET} ${GREEN}%s${RESET}\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST) | sort

