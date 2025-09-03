# orc

A powerful command-line tool written in Rust.

## Installation

### Prerequisites

Before installing orc, you need to have Rust installed on your system.

#### Installing Rust

##### On Windows

1. **Download and run rustup-init.exe:**
   - Visit [https://rustup.rs/](https://rustup.rs/)
   - Download `rustup-init.exe`
   - Run the installer and follow the on-screen instructions

2. **Using PowerShell/Command Prompt:**
   ```cmd
   # Download and install rustup
   curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
   ```

3. **Restart your terminal** or run:
   ```cmd
   source %USERPROFILE%\.cargo\env
   ```

##### On macOS

1. **Using the official installer:**
   ```bash
   curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
   ```

2. **Using Homebrew (alternative):**
   ```bash
   brew install rust
   ```

3. **Restart your terminal** or run:
   ```bash
   source ~/.cargo/env
   ```

##### On Linux

1. **Using the official installer:**
   ```bash
   curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
   ```

2. **Using package managers:**

   **Ubuntu/Debian:**
   ```bash
   sudo apt update
   sudo apt install rustc cargo
   ```

   **Fedora:**
   ```bash
   sudo dnf install rust cargo
   ```

   **Arch Linux:**
   ```bash
   sudo pacman -S rust
   ```

3. **Restart your terminal** or run:
   ```bash
   source ~/.cargo/env
   ```

#### Verify Rust Installation

After installation, verify that Rust is properly installed:

```bash
rustc --version
cargo --version
```

You should see version information for both `rustc` (the Rust compiler) and `cargo` (Rust's package manager).

### Installing orc

#### Method 1: From Source (Recommended)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/ugm616/orc.git
   cd orc
   ```

2. **Build the project:**
   ```bash
   cargo build --release
   ```

3. **Install the binary system-wide:**

   **On Windows:**
   ```cmd
   # Copy the binary to a directory in your PATH
   copy target\release\orc.exe C:\Windows\System32\
   
   # Or install using cargo
   cargo install --path .
   ```

   **On macOS/Linux:**
   ```bash
   # Install using cargo (recommended)
   cargo install --path .
   
   # Or manually copy to /usr/local/bin
   sudo cp target/release/orc /usr/local/bin/
   sudo chmod +x /usr/local/bin/orc
   ```

#### Method 2: Direct Installation from Git

```bash
cargo install --git https://github.com/ugm616/orc.git
```

#### Method 3: From crates.io (when published)

```bash
cargo install orc
```

### Verification

After installation, verify that orc is properly installed and accessible:

```bash
orc --version
```

If the command is not found, ensure that the Cargo bin directory is in your PATH:

- **Windows:** `%USERPROFILE%\.cargo\bin`
- **macOS/Linux:** `~/.cargo/bin`

### Platform-Specific Installation Notes

#### Windows

- If you encounter permission issues, run your terminal as Administrator
- Make sure your antivirus software isn't blocking the installation
- The Windows Defender SmartScreen might warn about the executable; this is normal for new applications

#### macOS

- On newer versions of macOS, you might need to allow the binary in System Preferences > Security & Privacy
- If using Homebrew, ensure your PATH includes `/usr/local/bin`

#### Linux

- On some distributions, you might need to install additional dependencies:
  ```bash
  # Ubuntu/Debian
  sudo apt install build-essential pkg-config
  
  # Fedora
  sudo dnf groupinstall "Development Tools"
  
  # Arch Linux
  sudo pacman -S base-devel
  ```

### Troubleshooting

#### Common Issues

1. **"cargo: command not found"**
   - Ensure Rust is properly installed and the Cargo bin directory is in your PATH
   - Restart your terminal after installing Rust

2. **Permission denied errors**
   - On Unix-like systems, use `sudo` for system-wide installation
   - On Windows, run your terminal as Administrator

3. **Build failures**
   - Ensure you have the latest version of Rust: `rustup update`
   - Check that all system dependencies are installed

4. **"orc: command not found" after installation**
   - Verify the Cargo bin directory is in your PATH
   - Try running the full path: `~/.cargo/bin/orc` (Unix) or `%USERPROFILE%\.cargo\bin\orc.exe` (Windows)

#### Getting Help

If you encounter issues during installation:

1. Check the [Issues](https://github.com/ugm616/orc/issues) page for known problems
2. Create a new issue with details about your system and the error message
3. Include the output of `rustc --version` and `cargo --version`

### Uninstallation

To uninstall orc:

```bash
# If installed via cargo
cargo uninstall orc

# If manually copied, remove the binary
# Windows: delete from C:\Windows\System32\orc.exe or remove from PATH
# macOS/Linux: sudo rm /usr/local/bin/orc
```

## Development

### Building from Source

1. Clone the repository:
   ```bash
   git clone https://github.com/ugm616/orc.git
   cd orc
   ```

2. Build in debug mode:
   ```bash
   cargo build
   ```

3. Run tests:
   ```bash
   cargo test
   ```

4. Build optimized release:
   ```bash
   cargo build --release
   ```

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.