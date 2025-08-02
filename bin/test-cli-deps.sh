#!/bin/bash

REQUIRED_CMDS=("jq" "shuf" "date" "uuidgen")

# Use this to store any missing commands
missing=()

# Detect platform
OS="$(uname -s)"

# Platform-specific package installation
install_command() {
  cmd="$1"

  case "$OS" in
    Linux)
      if grep -qiE 'debian|ubuntu' /etc/os-release; then
        echo "Installing $cmd using apt..."
        sudo apt update && sudo apt install -y "$cmd"
      elif grep -qi arch /etc/os-release; then
        echo "Installing $cmd using pacman..."
        sudo pacman -Sy --noconfirm "$cmd"
      elif grep -qiE 'fedora|rhel|centos' /etc/os-release; then
        echo "Installing $cmd using dnf..."
        sudo dnf install -y "$cmd"
      else
        echo "Unsupported Linux distribution. Please install '$cmd' manually."
      fi
      ;;

    Darwin)
      if ! command -v brew >/dev/null 2>&1; then
        echo "Homebrew is not installed. Please install Homebrew first: https://brew.sh"
        exit 1
      fi

      if [[ "$cmd" == "shuf" || "$cmd" == "date" ]]; then
        echo "Installing coreutils via Homebrew (for $cmd)..."
        brew install coreutils
      else
        echo "Installing $cmd via Homebrew..."
        brew install "$cmd"
      fi
      ;;

    MINGW*|CYGWIN*|MSYS*|Windows*)
      echo "This script does not support Windows. Please install '$cmd' manually."
      exit 1
      ;;

    *)
      echo "Unsupported OS: $OS"
      exit 1
      ;;
  esac
}

# Add GNU coreutils to PATH on macOS if needed
add_gnubin_to_path_if_macos() {
  if [[ "$OS" == "Darwin" && -d "/opt/homebrew/opt/coreutils/libexec/gnubin" ]]; then
    export PATH="/opt/homebrew/opt/coreutils/libexec/gnubin:$PATH"
  fi
}

# Run checks
add_gnubin_to_path_if_macos

for cmd in "${REQUIRED_CMDS[@]}"; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "$cmd is missing"
    missing+=("$cmd")
  else
    echo "$cmd is installed"
  fi
done

# Install missing commands
for cmd in "${missing[@]}"; do
  install_command "$cmd"
done

# Final report
if [ ${#missing[@]} -eq 0 ]; then
  echo "✅ All required commands are installed."
else
  echo "⚠️ Some commands were missing and may have just been installed. You may need to restart your shell."
fi
