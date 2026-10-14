#!/usr/bin/env bash
#
# Cloud Agent install script for the compathee/compathee repository.
#
# The repository hosts the "Choir Rehearsal" WordPress plugin (PHP 8.0+) and
# n8n workflow JSON. This script provisions the PHP toolchain needed to lint
# and validate the plugin, plus the JSON tooling the n8n workflows rely on.
#
# It must be idempotent: it runs once to build the environment snapshot and may
# run again on later boots against an already-provisioned machine.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

# PHP CLI + extensions commonly used by WordPress plugins, Composer for PHP
# dependency management, and jq for validating the n8n workflow JSON files.
PACKAGES=(
  php-cli
  php-mbstring
  php-xml
  php-curl
  php-zip
  php-mysql
  php-gd
  composer
  jq
)

echo "==> Updating apt package lists"
sudo apt-get update -qq

echo "==> Installing packages: ${PACKAGES[*]}"
# apt-get install is idempotent: already-current packages are left untouched.
sudo apt-get install -y --no-install-recommends "${PACKAGES[@]}"

echo "==> Installed tool versions"
php --version
composer --version
jq --version

echo "==> Cloud Agent install complete"
