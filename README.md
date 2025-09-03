# orc

A lean, cross-platform terminal client that routes all traffic through a pre-installed Tor instance without bundling Tor.

## Features

- **Tor-only networking**: All traffic routed through SOCKS5H proxy, no direct internet connections
- **Onion-only validation**: Rejects non-.onion addresses for security
- **Auto-discovery**: Detects Tor proxy on 127.0.0.1:9150, then 9050, then config values
- **Hard fail**: Clear error messages when Tor is unavailable
- **Memory hygiene**: Sensitive data automatically zeroized
- **Panic/kill switch**: Emergency cleanup on Ctrl+C or crashes
- **Minimal size**: ~3.2MB binary with only system dependencies

## Requirements

- **Tor must be running** on one of these ports:
  - `127.0.0.1:9150` (Tor Browser default)
  - `127.0.0.1:9050` (System Tor default)
  - Custom address via `ORC_SOCKS_HOST`/`ORC_SOCKS_PORT` environment variables

## Installation

```bash
cargo build --release
cp target/release/orc /usr/local/bin/
```

## Usage

### Check Tor Availability
```bash
orc --check
```

### Fetch .onion URLs
```bash
# Fetch a webpage via Tor
orc fetch --url "http://facebookcorewwwi.onion"

# With verbose output
orc --verbose fetch --url "https://duckduckgogg42r.onion"
```

### Raw TCP Streaming
```bash
# Send hex-encoded data to a .onion service
orc stream --host "facebookcorewwwi.onion" --port 80 --hex "474554202f20485454502f312e310d0a0d0a"

# With verbose output
orc --verbose stream --host "example.onion" --port 443 --hex "16030100..."
```

## Configuration

### Environment Variables
- `ORC_SOCKS_HOST`: Override default Tor proxy host
- `ORC_SOCKS_PORT`: Override default Tor proxy port  
- `ORC_CONFIG`: Path to custom config file

### Config File
Optional JSON config at `~/.config/orc/config.json`:
```json
{
  "socks_host": "127.0.0.1",
  "socks_port": 9050,
  "wipe_paths": []
}
```

## Security Features

- **Tor-only**: Rejects all non-.onion addresses
- **No DNS leaks**: Uses SOCKS5H for hostname resolution via Tor
- **Memory protection**: Sensitive data zeroized with `zeroize` crate
- **Emergency exit**: Ctrl+C triggers secure cleanup and exit code 137
- **No logging**: No persistent logs written to disk by default

## Error Codes

- `0`: Success
- `1`: General error (Tor unavailable, invalid input, etc.)
- `137`: Emergency exit (signal-triggered cleanup)

## Examples

```bash
# Check if Tor is running
$ orc --check
✓ Tor is available and working at 127.0.0.1:9150

# Fetch Facebook's onion service
$ orc fetch --url "https://facebookcorewwwi.onion"
<!DOCTYPE html>...

# Send HTTP GET request via raw TCP
$ orc stream --host "facebookcorewwwi.onion" --port 80 --hex "474554202f20485454502f312e310d0a486f73743a2066616365626f6f6b636f726577777769676e72652e6f6e696f6e0d0a0d0a"
485454502f312e3120333030204d6f76656420506572...
```

## Building

```bash
# Debug build
cargo build

# Optimized release build
cargo build --release

# Check without building
cargo check
```

## Dependencies

**Runtime**: Only system libraries (libc, libm, libgcc_s)

**Build**: tokio, reqwest, crossterm, zeroize, rand, serde, directories, tokio-socks, thiserror, clap, hex, url

## License

MIT OR Apache-2.0