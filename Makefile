# Variables
CONFIG ?= release
LOG_LEVEL = debug
OUTDIR = build/$(CONFIG)
PACKAGE = $(OUTDIR)/net.nosial.configlib.ncc

# Default Target
all: build

# Build Steps
build:
	ncc build --config=$(CONFIG) --log-level $(LOG_LEVEL)

install:
	ncc package install --package=$(PACKAGE) --skip-dependencies --build-source --reinstall -y --log-level $(LOG_LEVEL)

clean:
	rm -rf build

.PHONY: all build install clean