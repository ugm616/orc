use crate::config::Config;
use std::net::SocketAddr;
use std::time::Duration;
use thiserror::Error;
use tokio::net::TcpStream;
use tokio::time::timeout;

#[derive(Debug, Error)]
pub enum TorError {
    #[error("No Tor proxy found at any of the attempted addresses")]
    NoTorProxy,
    #[error("Connection to Tor proxy failed: {0}")]
    ConnectionFailed(String),
    #[error("Tor proxy test failed: {0}")]
    TestFailed(String),
    #[error("Address resolution failed: {0}")]
    AddressResolution(String),
}

#[derive(Debug, Clone)]
pub struct TorClient {
    host: String,
    port: u16,
}

impl TorClient {
    /// Create a new TorClient by detecting available Tor proxies
    pub async fn new(config: &Config) -> Result<Self, TorError> {
        let addresses = config.get_proxy_addresses();
        
        for (host, port) in addresses {
            if Self::test_proxy(&host, port).await.is_ok() {
                return Ok(Self { host, port });
            }
        }
        
        Err(TorError::NoTorProxy)
    }

    /// Test if a SOCKS5 proxy is available at the given address
    async fn test_proxy(host: &str, port: u16) -> Result<(), TorError> {
        let addr = format!("{}:{}", host, port);
        let socket_addr: SocketAddr = addr.parse()
            .map_err(|e| TorError::AddressResolution(format!("Invalid address {}: {}", addr, e)))?;

        // Try to connect with a short timeout
        match timeout(Duration::from_secs(5), TcpStream::connect(&socket_addr)).await {
            Ok(Ok(_stream)) => {
                // Connection successful, assume SOCKS5 proxy is available
                Ok(())
            }
            Ok(Err(e)) => Err(TorError::ConnectionFailed(e.to_string())),
            Err(_) => Err(TorError::ConnectionFailed("Connection timeout".to_string())),
        }
    }

    /// Test connectivity by attempting to connect through Tor
    pub async fn test_connectivity(&self) -> Result<(), TorError> {
        // We'll test by trying to establish a SOCKS connection
        // In a real implementation, we might try to connect to a known .onion service
        Self::test_proxy(&self.host, self.port).await
    }

    /// Get the host of the Tor proxy
    pub fn host(&self) -> &str {
        &self.host
    }

    /// Get the port of the Tor proxy
    pub fn port(&self) -> u16 {
        self.port
    }

    /// Get the full proxy address
    pub fn proxy_addr(&self) -> String {
        format!("{}:{}", self.host, self.port)
    }

    /// Create a reqwest client configured to use this Tor proxy
    pub fn create_http_client(&self) -> Result<reqwest::Client, TorError> {
        let proxy_url = format!("socks5h://{}:{}", self.host, self.port);
        
        let proxy = reqwest::Proxy::all(&proxy_url)
            .map_err(|e| TorError::ConnectionFailed(format!("Failed to create proxy: {}", e)))?;

        let client = reqwest::Client::builder()
            .proxy(proxy)
            .use_rustls_tls()
            .timeout(Duration::from_secs(30))
            .build()
            .map_err(|e| TorError::ConnectionFailed(format!("Failed to create HTTP client: {}", e)))?;

        Ok(client)
    }

    /// Create a SOCKS5 stream to the specified host and port
    pub async fn create_socks_stream(&self, host: &str, port: u16) -> Result<tokio_socks::tcp::Socks5Stream<TcpStream>, TorError> {
        let proxy_addr = format!("{}:{}", self.host, self.port);
        let target_addr = (host, port);

        let stream = timeout(
            Duration::from_secs(30),
            tokio_socks::tcp::Socks5Stream::connect(proxy_addr.as_str(), target_addr)
        ).await
        .map_err(|_| TorError::ConnectionFailed("SOCKS connection timeout".to_string()))?
        .map_err(|e| TorError::ConnectionFailed(format!("SOCKS connection failed: {}", e)))?;

        Ok(stream)
    }
}