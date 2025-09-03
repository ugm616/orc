use crate::security::validate_onion_url;
use crate::tor::TorClient;
use std::collections::HashMap;
use thiserror::Error;

#[derive(Debug, Error)]
pub enum HttpError {
    #[error("Invalid URL: {0}")]
    InvalidUrl(String),
    #[error("Non-onion URL rejected: {0}")]
    NonOnionUrl(String),
    #[error("HTTP request failed: {0}")]
    RequestFailed(String),
    #[error("Response parsing failed: {0}")]
    ResponseParsing(String),
    #[error("Security validation failed: {0}")]
    SecurityValidation(#[from] crate::security::SecurityError),
    #[error("Tor client error: {0}")]
    TorClient(#[from] crate::tor::TorError),
}

#[derive(Debug)]
pub struct HttpResponse {
    pub status: u16,
    pub headers: HashMap<String, String>,
    pub body: String,
}

/// Fetch a URL via Tor, ensuring it's a .onion address
pub async fn fetch_url(tor_client: &TorClient, url: &str) -> Result<HttpResponse, HttpError> {
    // Validate that this is a .onion URL
    validate_onion_url(url)?;

    // Create HTTP client configured for Tor
    let client = tor_client.create_http_client()?;

    // Make the request
    let response = client
        .get(url)
        .send()
        .await
        .map_err(|e| HttpError::RequestFailed(format!("Request failed: {}", e)))?;

    // Extract status code
    let status = response.status().as_u16();

    // Extract headers
    let mut headers = HashMap::new();
    for (key, value) in response.headers() {
        headers.insert(
            key.to_string(),
            value.to_str()
                .map_err(|e| HttpError::ResponseParsing(format!("Invalid header value: {}", e)))?
                .to_string(),
        );
    }

    // Extract body
    let body = response
        .text()
        .await
        .map_err(|e| HttpError::ResponseParsing(format!("Failed to read response body: {}", e)))?;

    Ok(HttpResponse {
        status,
        headers,
        body,
    })
}

/// Fetch a URL and return only the response body
pub async fn fetch_url_body(tor_client: &TorClient, url: &str) -> Result<String, HttpError> {
    let response = fetch_url(tor_client, url).await?;
    Ok(response.body)
}

/// Make a POST request with data via Tor
pub async fn post_data(
    tor_client: &TorClient,
    url: &str,
    data: &[u8],
    content_type: Option<&str>,
) -> Result<HttpResponse, HttpError> {
    // Validate that this is a .onion URL
    validate_onion_url(url)?;

    // Create HTTP client configured for Tor
    let client = tor_client.create_http_client()?;

    // Build the request
    let mut request = client.post(url).body(data.to_vec());

    if let Some(ct) = content_type {
        request = request.header("Content-Type", ct);
    }

    // Make the request
    let response = request
        .send()
        .await
        .map_err(|e| HttpError::RequestFailed(format!("POST request failed: {}", e)))?;

    // Extract status code
    let status = response.status().as_u16();

    // Extract headers
    let mut headers = HashMap::new();
    for (key, value) in response.headers() {
        headers.insert(
            key.to_string(),
            value.to_str()
                .map_err(|e| HttpError::ResponseParsing(format!("Invalid header value: {}", e)))?
                .to_string(),
        );
    }

    // Extract body
    let body = response
        .text()
        .await
        .map_err(|e| HttpError::ResponseParsing(format!("Failed to read response body: {}", e)))?;

    Ok(HttpResponse {
        status,
        headers,
        body,
    })
}