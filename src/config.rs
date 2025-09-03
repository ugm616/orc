use directories::ProjectDirs;
use serde::{Deserialize, Serialize};
use std::path::PathBuf;
use thiserror::Error;

#[derive(Debug, Error)]
pub enum ConfigError {
    #[error("IO error: {0}")]
    Io(#[from] std::io::Error),
    #[error("JSON parsing error: {0}")]
    Json(#[from] serde_json::Error),
    #[error("Invalid configuration: {0}")]
    Invalid(String),
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct Config {
    pub socks_host: String,
    pub socks_port: u16,
    pub config_path: Option<PathBuf>,
    pub wipe_paths: Vec<PathBuf>,
}

impl Default for Config {
    fn default() -> Self {
        Self {
            socks_host: "127.0.0.1".to_string(),
            socks_port: 9150, // Default to Tor Browser port
            config_path: None,
            wipe_paths: Vec::new(),
        }
    }
}

impl Config {
    /// Load configuration from environment variables, config file, or defaults
    pub fn load() -> Result<Self, ConfigError> {
        let mut config = Self::default();

        // Try to load from config file first
        if let Some(config_path) = Self::get_config_path() {
            if config_path.exists() {
                let content = std::fs::read_to_string(&config_path)?;
                let file_config: Config = serde_json::from_str(&content)?;
                config = file_config;
                config.config_path = Some(config_path);
            }
        }

        // Override with environment variables if present
        if let Ok(host) = std::env::var("ORC_SOCKS_HOST") {
            config.socks_host = host;
        }

        if let Ok(port_str) = std::env::var("ORC_SOCKS_PORT") {
            config.socks_port = port_str.parse()
                .map_err(|_| ConfigError::Invalid(format!("Invalid port in ORC_SOCKS_PORT: {}", port_str)))?;
        }

        // Override config path if specified
        if let Ok(config_path) = std::env::var("ORC_CONFIG") {
            config.config_path = Some(PathBuf::from(config_path));
        }

        // Validate configuration
        config.validate()?;

        Ok(config)
    }

    /// Get the default config file path
    pub fn get_config_path() -> Option<PathBuf> {
        ProjectDirs::from("", "", "orc")
            .map(|dirs| dirs.config_dir().join("config.json"))
    }

    /// Validate the configuration
    fn validate(&self) -> Result<(), ConfigError> {
        if self.socks_host.is_empty() {
            return Err(ConfigError::Invalid("SOCKS host cannot be empty".to_string()));
        }

        if self.socks_port == 0 {
            return Err(ConfigError::Invalid("SOCKS port cannot be zero".to_string()));
        }

        Ok(())
    }

    /// Save the current configuration to file
    pub fn save(&self) -> Result<(), ConfigError> {
        if let Some(config_path) = &self.config_path {
            if let Some(parent) = config_path.parent() {
                std::fs::create_dir_all(parent)?;
            }
            let content = serde_json::to_string_pretty(self)?;
            std::fs::write(config_path, content)?;
        }
        Ok(())
    }

    /// Get the list of proxy addresses to try in order
    pub fn get_proxy_addresses(&self) -> Vec<(String, u16)> {
        vec![
            // Environment variables take priority
            (self.socks_host.clone(), self.socks_port),
            // Then try Tor Browser default
            ("127.0.0.1".to_string(), 9150),
            // Then try system Tor default
            ("127.0.0.1".to_string(), 9050),
        ]
    }
}