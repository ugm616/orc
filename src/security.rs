use std::panic;
use std::process;
use tokio::signal;
use zeroize::Zeroize;
use thiserror::Error;

#[derive(Debug, Error)]
pub enum SecurityError {
    #[error("Invalid hex data: {0}")]
    InvalidHex(String),
    #[error("IO error during secure wipe: {0}")]
    SecureWipeError(#[from] std::io::Error),
}

/// A wrapper for sensitive string data that gets zeroized on drop
pub struct SensitiveString {
    data: String,
}

impl SensitiveString {
    pub fn new(data: String) -> Self {
        Self { data }
    }

    pub fn from_hex(hex: &str) -> Result<Self, SecurityError> {
        // Validate hex string
        if hex.len() % 2 != 0 {
            return Err(SecurityError::InvalidHex("Hex string must have even length".to_string()));
        }

        for c in hex.chars() {
            if !c.is_ascii_hexdigit() {
                return Err(SecurityError::InvalidHex(format!("Invalid hex character: {}", c)));
            }
        }

        // Convert hex to bytes, then to string
        let bytes = hex::decode(hex)
            .map_err(|e| SecurityError::InvalidHex(e.to_string()))?;
        
        let data = String::from_utf8_lossy(&bytes).to_string();
        Ok(Self::new(data))
    }

    pub fn expose(&self) -> &str {
        &self.data
    }

    pub fn expose_bytes(&self) -> &[u8] {
        self.data.as_bytes()
    }
}

impl Drop for SensitiveString {
    fn drop(&mut self) {
        self.data.zeroize();
    }
}

impl Zeroize for SensitiveString {
    fn zeroize(&mut self) {
        self.data.zeroize();
    }
}

impl Drop for SensitiveBytes {
    fn drop(&mut self) {
        self.data.zeroize();
    }
}

/// A wrapper for sensitive byte data that gets zeroized on drop
pub struct SensitiveBytes {
    data: Vec<u8>,
}

impl SensitiveBytes {
    pub fn new(data: Vec<u8>) -> Self {
        Self { data }
    }

    pub fn from_hex(hex: &str) -> Result<Self, SecurityError> {
        let data = hex::decode(hex)
            .map_err(|e| SecurityError::InvalidHex(e.to_string()))?;
        Ok(Self::new(data))
    }

    pub fn expose(&self) -> &[u8] {
        &self.data
    }
}

impl Zeroize for SensitiveBytes {
    fn zeroize(&mut self) {
        self.data.zeroize();
    }
}

/// Validate that a hostname is a .onion address
pub fn validate_onion_host(host: &str) -> Result<(), SecurityError> {
    if !host.ends_with(".onion") {
        return Err(SecurityError::InvalidHex(
            format!("Host must be a .onion address, got: {}", host)
        ));
    }

    // Basic validation of onion address format
    let domain_part = host.strip_suffix(".onion").unwrap();
    
    // v2 onion addresses are 16 characters base32
    // v3 onion addresses are 56 characters base32
    if domain_part.len() != 16 && domain_part.len() != 56 {
        return Err(SecurityError::InvalidHex(
            format!("Invalid onion address length: {}", host)
        ));
    }

    // Check if it's valid base32 (simplified check)
    for c in domain_part.chars() {
        if !c.is_ascii_alphanumeric() {
            return Err(SecurityError::InvalidHex(
                format!("Invalid character in onion address: {}", c)
            ));
        }
    }

    Ok(())
}

/// Validate that a URL is a .onion URL
pub fn validate_onion_url(url: &str) -> Result<(), SecurityError> {
    if !url.starts_with("http://") && !url.starts_with("https://") {
        return Err(SecurityError::InvalidHex(
            format!("URL must start with http:// or https://, got: {}", url)
        ));
    }

    // Extract hostname from URL
    let url_parsed = url::Url::parse(url)
        .map_err(|e| SecurityError::InvalidHex(format!("Invalid URL: {}", e)))?;
    
    if let Some(host) = url_parsed.host_str() {
        validate_onion_host(host)?;
    } else {
        return Err(SecurityError::InvalidHex("URL must contain a hostname".to_string()));
    }

    Ok(())
}

/// Install panic handlers for emergency cleanup
pub fn install_panic_handlers() {
    // Set custom panic hook
    panic::set_hook(Box::new(|panic_info| {
        eprintln!("PANIC: {}", panic_info);
        emergency_exit();
    }));

    // Handle Ctrl+C
    tokio::spawn(async {
        match signal::ctrl_c().await {
            Ok(()) => {
                eprintln!("Received SIGINT, performing emergency exit...");
                emergency_exit();
            }
            Err(err) => {
                eprintln!("Unable to listen for Ctrl+C: {}", err);
            }
        }
    });
}

/// Perform emergency cleanup and exit
fn emergency_exit() {
    eprintln!("Performing emergency cleanup...");
    
    // TODO: Add any sensitive data cleanup here
    // This would zeroize any global sensitive data structures
    
    // Exit with code 137 (128 + 9, indicating killed by signal)
    process::exit(137);
}

/// Securely wipe a file by overwriting it with random data
pub fn secure_wipe_file(path: &std::path::Path) -> Result<(), SecurityError> {
    use std::fs::OpenOptions;
    use std::io::{Seek, SeekFrom, Write};
    use rand::RngCore;

    if !path.exists() {
        return Ok(());
    }

    let mut file = OpenOptions::new()
        .write(true)
        .open(path)?;

    // Get file size
    let file_size = file.seek(SeekFrom::End(0))?;
    file.seek(SeekFrom::Start(0))?;

    // Overwrite with random data
    let mut rng = rand::thread_rng();
    let mut buffer = vec![0u8; 4096];
    
    let mut remaining = file_size;
    while remaining > 0 {
        let to_write = std::cmp::min(remaining, buffer.len() as u64) as usize;
        rng.fill_bytes(&mut buffer[..to_write]);
        file.write_all(&buffer[..to_write])?;
        remaining -= to_write as u64;
    }
    
    file.sync_all()?;
    drop(file);

    // Remove the file
    std::fs::remove_file(path)?;
    
    Ok(())
}