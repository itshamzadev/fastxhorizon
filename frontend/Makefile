# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
# Project-wide Makefile
# Root = frontend, backend lives in ./backend
# Requires:
#   CR_PAT      GitHub Container Registry token (write:packages)
#   NPM_TOKEN   GitHub Packages npm token (read/write:packages)
#   GH_USER     your GitHub username (default: hoangsonww)
# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

# User/config
GH_USER        ?= hoangsonww
BACKEND_DIR    := backend

# derive versions from package.json
FRONTEND_VERSION := $(shell node -p "require('./package.json').version")
BACKEND_VERSION  := $(shell cd $(BACKEND_DIR) && node -p "require('./package.json').version")

# image names
FRONTEND_IMAGE := ghcr.io/$(GH_USER)/ecommerce-fullstack-website-frontend
BACKEND_IMAGE  := ghcr.io/$(GH_USER)/FASTXHORIZON-electronics-backend

# npm package names
FRONTEND_PKG   := @$(GH_USER)/ecommerce-fullstack-website-frontend
BACKEND_PKG    := @$(GH_USER)/FASTXHORIZON-electronics-backend

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
.PHONY: all help install build test \
        docker-build docker-push \
        publish-npm \
        clean

all: help

help:
	@echo "Usage:"
	@echo "  make install          # install both frontend & backend deps"
	@echo "  make build            # build both frontend & backend"
	@echo "  make test             # run tests (if any) for both"
	@echo
	@echo "  make docker-build     # docker build both images"
	@echo "  make docker-push      # push both to ghcr.io (uses CR_PAT)"
	@echo
	@echo "  make publish-npm      # npm publish both to GitHub Packages (uses NPM_TOKEN)"
	@echo
	@echo "  make clean            # cleanup node_modules and builds"
	@echo

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
install: install-frontend install-backend

install-frontend:
	@echo "â†ª Installing frontend deps..."
	npm ci

install-backend:
	@echo "â†ª Installing backend deps..."
	cd $(BACKEND_DIR) && npm ci

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
build: build-frontend build-backend

build-frontend:
	@echo "â†ª Building frontend..."
	npm run build

build-backend:
	@echo "â†ª Building backend..."
	cd $(BACKEND_DIR) && npm run build

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
test: test-frontend test-backend

test-frontend:
	@echo "â†ª Testing frontend..."
	# insert your frontend test command here, e.g. npm test
	@echo "(no tests configured)"

test-backend:
	@echo "â†ª Testing backend..."
	# insert your backend test command here, e.g. cd backend && npm test
	@echo "(no tests configured)"

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
docker-build: docker-build-frontend docker-build-backend

docker-build-frontend:
	@echo "â†ª Docker build frontend â†’ $(FRONTEND_IMAGE):$(FRONTEND_VERSION)"
	docker build \
	  -t $(FRONTEND_IMAGE):$(FRONTEND_VERSION) \
	  -t $(FRONTEND_IMAGE):latest \
	  -f Dockerfile \
	  .

docker-build-backend:
	@echo "â†ª Docker build backend â†’ $(BACKEND_IMAGE):$(BACKEND_VERSION)"
	docker build \
	  -t $(BACKEND_IMAGE):$(BACKEND_VERSION) \
	  -t $(BACKEND_IMAGE):latest \
	  -f $(BACKEND_DIR)/Dockerfile \
	  $(BACKEND_DIR)

docker-push: ## push both images (requires CR_PAT)
	@echo "â†ª Pushing to GitHub Container Registry..."
	@if [ -z "$(CR_PAT)" ]; then \
	  echo "âŒ CR_PAT is not set"; exit 1; \
	fi
	echo "$(CR_PAT)" | docker login ghcr.io -u "$(GH_USER)" --password-stdin
	docker push $(FRONTEND_IMAGE):$(FRONTEND_VERSION)
	docker push $(FRONTEND_IMAGE):latest
	docker push $(BACKEND_IMAGE):$(BACKEND_VERSION)
	docker push $(BACKEND_IMAGE):latest

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
publish-npm: publish-frontend-npm publish-backend-npm

publish-frontend-npm:
	@echo "â†ª npm publish frontend â†’ $(FRONTEND_PKG)@$(FRONTEND_VERSION)"
	@if [ -z "$(NPM_TOKEN)" ]; then \
	  echo "âŒ NPM_TOKEN is not set"; exit 1; \
	fi
	npm config set //npm.pkg.github.com/:_authToken "$(NPM_TOKEN)"
	npm publish --access public

publish-backend-npm:
	@echo "â†ª npm publish backend â†’ $(BACKEND_PKG)@$(BACKEND_VERSION)"
	@if [ -z "$(NPM_TOKEN)" ]; then \
	  echo "âŒ NPM_TOKEN is not set"; exit 1; \
	fi
	cd $(BACKEND_DIR) && \
	npm config set //npm.pkg.github.com/:_authToken "$(NPM_TOKEN)" && \
	npm publish --access public

# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
clean:
	@echo "â†ª Cleaning..."
	rm -rf node_modules build
	cd $(BACKEND_DIR) && rm -rf node_modules build
