use clap::{Arg, Command};
use std::process;
use zeroize::Zeroize;

mod config;
mod net;
mod security;
mod tor;

use config::Config;
use security::{install_panic_handlers, SensitiveString};

#[tokio::main]
async fn main() {
    // Install panic handlers early
    install_panic_handlers();
    
    let matches = Command::new("orc")
        .version("0.1.0")
        .about("A lean terminal client that routes traffic through Tor")
        .arg(
            Arg::new("check")
                .long("check")
                .help("Verify Tor availability")
                .action(clap::ArgAction::SetTrue),
        )
        .arg(
            Arg::new("verbose")
                .long("verbose")
                .short('v')
                .help("Enable verbose output")
                .action(clap::ArgAction::SetTrue),
        )
        .subcommand(
            Command::new("fetch")
                .about("Fetch a URL via Tor")
                .arg(
                    Arg::new("url")
                        .long("url")
                        .value_name("ONION_URL")
                        .help("The .onion URL to fetch")
                        .required(true),
                ),
        )
        .subcommand(
            Command::new("stream")
                .about("Raw TCP stream via Tor")
                .arg(
                    Arg::new("host")
                        .long("host")
                        .value_name("ONION_HOST")
                        .help("The .onion host to connect to")
                        .required(true),
                )
                .arg(
                    Arg::new("port")
                        .long("port")
                        .value_name("PORT")
                        .help("The port to connect to")
                        .required(true),
                )
                .arg(
                    Arg::new("hex")
                        .long("hex")
                        .value_name("HEX_DATA")
                        .help("Hex-encoded data to send")
                        .required(true),
                ),
        )
        .get_matches();

    let verbose = matches.get_flag("verbose");
    
    // Load configuration
    let config = match Config::load() {
        Ok(config) => config,
        Err(e) => {
            eprintln!("Error loading configuration: {}", e);
            process::exit(1);
        }
    };

    // Setup Tor client
    let tor_client = match tor::TorClient::new(&config).await {
        Ok(client) => client,
        Err(e) => {
            eprintln!("Error: Tor is not available. {}", e);
            eprintln!("Please ensure Tor is running on one of the following addresses:");
            eprintln!("  - 127.0.0.1:9150 (Tor Browser)");
            eprintln!("  - 127.0.0.1:9050 (System Tor)");
            eprintln!("  - Custom address via ORC_SOCKS_HOST/ORC_SOCKS_PORT environment variables");
            process::exit(1);
        }
    };

    if verbose {
        println!("Connected to Tor at {}:{}", tor_client.host(), tor_client.port());
    }

    // Handle different commands
    let result = if matches.get_flag("check") {
        handle_check(&tor_client, verbose).await
    } else if let Some(fetch_matches) = matches.subcommand_matches("fetch") {
        let url = fetch_matches.get_one::<String>("url").unwrap();
        handle_fetch(&tor_client, url, verbose).await
    } else if let Some(stream_matches) = matches.subcommand_matches("stream") {
        let host = stream_matches.get_one::<String>("host").unwrap();
        let port = stream_matches.get_one::<String>("port").unwrap();
        let hex_data = stream_matches.get_one::<String>("hex").unwrap();
        handle_stream(&tor_client, host, port, hex_data, verbose).await
    } else {
        eprintln!("No command specified. Use --help for usage information.");
        process::exit(1);
    };

    match result {
        Ok(_) => process::exit(0),
        Err(e) => {
            eprintln!("Error: {}", e);
            process::exit(1);
        }
    }
}

async fn handle_check(tor_client: &tor::TorClient, verbose: bool) -> Result<(), Box<dyn std::error::Error>> {
    if verbose {
        println!("Checking Tor connectivity...");
    }
    
    // Test connection by attempting to resolve a known .onion address
    match tor_client.test_connectivity().await {
        Ok(_) => {
            println!("✓ Tor is available and working at {}:{}", tor_client.host(), tor_client.port());
            Ok(())
        }
        Err(e) => {
            eprintln!("✗ Tor connectivity test failed: {}", e);
            Err(e.into())
        }
    }
}

async fn handle_fetch(
    tor_client: &tor::TorClient,
    url: &str,
    verbose: bool,
) -> Result<(), Box<dyn std::error::Error>> {
    if verbose {
        println!("Fetching URL: {}", url);
    }

    let response = net::http::fetch_url(tor_client, url).await?;
    
    if verbose {
        println!("Response status: {}", response.status);
        println!("Response headers:");
        for (key, value) in &response.headers {
            println!("  {}: {}", key, value);
        }
        println!();
    }
    
    println!("{}", response.body);
    Ok(())
}

async fn handle_stream(
    tor_client: &tor::TorClient,
    host: &str,
    port_str: &str,
    hex_data: &str,
    verbose: bool,
) -> Result<(), Box<dyn std::error::Error>> {
    let port: u16 = port_str.parse()
        .map_err(|_| format!("Invalid port number: {}", port_str))?;

    if verbose {
        println!("Connecting to {}:{}", host, port);
        println!("Sending hex data: {}", hex_data);
    }

    let mut sensitive_data = SensitiveString::from_hex(hex_data)?;
    let response = net::tcp::stream_data(tor_client, host, port, &sensitive_data.expose()).await?;
    sensitive_data.zeroize();

    if verbose {
        println!("Received {} bytes", response.len());
    }
    
    println!("{}", hex::encode(&response));
    Ok(())
}