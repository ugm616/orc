package auth

import (
	"crypto/rand"
	"crypto/subtle"
	"fmt"
	"math/big"
	"strings"

	"golang.org/x/crypto/argon2"
)

const (
	// Argon2id parameters
	argonTime    = 1
	argonMemory  = 64 * 1024
	argonThreads = 4
	argonKeyLen  = 32
	saltLen      = 16
)

// GenerateAccountID creates a random 12-digit account ID
func GenerateAccountID() (string, error) {
	// Generate random number between 100000000000 and 999999999999
	min := big.NewInt(100000000000)
	max := big.NewInt(999999999999)
	
	n, err := rand.Int(rand.Reader, new(big.Int).Sub(max, min))
	if err != nil {
		return "", err
	}
	
	return fmt.Sprintf("%d", new(big.Int).Add(n, min)), nil
}

// GenerateRecoveryPhrase creates a recovery phrase from random words
func GenerateRecoveryPhrase() (string, error) {
	words := []string{
		"alpha", "beta", "gamma", "delta", "epsilon", "zeta", "eta", "theta",
		"iota", "kappa", "lambda", "mu", "nu", "xi", "omicron", "pi",
		"rho", "sigma", "tau", "upsilon", "phi", "chi", "psi", "omega",
		"storm", "ocean", "mountain", "forest", "river", "stone", "flame", "wind",
		"shadow", "light", "moon", "star", "sun", "cloud", "rain", "snow",
		"eagle", "wolf", "bear", "fox", "hawk", "owl", "deer", "lion",
		"tiger", "dragon", "phoenix", "crow", "dove", "swan", "robin", "falcon",
	}
	
	phrase := make([]string, 6)
	for i := 0; i < 6; i++ {
		idx, err := rand.Int(rand.Reader, big.NewInt(int64(len(words))))
		if err != nil {
			return "", err
		}
		phrase[i] = words[idx.Int64()]
	}
	
	return strings.Join(phrase, " "), nil
}

// GenerateSalt creates a random salt
func GenerateSalt() ([]byte, error) {
	salt := make([]byte, saltLen)
	_, err := rand.Read(salt)
	return salt, err
}

// HashPassword hashes a password using Argon2id
func HashPassword(password string) (string, error) {
	salt, err := GenerateSalt()
	if err != nil {
		return "", err
	}
	
	hash := argon2.IDKey([]byte(password), salt, argonTime, argonMemory, argonThreads, argonKeyLen)
	
	// Format: $argon2id$v=19$m=65536,t=1,p=4$salt$hash
	return fmt.Sprintf("$argon2id$v=19$m=%d,t=%d,p=%d$%x$%x",
		argonMemory, argonTime, argonThreads, salt, hash), nil
}

// VerifyPassword verifies a password against a hash
func VerifyPassword(password, hashStr string) bool {
	parts := strings.Split(hashStr, "$")
	if len(parts) != 6 || parts[1] != "argon2id" {
		return false
	}
	
	// Parse parameters
	var memory, time, threads uint32
	if _, err := fmt.Sscanf(parts[3], "m=%d,t=%d,p=%d", &memory, &time, &threads); err != nil {
		return false
	}
	
	// Parse salt and hash
	salt := make([]byte, saltLen)
	hash := make([]byte, argonKeyLen)
	
	if _, err := fmt.Sscanf(parts[4], "%x", &salt); err != nil {
		return false
	}
	if _, err := fmt.Sscanf(parts[5], "%x", &hash); err != nil {
		return false
	}
	
	// Compute hash for provided password
	computedHash := argon2.IDKey([]byte(password), salt, time, memory, uint8(threads), argonKeyLen)
	
	// Constant-time comparison
	return subtle.ConstantTimeCompare(hash, computedHash) == 1
}

// ValidateAccountID checks if an account ID is valid format
func ValidateAccountID(id string) bool {
	if len(id) != 12 {
		return false
	}
	
	for _, r := range id {
		if r < '0' || r > '9' {
			return false
		}
	}
	
	return true
}

// ValidatePassword checks password requirements
func ValidatePassword(password string) error {
	if len(password) < 8 {
		return fmt.Errorf("password must be at least 8 characters")
	}
	if len(password) > 128 {
		return fmt.Errorf("password must be no more than 128 characters")
	}
	return nil
}

// ValidateDisplayName checks display name requirements
func ValidateDisplayName(name string) error {
	if len(name) < 1 {
		return fmt.Errorf("display name is required")
	}
	if len(name) > 50 {
		return fmt.Errorf("display name must be no more than 50 characters")
	}
	return nil
}