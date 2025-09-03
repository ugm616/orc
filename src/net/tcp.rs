use crate::security::validate_onion_host;
use crate::tor::TorClient;
use thiserror::Error;
use tokio::io::{AsyncReadExt, AsyncWriteExt};

#[derive(Debug, Error)]
pub enum TcpError {
    #[error("Invalid host: {0}")]
    InvalidHost(String),
    #[error("Non-onion host rejected: {0}")]
    NonOnionHost(String),
    #[error("Connection failed: {0}")]
    ConnectionFailed(String),
    #[error("IO error: {0}")]
    Io(#[from] std::io::Error),
    #[error("Security validation failed: {0}")]
    SecurityValidation(#[from] crate::security::SecurityError),
    #[error("Tor client error: {0}")]
    TorClient(#[from] crate::tor::TorError),
}

/// Send data to a host via Tor and return the response
pub async fn stream_data(
    tor_client: &TorClient,
    host: &str,
    port: u16,
    data: &str,
) -> Result<Vec<u8>, TcpError> {
    // Validate that this is a .onion host
    validate_onion_host(host)?;

    // Create SOCKS5 connection through Tor
    let mut stream = tor_client.create_socks_stream(host, port).await?;

    // Send the data
    stream.write_all(data.as_bytes()).await?;
    stream.flush().await?;

    // Read response
    let mut response = Vec::new();
    stream.read_to_end(&mut response).await?;

    Ok(response)
}

/// Send raw bytes to a host via Tor and return the response
pub async fn stream_bytes(
    tor_client: &TorClient,
    host: &str,
    port: u16,
    data: &[u8],
) -> Result<Vec<u8>, TcpError> {
    // Validate that this is a .onion host
    validate_onion_host(host)?;

    // Create SOCKS5 connection through Tor
    let mut stream = tor_client.create_socks_stream(host, port).await?;

    // Send the data
    stream.write_all(data).await?;
    stream.flush().await?;

    // Read response
    let mut response = Vec::new();
    stream.read_to_end(&mut response).await?;

    Ok(response)
}

/// Send data and read a specific amount of response bytes
pub async fn stream_data_with_length(
    tor_client: &TorClient,
    host: &str,
    port: u16,
    data: &[u8],
    response_length: usize,
) -> Result<Vec<u8>, TcpError> {
    // Validate that this is a .onion host
    validate_onion_host(host)?;

    // Create SOCKS5 connection through Tor
    let mut stream = tor_client.create_socks_stream(host, port).await?;

    // Send the data
    stream.write_all(data).await?;
    stream.flush().await?;

    // Read exact amount of response
    let mut response = vec![0u8; response_length];
    stream.read_exact(&mut response).await?;

    Ok(response)
}

/// Establish a connection and return the stream for manual handling
pub async fn connect_stream(
    tor_client: &TorClient,
    host: &str,
    port: u16,
) -> Result<tokio_socks::tcp::Socks5Stream<tokio::net::TcpStream>, TcpError> {
    // Validate that this is a .onion host
    validate_onion_host(host)?;

    // Create SOCKS5 connection through Tor
    let stream = tor_client.create_socks_stream(host, port).await?;

    Ok(stream)
}

/// Test connectivity to a host without sending data
pub async fn test_connection(
    tor_client: &TorClient,
    host: &str,
    port: u16,
) -> Result<(), TcpError> {
    // Validate that this is a .onion host
    validate_onion_host(host)?;

    // Try to establish connection and immediately close it
    let _stream = tor_client.create_socks_stream(host, port).await?;
    
    Ok(())
}